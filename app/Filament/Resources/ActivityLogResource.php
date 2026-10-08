<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\User;
use App\Support\ActivityDescription;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Riwayat Aktivitas: jejak audit, untuk dibaca saja.
 *
 * Catatan audit yang bisa diubah atau dihapus dari panel tidak bisa
 * dipercaya, jadi seluruh aksi pengubah ditutup, termasuk bagi admin.
 * Pembersihan catatan lama dikerjakan penjadwal (activitylog:clean).
 */
class ActivityLogResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Riwayat Aktivitas';

    protected static ?string $modelLabel = 'Aktivitas';

    protected static ?string $pluralModelLabel = 'Riwayat Aktivitas';

    protected static ?int $navigationSort = 90;

    protected static function permissionPrefix(): string
    {
        return 'activity_log';
    }

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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['causer', 'subject']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y, H:i')->sortable(),

                TextColumn::make('causer')
                    ->label('Oleh')
                    ->state(fn (Activity $record) => ActivityDescription::causer($record)),

                TextColumn::make('event')
                    ->label('Aksi')
                    ->badge()
                    ->state(fn (Activity $record) => ActivityDescription::event($record))
                    ->color(fn (Activity $record) => match ($record->event) {
                        'created', 'peran_diberikan', 'izin_diberikan' => 'success',
                        'deleted', 'peran_dicabut', 'izin_dicabut' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('subject')
                    ->label('Data')
                    ->state(fn (Activity $record) => ActivityDescription::subject($record)),

                TextColumn::make('changes')
                    ->label('Perubahan')
                    ->state(fn (Activity $record) => implode(' · ', ActivityDescription::changes($record)))
                    ->wrap()
                    ->limit(160),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Jenis data')
                    ->options(ActivityDescription::subjectOptions()),

                SelectFilter::make('causer_id')
                    ->label('Oleh')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $data['value'])
                        : $query)
                    ->searchable(),
            ])
            ->actions([
                Actions\Action::make('detail')
                    ->iconButton()
                    ->tooltip('Lihat rincian')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Activity $record) => ActivityDescription::event($record).' — '.ActivityDescription::subject($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (Activity $record) => new HtmlString(
                        '<p style="margin-bottom:0.5rem;">Oleh <strong>'.e(ActivityDescription::causer($record)).'</strong>, '
                        .e($record->created_at?->translatedFormat('j F Y, H:i')).'</p>'
                        .'<ul style="padding-inline-start:1.25rem;list-style:disc;">'
                        .collect(ActivityDescription::changes($record))->map(fn (string $baris) => '<li>'.e($baris).'</li>')->implode('')
                        .'</ul>'
                    )),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListActivityLogs::route('/')];
    }
}
