<?php

namespace App\Filament\Actions;

use App\Models\RoomBooking;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * Buka formulir resmi FM/JGU/L.89 pada halamannya sendiri.
 *
 * Dokumennya lembar A4 penuh; ditampilkan di dalam modal ukurannya menyusut
 * sampai tidak terbaca, jadi aksi ini menuju halaman tersendiri yang memuat
 * pratinjau besar beserta tombol unduh.
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
            ->url(fn (Model $record) => $this->pageUrl($record));
    }

    private function pageUrl(Model $record): string
    {
        $resource = $record instanceof RoomBooking
            ? \App\Filament\Resources\RoomBookingResource::class
            : \App\Filament\Resources\InventoryBookingResource::class;

        return $resource::getUrl('form', ['record' => $record]);
    }
}
