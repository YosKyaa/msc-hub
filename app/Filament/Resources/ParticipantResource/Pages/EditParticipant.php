<?php
namespace App\Filament\Resources\ParticipantResource\Pages;
use App\Filament\Resources\ParticipantResource;
use App\Models\Participant;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditParticipant extends EditRecord { protected static string $resource = ParticipantResource::class; protected function getHeaderActions(): array { return [DeleteAction::make()->modalDescription(ParticipantResource::DELETION_NOTE)->visible(fn (Participant $record) => $record->isDeletable())]; } }
