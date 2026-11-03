<?php

namespace App\Filament\Resources\Counties\Pages;

use App\Filament\Resources\Counties\CountyResource;
use Filament\Resources\Pages\ListRecords;

class ListCounties extends ListRecords
{
    protected static string $resource = CountyResource::class;
}
