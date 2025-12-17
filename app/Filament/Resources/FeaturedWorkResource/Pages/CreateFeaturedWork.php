<?php

namespace App\Filament\Resources\FeaturedWorkResource\Pages;

use App\Filament\Resources\FeaturedWorkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeaturedWork extends CreateRecord
{
    protected static string $resource = FeaturedWorkResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
