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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load inventory items from relationship
        $data['inventoryItems'] = $this->record->inventoryItems->map(function ($item) {
            return [
                'inventory_item_id' => $item->id,
                'quantity' => $item->pivot->quantity,
                'notes' => $item->pivot->notes,
            ];
        })->toArray();

        return $data;
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

    protected function afterSave(): void
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
            } else {
                // If no valid items, detach all
                $this->record->inventoryItems()->detach();
            }
        } else {
            // If inventoryItems is not set or empty, detach all
            $this->record->inventoryItems()->detach();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
