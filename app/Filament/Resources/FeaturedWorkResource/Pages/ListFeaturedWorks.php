<?php

namespace App\Filament\Resources\FeaturedWorkResource\Pages;

use App\Filament\Resources\FeaturedWorkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeaturedWorks extends ListRecords
{
    protected static string $resource = FeaturedWorkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
