<?php
namespace App\Filament\Resources\CertificateEventResource\Pages;
use App\Filament\Resources\CertificateEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditCertificateEvent extends EditRecord { protected static string $resource = CertificateEventResource::class; protected function getHeaderActions(): array { return [DeleteAction::make()]; } }
