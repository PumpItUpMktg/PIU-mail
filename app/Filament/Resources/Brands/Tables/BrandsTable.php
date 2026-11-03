<?php

namespace App\Filament\Resources\Brands\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')->label('Tenant')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug'),
                TextColumn::make('phone')->toggleable(),
                TextColumn::make('website_url')->toggleable(),
            ])
            ->filters([SelectFilter::make('tenant')->relationship('tenant', 'name')])
            ->recordActions([EditAction::make()]);
    }
}
