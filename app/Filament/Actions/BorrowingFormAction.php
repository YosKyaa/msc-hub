<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * Buka formulir resmi FM/JGU/L.89 sebagai pratinjau, bukan unduhan langsung.
 *
 * Petugas biasanya ingin memastikan isinya benar sebelum mencetak; mengunduh
 * lebih dulu hanya menumpuk berkas yang salah di folder unduhan.
 */
class BorrowingFormAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'borrowing_form';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Form Peminjaman')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->modalHeading('Form Peminjaman Ruangan / Fasilitas Multimedia JGU')
            ->modalDescription(fn (Model $record) => 'Kode booking '.$record->booking_code)
            ->modalWidth('5xl')
            ->modalContent(fn (Model $record) => view('filament.actions.borrowing-form-preview', [
                'previewUrl' => $this->urlFor($record),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->extraModalFooterActions(fn (Model $record) => [
                Action::make('download')
                    ->label('Unduh PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url($this->urlFor($record, download: true))
                    ->openUrlInNewTab(),
            ]);
    }

    private function urlFor(Model $record, bool $download = false): string
    {
        $route = $record instanceof \App\Models\RoomBooking
            ? 'borrowing-form.room'
            : 'borrowing-form.inventory';

        $parameter = $record instanceof \App\Models\RoomBooking
            ? ['roomBooking' => $record]
            : ['inventoryBooking' => $record];

        return route($route, $download ? [...$parameter, 'unduh' => 1] : $parameter);
    }
}
