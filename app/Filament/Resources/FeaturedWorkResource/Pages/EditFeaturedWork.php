<?php

namespace App\Filament\Resources\FeaturedWorkResource\Pages;

use App\Filament\Resources\FeaturedWorkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFeaturedWork extends EditRecord
{
    protected static string $resource = FeaturedWorkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
