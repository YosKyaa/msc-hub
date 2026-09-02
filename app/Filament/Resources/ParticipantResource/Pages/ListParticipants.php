<?php
namespace App\Filament\Resources\ParticipantResource\Pages;
use App\Filament\Resources\ParticipantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListParticipants extends ListRecords { protected static string $resource = ParticipantResource::class; protected function getHeaderActions(): array { return [CreateAction::make()]; } }
