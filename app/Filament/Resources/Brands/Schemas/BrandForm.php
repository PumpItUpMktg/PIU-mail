<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tenant_id')->relationship('tenant', 'name')->required()->searchable()->preload(),
            TextInput::make('name')->required(),
            TextInput::make('slug')->required()->helperText('Unique within the tenant.'),
            TextInput::make('logo_path')->label('Logo path'),
            ColorPicker::make('primary_color'),
            TextInput::make('phone')->tel(),
            TextInput::make('website_url')->url(),
            Textarea::make('return_address')->rows(3),
        ]);
    }
}
