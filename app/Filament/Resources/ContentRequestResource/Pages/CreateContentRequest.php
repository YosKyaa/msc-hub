<?php

namespace App\Filament\Resources\ContentRequestResource\Pages;

use App\Enums\RequestStatus;
use App\Filament\Resources\ContentRequestResource;
use App\Models\ContentRequest;
use Filament\Resources\Pages\CreateRecord;

class CreateContentRequest extends CreateRecord
{
    protected static string $resource = ContentRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['request_code'] = ContentRequest::generateRequestCode();
        $data['status'] = RequestStatus::INCOMING;
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
