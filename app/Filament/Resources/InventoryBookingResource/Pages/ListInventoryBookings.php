<?php

namespace App\Filament\Resources\InventoryBookingResource\Pages;

use App\Filament\Resources\InventoryBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventoryBookings extends ListRecords
{
    protected static string $resource = InventoryBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
