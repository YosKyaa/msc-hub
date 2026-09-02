<?php
namespace App\Filament\Resources\CertificateEventResource\Pages;
use App\Filament\Resources\CertificateEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListCertificateEvents extends ListRecords { protected static string $resource = CertificateEventResource::class; protected function getHeaderActions(): array { return [CreateAction::make()]; } }
