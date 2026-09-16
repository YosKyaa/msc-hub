<?php

namespace App\Filament\Resources\CertificateResource\Pages;

use App\Filament\Resources\CertificateResource;
use Filament\Resources\Pages\ListRecords;

class ListCertificates extends ListRecords
{
    protected static string $resource = CertificateResource::class;

    public function getSubheading(): ?string
    {
        return 'Halaman ini hanya untuk melihat. Penerbitan dan pengiriman ulang dikerjakan dari halaman kegiatannya.';
    }

    /** Tidak ada yang bisa dibuat dari sini. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
