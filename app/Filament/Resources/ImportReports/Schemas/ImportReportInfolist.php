<?php

namespace App\Filament\Resources\ImportReports\Schemas;

use App\Models\ImportReport;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $t = fn (string $name, ?string $label = null) => TextEntry::make($name)->label($label ?? str($name)->headline())->placeholder('—');

        return $schema->columns(1)->components([
            Section::make('Run')->columns(4)->schema([
                $t('county'), $t('trigger'), $t('status'), $t('source_layer_version', 'Layer version'),
                $t('started_at')->dateTime(), $t('finished_at')->dateTime(), $t('expected_count', 'Source rows'),
                $t('error')->columnSpanFull(),
            ]),
            Section::make('Counts')->columns(5)->schema([
                $t('fetched'), $t('inserted'), $t('updated'), $t('duplicates'), $t('skipped_by_class', 'Skipped by class'),
                $t('null_year', 'Null year'), $t('null_geometry', 'Null geometry'), $t('no_zip', 'No ZIP'),
                $t('bldg_desc_present', 'BLDG_DESC present'),
                TextEntry::make('decode')->label('BLDG_DESC decoded')
                    ->state(fn (ImportReport $r) => $r->bldg_desc_decoded.($r->decodeRate() !== null ? " ({$r->decodeRate()}%)" : '')),
            ]),
            Section::make('Source freshness')->columns(4)->schema([
                $t('source_pub_date_min', 'PCL_PBDATE min')->date(), $t('source_pub_date_max', 'PCL_PBDATE max')->date(),
                $t('source_last_upd_min', 'PCLLASTUPD min')->date(), $t('source_last_upd_max', 'PCLLASTUPD max')->date(),
            ]),
            Section::make('Rows per property class')->columns(2)->schema([
                KeyValueEntry::make('counts_by_class')->label('Imported')->keyLabel('Class')->valueLabel('Rows'),
                KeyValueEntry::make('skipped_classes')->label('Skipped')->keyLabel('Class')->valueLabel('Rows'),
            ]),
            Section::make('Notes')->schema([
                TextEntry::make('notes')->hiddenLabel()->listWithLineBreaks()->placeholder('None'),
            ]),
        ]);
    }
}
