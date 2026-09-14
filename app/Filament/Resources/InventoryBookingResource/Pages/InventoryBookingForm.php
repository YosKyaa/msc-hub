<?php

namespace App\Filament\Resources\InventoryBookingResource\Pages;

use App\Filament\Resources\Concerns\ShowsBorrowingForm;
use App\Filament\Resources\InventoryBookingResource;
use Filament\Resources\Pages\Page;

class InventoryBookingForm extends Page
{
    use ShowsBorrowingForm;

    protected static string $resource = InventoryBookingResource::class;

    // Dideklarasikan di kelas, bukan trait: Filament\Pages\Page sudah
    // mendeklarasikan $view sehingga tabrakan bila datang dari trait.
    protected string $view = 'filament.pages.borrowing-form';
}
