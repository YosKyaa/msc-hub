<?php

namespace App\Filament\Resources\Concerns;

use App\Models\RoomBooking;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

/**
 * Halaman pratinjau formulir resmi FM/JGU/L.89.
 *
 * Dokumen ditampilkan sehalaman penuh, bukan di dalam modal, karena lembar A4
 * yang diperkecil ke dalam kotak dialog terlalu kecil untuk diperiksa.
 */
trait ShowsBorrowingForm
{
    use InteractsWithRecord;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        static::authorizeResourceAccess();
    }

    public function getTitle(): string
    {
        return 'Form Peminjaman Ruangan / Fasilitas Multimedia JGU';
    }

    public function getSubheading(): ?string
    {
        return 'Kode booking '.$this->record->booking_code;
    }

    public function getBreadcrumb(): string
    {
        return 'Form Peminjaman';
    }

    /**
     * Ditampilkan dengan lebar pas kertas supaya isinya langsung terbaca.
     */
    public function getPreviewUrl(): string
    {
        return $this->formUrl().'#view=FitH&toolbar=1';
    }

    public function getDownloadUrl(): string
    {
        return $this->formUrl(download: true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Unduh PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url($this->getDownloadUrl())
                ->openUrlInNewTab(),

            Action::make('back')
                ->label('Kembali ke Booking')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
        ];
    }

    private function formUrl(bool $download = false): string
    {
        $isRoom = $this->record instanceof RoomBooking;

        $parameters = $isRoom
            ? ['roomBooking' => $this->record]
            : ['inventoryBooking' => $this->record];

        return route(
            $isRoom ? 'borrowing-form.room' : 'borrowing-form.inventory',
            $download ? [...$parameters, 'unduh' => 1] : $parameters,
        );
    }
}
