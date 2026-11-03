<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge()->formatStateUsing(fn (Role $state) => $state->label()),
                TextColumn::make('tenant.name')->label('Tenant')->placeholder('— (all tenants)'),
            ])
            ->filters([
                SelectFilter::make('role')->options(Role::options()),
                SelectFilter::make('tenant')->relationship('tenant', 'name'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
