<?php

namespace App\Filament\Resources\IssuerResource\Pages;

use App\Filament\Resources\IssuerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIssuers extends ListRecords
{
    protected static string $resource = IssuerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Penerbit')];
    }
}
