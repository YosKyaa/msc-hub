<?php

namespace App\Observers;

use App\Models\InventoryBooking;
use App\Notifications\BookingStatusUpdated;
use App\Notifications\BookingSubmitted;
use App\Notifications\NewBookingNotification;
use App\Observers\Concerns\DispatchesMscNotifications;
use Illuminate\Support\Facades\Log;

class InventoryBookingObserver
{
    use DispatchesMscNotifications;

    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the InventoryBooking "created" event.
     */
    public function created(InventoryBooking $inventoryBooking): void
    {
        $this->withoutBreakingTheRequest(function () use ($inventoryBooking) {
            $context = ['inventory_booking_id' => $inventoryBooking->id];

            $this->notifyRequester(
                $inventoryBooking->requester_email,
                new BookingSubmitted($inventoryBooking, 'INVENTORY'),
                $context,
            );

            $this->notifyMscTeam(new NewBookingNotification($inventoryBooking, 'INVENTORY'), $context);
        }, ['inventoryBooking_id' => $inventoryBooking->id]);
    }

    /**
     * Handle the InventoryBooking "updated" event.
     */
    public function updated(InventoryBooking $inventoryBooking): void
    {
        // Check if status changed
        $notifiableStatuses = ['approved_head', 'rejected', 'cancelled', 'checked_out', 'returned'];

        if ($inventoryBooking->isDirty('status')
            && $inventoryBooking->requester_email
            && in_array($inventoryBooking->status->value, $notifiableStatuses, true)) {
            $this->notifyRequester(
                $inventoryBooking->requester_email,
                new BookingStatusUpdated($inventoryBooking, 'INVENTORY'),
                ['inventory_booking_id' => $inventoryBooking->id],
            );
        }
    }

    /**
     * Handle the InventoryBooking "deleted" event.
     */
    public function deleted(InventoryBooking $inventoryBooking): void
    {
        //
    }

    /**
     * Handle the InventoryBooking "restored" event.
     */
    public function restored(InventoryBooking $inventoryBooking): void
    {
        //
    }

    /**
     * Handle the InventoryBooking "force deleted" event.
     */
    public function forceDeleted(InventoryBooking $inventoryBooking): void
    {
        //
    }

    /**
     * Efek samping setelah commit tidak boleh menggagalkan permintaan
     * peminjam: datanya sudah tersimpan, jadi kegagalan mengirim
     * pemberitahuan cukup dicatat.
     */
    private function withoutBreakingTheRequest(callable $work, array $context): void
    {
        try {
            $work();
        } catch (\Throwable $exception) {
            try {
                Log::error('Gagal mengirim pemberitahuan booking.', [
                    ...$context,
                    'exception' => $exception,
                ]);
            } catch (\Throwable) {
                // Menulis log pun bisa gagal (mis. izin folder di server).
                // Diamkan: peminjam tidak boleh menanggung masalah itu.
            }
        }
    }
}
