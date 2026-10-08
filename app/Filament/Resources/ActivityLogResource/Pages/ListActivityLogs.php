<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    public function getSubheading(): ?string
    {
        return 'Siapa mengubah apa, kapan, dan dari nilai apa menjadi apa. Hanya untuk dibaca: catatan ini tidak bisa diubah atau dihapus dari panel.';
    }
}
