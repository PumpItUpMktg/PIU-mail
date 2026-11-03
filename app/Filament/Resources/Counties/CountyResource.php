<?php

namespace App\Filament\Resources\Counties;

use App\Enums\CountyStatus;
use App\Filament\Concerns\SuperAdminOnly;
use App\Filament\Resources\Counties\Pages\ListCounties;
use App\Models\County;
use App\Parcels\ParcelImporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** The 21-county import registry, with per-county Refresh / Retry failed. */
class CountyResource extends Resource
{
    use SuperAdminOnly;

    protected static ?string $model = County::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAmericas;

    protected static string|UnitEnum|null $navigationGroup = 'NJ parcel data';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('fips')->label('FIPS'),
                TextColumn::make('status')->badge()->color(fn (CountyStatus $state) => match ($state) {
                    CountyStatus::Loaded => 'success',
                    CountyStatus::Failed => 'danger',
                    CountyStatus::Queued, CountyStatus::Loading => 'warning',
                    CountyStatus::NotLoaded => 'gray',
                }),
                TextColumn::make('row_count')->label('Rows')->numeric()->sortable(),
                TextColumn::make('source_pub_date')->label('Source pub. date')->date()->placeholder('—'),
                TextColumn::make('loaded_at')->dateTime()->placeholder('—'),
                TextColumn::make('last_error')->limit(60)->tooltip(fn (County $r) => $r->last_error)->placeholder('—')->toggleable(),
            ])
            ->recordActions([
                Action::make('refresh')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalDescription('Queue a fresh import of this county. Existing rows are updated in place.')
                    ->disabled(fn (County $r) => in_array($r->status, [CountyStatus::Queued, CountyStatus::Loading], true))
                    ->action(fn (County $r) => self::queue($r, 'manual')),
                Action::make('retry')
                    ->label('Retry failed')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('danger')
                    ->visible(fn (County $r) => $r->status === CountyStatus::Failed)
                    ->action(fn (County $r) => self::queue($r, 'retry')),
            ]);
    }

    private static function queue(County $county, string $trigger): void
    {
        app(ParcelImporter::class)->start($county, $trigger);

        Notification::make()->title("{$county->name} import queued")->success()->send();
    }

    public static function getPages(): array
    {
        return ['index' => ListCounties::route('/')];
    }
}
