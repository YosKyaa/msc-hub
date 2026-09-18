<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CertificateResource\Pages;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\Issuer;
use App\Support\CertificatePermission;
use App\Support\CertificateStage;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Daftar seluruh sertifikat yang pernah terbit, lintas kegiatan.
 *
 * Sebelumnya sertifikat hanya terlihat dari dalam kegiatannya masing-masing.
 * Ketika seseorang menghubungi dan mengaku belum menerima emailnya, staf
 * harus menebak dulu kegiatan mana yang dimaksud sebelum bisa mencarinya.
 * Halaman ini hanya untuk melihat dan menelusuri — tidak ada yang bisa
 * diubah dari sini, dan penerbitan tetap dikerjakan di halaman kegiatan.
 */
class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string|UnitEnum|null $navigationGroup = 'Sertifikat';

    protected static ?string $navigationLabel = 'Cari Sertifikat';

    protected static ?string $modelLabel = 'Sertifikat';

    protected static ?string $pluralModelLabel = 'Sertifikat Terbit';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'certificate_number';

    public static function canAccess(): bool
    {
        return CertificatePermission::allows('view');
    }

    /** Halaman ini hanya untuk melihat. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Seluruh sertifikat yang pernah terbit. Cari dengan nama, email, atau nomornya, lalu salin tautan verifikasinya untuk dikirim ulang secara manual.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['event.issuer']))
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('recipient_name')
                    ->label('Penerima')
                    ->description(fn (Certificate $record) => $record->recipient_email ?: 'Tanpa email')
                    ->searchable(['recipient_name', 'recipient_email'])
                    ->sortable(),

                TextColumn::make('certificate_number')
                    ->label('Nomor')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Nomor disalin.'),

                TextColumn::make('event.name')
                    ->label('Kegiatan')
                    ->description(fn (Certificate $record) => $record->event?->issuer?->name)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('stage')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Certificate $record) => CertificateStage::ofCertificate($record)->getLabel())
                    ->color(fn (Certificate $record) => CertificateStage::ofCertificate($record)->getColor()),

                TextColumn::make('issued_at')->label('Terbit')->dateTime('d M Y, H:i')->sortable(),

                TextColumn::make('emailed_at')
                    ->label('Dikirim')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum')
                    ->sortable(),

                TextColumn::make('email_error')
                    ->label('Kendala email')
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Tautan lengkap, siap disalin lalu dikirim lewat jalur lain
                // ketika emailnya tidak kunjung sampai.
                TextColumn::make('verification_url')
                    ->label('Tautan verifikasi')
                    ->state(fn (Certificate $record) => $record->verificationUrl())
                    ->copyable()
                    ->copyMessage('Tautan disalin.')
                    ->limit(38)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('stage')
                    ->label('Status')
                    ->options(CertificateStage::options())
                    ->query(fn (Builder $query, array $data) => CertificateStage::tryFrom((string) ($data['value'] ?? ''))
                        ?->constrainCertificates($query) ?? $query),

                SelectFilter::make('certificate_event_id')
                    ->label('Kegiatan')
                    ->options(fn () => CertificateEvent::orderByDesc('id')->pluck('name', 'id')->all())
                    ->searchable(),

                SelectFilter::make('issuer')
                    ->label('Penerbit')
                    ->options(fn () => Issuer::orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('event', fn (Builder $event) => $event->where('issuer_id', $data['value']))
                        : $query),
            ])
            // Hanya aksi yang membuka atau menyalin; tidak ada yang mengubah.
            ->actions([
                Actions\Action::make('verify')
                    ->iconButton()
                    ->tooltip('Buka halaman verifikasi')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (Certificate $record) => $record->verificationUrl())
                    ->openUrlInNewTab(),

                Actions\Action::make('download')
                    ->iconButton()
                    ->tooltip('Unduh PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Certificate $record) => $record->downloadUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Certificate $record) => $record->isValid()),

                Actions\Action::make('openEvent')
                    ->iconButton()
                    ->tooltip('Buka kegiatannya')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Certificate $record) => $record->event
                        ? CertificateEventResource::getUrl('edit', ['record' => $record->event])
                        : null)
                    ->visible(fn (Certificate $record) => $record->event !== null),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCertificates::route('/')];
    }
}
