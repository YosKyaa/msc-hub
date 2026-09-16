<?php
namespace App\Filament\Resources\ParticipantResource\Pages;
use App\Filament\Resources\ParticipantResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditParticipant extends EditRecord { protected static string $resource = ParticipantResource::class; protected function getHeaderActions(): array { return [DeleteAction::make()]; } }
