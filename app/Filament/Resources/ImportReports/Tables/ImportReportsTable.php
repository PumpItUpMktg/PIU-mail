<?php

namespace App\Filament\Resources\ImportReports\Tables;

use App\Enums\ImportStatus;
use App\Models\ImportReport;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Started')->dateTime()->sortable(),
                TextColumn::make('county')->sortable()->searchable(),
                TextColumn::make('trigger')->badge(),
                TextColumn::make('status')->badge()->color(fn (ImportStatus $state) => match ($state) {
                    ImportStatus::Completed => 'success',
                    ImportStatus::Failed => 'danger',
                    ImportStatus::Running => 'warning',
                }),
                TextColumn::make('fetched')->numeric(),
                TextColumn::make('inserted')->numeric(),
                TextColumn::make('updated')->numeric(),
                TextColumn::make('duplicates')->numeric(),
                TextColumn::make('skipped_by_class')->label('Skipped (class)')->numeric()->toggleable(),
                TextColumn::make('null_year')->numeric()->toggleable(),
                TextColumn::make('no_zip')->label('No ZIP')->numeric()->toggleable(),
                TextColumn::make('decode_rate')->label('BLDG_DESC decoded')
                    ->state(fn (ImportReport $r) => $r->decodeRate() === null ? '—' : $r->decodeRate().'%'),
                TextColumn::make('finished_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(ImportStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])),
                SelectFilter::make('county')->options(fn () => ImportReport::query()->distinct()->orderBy('county')->pluck('county', 'county')),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
