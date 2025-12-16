<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

class Dokumentasi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected string $view = 'filament.pages.dokumentasi';

    protected static ?string $title = 'Dokumentasi';

    protected static ?string $navigationLabel = 'Dokumentasi';

    protected static ?int $navigationSort = 100;
}
