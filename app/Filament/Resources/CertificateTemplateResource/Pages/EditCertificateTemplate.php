<?php
namespace App\Filament\Resources\CertificateTemplateResource\Pages;
use App\Filament\Resources\CertificateTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditCertificateTemplate extends EditRecord { protected static string $resource = CertificateTemplateResource::class; protected function getHeaderActions(): array { return [\Filament\Actions\Action::make('editor')->label('Editor Visual')->icon('heroicon-o-cursor-arrow-rays')->url(fn () => CertificateTemplateResource::getUrl('editor', ['record' => $this->record])), DeleteAction::make()]; } }
