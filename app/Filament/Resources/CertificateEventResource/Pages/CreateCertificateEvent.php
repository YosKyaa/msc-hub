<?php
namespace App\Filament\Resources\CertificateEventResource\Pages;
use App\Filament\Resources\CertificateEventResource;
use Filament\Resources\Pages\CreateRecord;
class CreateCertificateEvent extends CreateRecord { protected static string $resource = CertificateEventResource::class; protected function mutateFormDataBeforeCreate(array $data): array { $data['created_by'] = auth()->id(); return $data; } }
