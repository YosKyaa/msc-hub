<?php

namespace App\Filament\Resources\ContentRequestResource\Pages;

use App\Filament\Resources\ContentRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContentRequest extends EditRecord
{
    protected static string $resource = ContentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()->hasRole('admin')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
