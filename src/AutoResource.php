<?php

namespace Miguilim\FilamentAutoResource;

use Filament\Tables;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Miguilim\FilamentAutoResource\Generators\FormGenerator;
use Miguilim\FilamentAutoResource\Generators\InfolistGenerator;
use Miguilim\FilamentAutoResource\Generators\TableGenerator;
use Miguilim\FilamentAutoResource\Mounters\PageMounter;

class AutoResource extends Resource
{
    protected static array $enumDictionary = [];

    protected static array $visibleColumns = [];

    protected static array $searchableColumns = [];

    protected static bool $intrusive = true;

    public static function getExtraPages(): array
    {
        return [];
    }

    public static function getFilters(): array
    {
        return [];
    }

    public static function getActions(): array
    {
        return [];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema(InfolistGenerator::make(modelClass: static::getModel(), enumDictionary: static::$enumDictionary))
            ->columns(3);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(FormGenerator::make(modelClass: static::getModel(), enumDictionary: static::$enumDictionary))
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        $hasSoftDeletes = method_exists(static::getModel(), 'bootSoftDeletes');

        $defaultFilters = [...static::getFilters()];
        $defaultTableActions = [...static::getTableActions(), Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()];
        $defaultBulkActions = [...static::getBulkActions(), Tables\Actions\DeleteBulkAction::make()];

        if ($hasSoftDeletes) {
            $defaultFilters[] = Tables\Filters\TrashedFilter::make();

            $defaultTableActions[] = Tables\Actions\RestoreAction::make();

            $defaultBulkActions[] = Tables\Actions\RestoreBulkAction::make();
            $defaultBulkActions[] = Tables\Actions\ForceDeleteBulkAction::make();
        }

        $tableSchema = TableGenerator::make(
            modelClass: static::getModel(), 
            enumDictionary: static::$enumDictionary, 
            searchableColumns: static::$searchableColumns, 
            visibleColumns: static::$visibleColumns
        );

        // Define automatic sort by column
        if ($table->getDefaultSortColumn() === null) {
            $sortColumnsAvailable = collect($tableSchema)
            ->filter(fn ($column) => $column->isSortable())
            ->map(fn ($column) => $column->getName())
            ->values();

            $modelClass = static::getModel();
            $dummyModel = new $modelClass;

            if ($dummyModel->getIncrementing() && $sortColumnsAvailable->contains($dummyModel->getKeyName())) {
                $table = $table->defaultSort($dummyModel->getKeyName(), 'desc');
            } else if ($sortColumnsAvailable->contains('created_at')) {
                $table = $table->defaultSort('created_at', 'desc');
            } else  if ($sortColumnsAvailable->contains('updated_at')) {
                $table = $table->defaultSort('updated_at', 'desc');
            }
        }

        return $table
            ->columns($tableSchema)
            ->filters($defaultFilters)
            ->actions($defaultTableActions)
            ->bulkActions($defaultBulkActions);
    }

    public static function getPages(): array
    {
        return [...static::getExtraPages(), ...[
            'index'  => PageMounter::makeList(static::class),
            'create' => PageMounter::makeCreate(static::class),
            'view'   => PageMounter::makeView(static::class),
        ]];
    }

    public static function getEloquentQuery(): Builder
    {
        $parent = parent::getEloquentQuery();

        if (method_exists(static::getModel(), 'bootSoftDeletes')) {
            $parent = $parent->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
        }

        return $parent;
    }

    public static function getIntrusive(): bool
    {
        return static::$intrusive;
    }

    protected static function getBulkActions(): array
    {
        return collect(static::getActions())
            ->filter(fn (AutoAction $action) => $action->showOnBulkAction)
            ->map(fn (AutoAction $action) => $action->convertToBulkAction())
            ->all();
    }

    protected static function getTableActions(): array
    {
        return collect(static::getActions())
            ->filter(fn (AutoAction $action) => $action->showOnTable)
            ->map(fn (AutoAction $action) => $action->convertToTableAction())
            ->all();
    }

    public static function getPagesActions(): array
    {
        return collect(static::getActions())
            ->filter(fn (AutoAction $action) => $action->showOnViewPage)
            ->map(fn (AutoAction $action) => $action->convertToViewPageAction())
            ->all();
    }
}
