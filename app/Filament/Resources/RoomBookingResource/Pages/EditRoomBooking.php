<?php

namespace App\Filament\Resources\RoomBookingResource\Pages;

use App\Filament\Resources\RoomBookingResource;
use App\Models\Room;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRoomBooking extends EditRecord
{
    protected static string $resource = RoomBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()->hasRole('admin')),
        ];
    }

    protected function beforeSave(): void
    {
        $data = $this->form->getState();

        $room = Room::find($data['room_id']);
        if (!$room) {
            Notification::make()
                ->title('Error')
                ->body('Ruangan tidak ditemukan')
                ->danger()
                ->send();
            $this->halt();
        }

        // Validate operating hours
        $startAt = new \DateTime($data['start_at']);
        $endAt = new \DateTime($data['end_at']);

        $errors = $room->validateOperatingHours($startAt, $endAt);
        if (!empty($errors)) {
            Notification::make()
                ->title('Validasi Gagal')
                ->body(implode("\n", $errors))
                ->danger()
                ->send();
            $this->halt();
        }

        // Check for overlapping bookings (exclude current)
        if ($room->hasOverlappingBookings($startAt, $endAt, $this->record->id)) {
            Notification::make()
                ->title('Ruangan Tidak Tersedia')
                ->body('Ruangan sudah dibooking pada waktu yang sama. Silakan pilih waktu lain.')
                ->danger()
                ->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
