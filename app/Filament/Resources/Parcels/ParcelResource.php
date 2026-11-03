<?php

namespace App\Filament\Resources\Parcels;

use App\Filament\Concerns\SuperAdminOnly;
use App\Filament\Resources\Parcels\Pages\ListParcels;
use App\Filament\Resources\Parcels\Pages\ViewParcel;
use App\Filament\Resources\Parcels\Schemas\ParcelInfolist;
use App\Filament\Resources\Parcels\Tables\ParcelsTable;
use App\Models\Parcel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Read-only browser over the global NJ parcel reference data. */
class ParcelResource extends Resource
{
    use SuperAdminOnly;

    protected static ?string $model = Parcel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'NJ parcel data';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'site_address';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ParcelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParcelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParcels::route('/'),
            'view' => ViewParcel::route('/{record}'),
        ];
    }
}
