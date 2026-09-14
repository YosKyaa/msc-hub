<?php

namespace App\Filament\Resources\RoomBookingResource\Pages;

use App\Filament\Resources\Concerns\ShowsBorrowingForm;
use App\Filament\Resources\RoomBookingResource;
use Filament\Resources\Pages\Page;

class RoomBookingForm extends Page
{
    use ShowsBorrowingForm;

    protected static string $resource = RoomBookingResource::class;

    // Dideklarasikan di kelas, bukan trait: Filament\Pages\Page sudah
    // mendeklarasikan $view sehingga tabrakan bila datang dari trait.
    protected string $view = 'filament.pages.borrowing-form';
}
