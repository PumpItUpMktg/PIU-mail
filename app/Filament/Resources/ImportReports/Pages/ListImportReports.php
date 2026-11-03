<?php

namespace App\Filament\Resources\ImportReports\Pages;

use App\Filament\Resources\ImportReports\ImportReportResource;
use Filament\Resources\Pages\ListRecords;

class ListImportReports extends ListRecords
{
    protected static string $resource = ImportReportResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
