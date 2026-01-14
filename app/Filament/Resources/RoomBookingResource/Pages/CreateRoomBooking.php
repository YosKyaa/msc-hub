<?php

namespace App\Filament\Resources\RoomBookingResource\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\RoomBookingResource;
use App\Models\Room;
use App\Models\RoomBooking;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRoomBooking extends CreateRecord
{
    protected static string $resource = RoomBookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['booking_code'] = RoomBooking::generateBookingCode();
        $data['status'] = BookingStatus::PENDING;

        return $data;
    }

    protected function beforeCreate(): void
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

        // Check for overlapping bookings
        if ($room->hasOverlappingBookings($startAt, $endAt)) {
            Notification::make()
                ->title('Ruangan Tidak Tersedia')
                ->body('Ruangan sudah dibooking pada waktu yang sama. Silakan pilih waktu lain.')
                ->danger()
                ->send();
            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        // Handle inventory items relationship manually
        $data = $this->form->getState();
        
        // Check if inventoryItems exists and is not empty
        if (isset($data['inventoryItems']) && is_array($data['inventoryItems']) && count($data['inventoryItems']) > 0) {
            // Filter out any empty or invalid items
            $validItems = array_filter($data['inventoryItems'], function ($item) {
                return isset($item['inventory_item_id']) && !empty($item['inventory_item_id']);
            });
            
            // Only sync if there are valid items
            if (count($validItems) > 0) {
                $syncData = [];
                foreach ($validItems as $item) {
                    $syncData[$item['inventory_item_id']] = [
                        'quantity' => $item['quantity'] ?? 1,
                        'notes' => $item['notes'] ?? null,
                    ];
                }
                
                $this->record->inventoryItems()->sync($syncData);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
