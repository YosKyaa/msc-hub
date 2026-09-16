<?php

namespace App\Filament\Resources\IssuerResource\Pages;

use App\Filament\Resources\IssuerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIssuer extends EditRecord
{
    protected static string $resource = IssuerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => ! $this->record->is_house),
        ];
    }
}
