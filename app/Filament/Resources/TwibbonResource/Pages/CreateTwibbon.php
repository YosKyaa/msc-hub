<?php

namespace App\Filament\Resources\TwibbonResource\Pages;

use App\Filament\Resources\TwibbonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTwibbon extends CreateRecord
{
    protected static string $resource = TwibbonResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
