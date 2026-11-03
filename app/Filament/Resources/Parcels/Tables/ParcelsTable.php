<?php

namespace App\Filament\Resources\Parcels\Tables;

use App\Models\Parcel;
use App\Parcels\BldgDescDecoder;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ParcelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('site_address')
            ->columns([
                TextColumn::make('site_address')->label('Address')->searchable()->sortable(),
                TextColumn::make('municipality')->sortable()->toggleable(),
                TextColumn::make('county')->sortable(),
                TextColumn::make('site_zip')->label('ZIP')->placeholder('—'),
                TextColumn::make('prop_class')->label('Class'),
                TextColumn::make('style')->placeholder('—'),
                TextColumn::make('yr_constr')->label('Built')->sortable()->placeholder('—'),
                TextColumn::make('calc_acre')->label('Acres')->numeric(2)->sortable(),
                IconColumn::make('owner_occupied')->label('Owner-occ.')->boolean(),
                TextColumn::make('deed_date')->label('Last sold')->date()->sortable()->toggleable(),
                TextColumn::make('pams_pin')->label('PAMS PIN')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Default view is residential (class 2, single-family); untick to see every class.
                Filter::make('residential')
                    ->label('Residential single-family only')
                    ->default()
                    ->query(fn (Builder $query) => Parcel::applyResidential($query)),
                SelectFilter::make('county')->options(fn () => self::distinct('county'))->searchable(),
                SelectFilter::make('municipality')->options(fn () => self::distinct('municipality'))->searchable(),
                SelectFilter::make('prop_class')->label('Property class')->options(fn () => self::distinct('prop_class')),
                SelectFilter::make('style_code')->label('Style')->options(BldgDescDecoder::STYLES),
                TernaryFilter::make('owner_occupied')->label('Owner-occupied'),
                self::range('yr_constr', 'Year built', 'numeric'),
                self::range('calc_acre', 'Acreage', 'decimal'),
                Filter::make('deed_date')
                    ->label('Last sold')
                    ->schema([DatePicker::make('sold_from'), DatePicker::make('sold_until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['sold_from'] ?? null, fn ($q, $d) => $q->whereDate('deed_date', '>=', $d))
                        ->when($data['sold_until'] ?? null, fn ($q, $d) => $q->whereDate('deed_date', '<=', $d))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    private static function range(string $column, string $label, string $type): Filter
    {
        return Filter::make($column)
            ->label($label)
            ->schema([
                TextInput::make('min')->label("{$label} from")->numeric(),
                TextInput::make('max')->label("{$label} to")->numeric(),
            ])
            ->query(fn (Builder $query, array $data) => $query
                ->when(filled($data['min'] ?? null), fn ($q) => $q->where($column, '>=', $data['min']))
                ->when(filled($data['max'] ?? null), fn ($q) => $q->where($column, '<=', $data['max'])));
    }

    /** @return array<string, string> */
    private static function distinct(string $column): array
    {
        return DB::table((new Parcel)->getTable())->whereNotNull($column)->distinct()->orderBy($column)
            ->pluck($column, $column)->all();
    }
}
