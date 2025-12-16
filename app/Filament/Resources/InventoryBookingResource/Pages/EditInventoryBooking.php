<?php

namespace App\Filament\Resources\InventoryBookingResource\Pages;

use App\Filament\Resources\InventoryBookingResource;
use App\Models\InventoryBooking;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInventoryBooking extends EditRecord
{
    protected static string $resource = InventoryBookingResource::class;

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

        // Validate operating hours
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

        // Check for overlapping bookings (excluding current)
        $itemIds = $data['items'] ?? [];
        if (!empty($itemIds)) {
            $conflicts = InventoryBooking::checkItemOverlaps($itemIds, $startAt, $endAt, $this->record->id);
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
