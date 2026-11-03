<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->options(Role::options())->required()->live(),
            Select::make('tenant_id')
                ->relationship('tenant', 'name')
                ->helperText('Super admins have no tenant.')
                ->required(fn (Get $get) => $get('role') !== null && $get('role') !== Role::SuperAdmin->value && $get('role') !== Role::SuperAdmin)
                ->hidden(fn (Get $get) => $get('role') === Role::SuperAdmin->value || $get('role') === Role::SuperAdmin)
                ->dehydrateStateUsing(fn ($state, Get $get) => in_array($get('role'), [Role::SuperAdmin->value, Role::SuperAdmin], true) ? null : $state),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->minLength(12)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state)),
        ]);
    }
}
