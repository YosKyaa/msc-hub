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

    protected array $inventoryItemsData = [];

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('create')
                ->label('Submit Booking')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Booking Ruangan')
                ->modalDescription('Apakah Anda yakin ingin submit booking ruangan ini? Pastikan semua data sudah benar.')
                ->modalSubmitActionLabel('Ya, Submit')
                ->modalCancelActionLabel('Batal')
                ->color('primary')
                ->icon('heroicon-o-check-circle')
                ->action(fn () => $this->create()),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['booking_code'] = RoomBooking::generateBookingCode();
        $data['status'] = BookingStatus::PENDING;

        // Store inventoryItems data before removing it
        $this->inventoryItemsData = $data['inventoryItems'] ?? [];
        
        // Remove inventoryItems from data as it's not a column in room_bookings table
        // We'll handle this relationship manually in afterCreate
        unset($data['inventoryItems']);

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

        // Check maximum bookings per month (2x per month per unit)
        $currentMonth = $startAt->format('Y-m');
        $bookingsThisMonth = RoomBooking::where('requester_email', $data['requester_email'])
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
                        ->title('Booking Berhasil Dibuat')
                        ->body('Booking ruangan dengan ' . count($validItems) . ' peralatan berhasil dibuat.')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Booking Berhasil Dibuat')
                        ->body('Booking ruangan berhasil dibuat tanpa peralatan.')
                        ->success()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('Booking Berhasil Dibuat')
                    ->body('Booking ruangan berhasil dibuat tanpa peralatan.')
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
