<?php

namespace App\Filament\Resources\InventoryBookingResource\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\InventoryBookingResource;
use App\Models\InventoryBooking;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryBooking extends CreateRecord
{
    protected static string $resource = InventoryBookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['booking_code'] = InventoryBooking::generateBookingCode();
        $data['status'] = BookingStatus::PENDING;

        return $data;
    }

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();

        // Validate operating hours (08:00 - 16:00)
        $startAt = new \DateTime($data['start_at']);
        $endAt = new \DateTime($data['end_at']);

        $errors = InventoryBooking::validateOperatingHours($startAt, $endAt);
        if (!empty($errors)) {
            Notification::make()
                ->title('Validasi Gagal')
                ->body(implode("\n", $errors))
                ->danger()
                ->send();

            $this->halt();
        }

        // Check for overlapping bookings
        $itemIds = $data['items'] ?? [];
        if (!empty($itemIds)) {
            $conflicts = InventoryBooking::checkItemOverlaps($itemIds, $startAt, $endAt);
            if (!empty($conflicts)) {
                Notification::make()
                    ->title('Item Tidak Tersedia')
                    ->body('Item berikut sudah dibooking pada waktu yang sama: ' . implode(', ', $conflicts))
                    ->danger()
                    ->send();

                $this->halt();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
