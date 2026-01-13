<?php

namespace App\Observers;

use App\Models\InventoryBooking;
use App\Models\User;
use App\Notifications\BookingStatusUpdated;
use App\Notifications\NewBookingNotification;
use Illuminate\Support\Facades\Notification;

class InventoryBookingObserver
{
    /**
     * Handle the InventoryBooking "created" event.
     */
    public function created(InventoryBooking $inventoryBooking): void
    {
        // Send notification to Staff/Head/Admin
        $recipients = User::role(['admin', 'staff_msc', 'head_msc'])->get();
        
        // Loop and add delay to avoid Mailtrap Rate Limiting
        foreach ($recipients as $index => $recipient) {
            $recipient->notify(
                (new NewBookingNotification($inventoryBooking, 'INVENTORY'))
                    ->delay(now()->addSeconds(($index + 1) * 10))
            );
        }
    }

    /**
     * Handle the InventoryBooking "updated" event.
     */
    public function updated(InventoryBooking $inventoryBooking): void
    {
        // Check if status changed
        if ($inventoryBooking->isDirty('status')) {
            // Send notification to requester
            if ($inventoryBooking->requester_email) {
                Notification::route('mail', $inventoryBooking->requester_email)
                    ->notify(new BookingStatusUpdated($inventoryBooking, 'INVENTORY'));
            }
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
}
