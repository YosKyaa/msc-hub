<?php

namespace App\Filament\Resources\TwibbonResource\Pages;

use App\Filament\Resources\TwibbonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTwibbons extends ListRecords
{
    protected static string $resource = TwibbonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Twibbon')];
    }
}
