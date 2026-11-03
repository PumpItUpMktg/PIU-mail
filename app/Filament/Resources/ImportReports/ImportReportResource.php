<?php

namespace App\Filament\Resources\ImportReports;

use App\Filament\Concerns\SuperAdminOnly;
use App\Filament\Resources\ImportReports\Pages\ListImportReports;
use App\Filament\Resources\ImportReports\Pages\ViewImportReport;
use App\Filament\Resources\ImportReports\Schemas\ImportReportInfolist;
use App\Filament\Resources\ImportReports\Tables\ImportReportsTable;
use App\Models\ImportReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ImportReportResource extends Resource
{
    use SuperAdminOnly;

    protected static ?string $model = ImportReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'NJ parcel data';

    protected static ?int $navigationSort = 3;

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
        return ImportReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportReports::route('/'),
            'view' => ViewImportReport::route('/{record}'),
        ];
    }
}
