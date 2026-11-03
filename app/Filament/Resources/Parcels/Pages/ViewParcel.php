<?php

namespace App\Filament\Resources\Parcels\Pages;

use App\Filament\Resources\Parcels\ParcelResource;
use Filament\Resources\Pages\ViewRecord;

class ViewParcel extends ViewRecord
{
    protected static string $resource = ParcelResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
