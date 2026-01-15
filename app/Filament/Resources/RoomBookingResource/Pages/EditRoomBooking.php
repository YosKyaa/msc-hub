<?php

namespace App\Filament\Resources\RoomBookingResource\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\RoomBookingResource;
use App\Models\Room;
use App\Models\RoomBooking;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRoomBooking extends EditRecord
{
    protected static string $resource = RoomBookingResource::class;

    protected array $inventoryItemsData = [];

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Simpan Perubahan')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Update Booking')
                ->modalDescription('Apakah Anda yakin ingin menyimpan perubahan booking ruangan ini?')
                ->modalSubmitActionLabel('Ya, Simpan')
                ->modalCancelActionLabel('Batal')
                ->color('primary')
                ->icon('heroicon-o-check-circle')
                ->action(fn () => $this->save()),
        ];
    }

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Store inventoryItems data before removing it
        $this->inventoryItemsData = $data['inventoryItems'] ?? [];
        
        // Remove inventoryItems from data as it's not a column in room_bookings table
        // We'll handle this relationship manually in afterSave
        unset($data['inventoryItems']);

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

        // Check maximum bookings per month (2x per month per unit) - exclude current record
        $currentMonth = $startAt->format('Y-m');
        $bookingsThisMonth = RoomBooking::where('requester_email', $data['requester_email'])
            ->where('id', '!=', $this->record->id) // Exclude current booking
            ->whereRaw('DATE_FORMAT(start_at, "%Y-%m") = ?', [$currentMonth])
            ->whereIn('status', [
                BookingStatus::PENDING,
                BookingStatus::APPROVED_STAFF,
                BookingStatus::APPROVED_HEAD,
            ])
            ->count();

        if ($bookingsThisMonth >= 2) {
            Notification::make()
                ->title('Batas Booking Tercapai')
                ->body('Anda sudah melakukan 2x booking pada bulan ini. Maksimal booking per bulan adalah 2x.')
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
        try {
            // Handle inventory items relationship manually using stored data
            $inventoryItems = $this->inventoryItemsData;
            
            // Check if inventoryItems exists and is not empty
            if (is_array($inventoryItems) && count($inventoryItems) > 0) {
                // Filter out any empty or invalid items
                $validItems = array_filter($inventoryItems, function ($item) {
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
                    
                    Notification::make()
                        ->title('Booking Berhasil Diupdate')
                        ->body('Booking ruangan dengan ' . count($validItems) . ' peralatan berhasil diupdate.')
                        ->success()
                        ->send();
                } else {
                    // If no valid items, detach all
                    $this->record->inventoryItems()->detach();
                    
                    Notification::make()
                        ->title('Booking Berhasil Diupdate')
                        ->body('Booking ruangan berhasil diupdate. Semua peralatan telah dihapus.')
                        ->success()
                        ->send();
                }
            } else {
                // If inventoryItems is not set or empty, detach all
                $this->record->inventoryItems()->detach();
                
                Notification::make()
                    ->title('Booking Berhasil Diupdate')
                    ->body('Booking ruangan berhasil diupdate tanpa peralatan.')
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Terjadi kesalahan saat menyimpan peralatan: ' . $e->getMessage())
                ->danger()
                ->send();
            
            \Log::error('Error saving room booking items: ' . $e->getMessage(), [
                'booking_id' => $this->record->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
