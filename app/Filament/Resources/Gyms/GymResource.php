<?php

namespace App\Filament\Resources\Gyms;

use App\Filament\Resources\Gyms\Pages\ListGyms;
use App\Filament\Resources\Gyms\Schemas\GymForm;
use App\Filament\Resources\Gyms\Schemas\GymInfolist;
use App\Filament\Resources\Gyms\Tables\GymTable;
use App\Models\Gym;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Superadmin-only resource for managing branches (Gyms).
 */
class GymResource extends Resource
{
    protected static ?string $model = Gym::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('app.resources.gyms.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.gyms.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'slug',
            'code',
            'email',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Gym $record */
        return [
            __('app.fields.status') => (string) $record->status,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return GymForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GymTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GymInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGyms::route('/'),
        ];
    }
}
