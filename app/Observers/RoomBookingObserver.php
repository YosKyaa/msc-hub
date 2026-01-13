<?php

namespace App\Observers;

use App\Models\RoomBooking;
use App\Models\User;
use App\Notifications\BookingStatusUpdated;
use App\Notifications\NewBookingNotification;
use Illuminate\Support\Facades\Notification;

class RoomBookingObserver
{
    /**
     * Handle the RoomBooking "created" event.
     */
    public function created(RoomBooking $roomBooking): void
    {
        // Send notification to Staff/Head/Admin
        $recipients = User::role(['admin', 'staff_msc', 'head_msc'])->get();
        
        // Loop and add delay to avoid Mailtrap Rate Limiting (Too many emails per second)
        foreach ($recipients as $index => $recipient) {
            $recipient->notify(
                (new NewBookingNotification($roomBooking, 'ROOM'))
                    ->delay(now()->addSeconds(($index + 1) * 10)) // Increased delay to 10s
            );
        }
    }

    /**
     * Handle the RoomBooking "updated" event.
     */
    public function updated(RoomBooking $roomBooking): void
    {
        // Check if status changed
        if ($roomBooking->isDirty('status')) {
            // Send notification to requester
            if ($roomBooking->requester_email) {
                Notification::route('mail', $roomBooking->requester_email)
                    ->notify(new BookingStatusUpdated($roomBooking, 'ROOM'));
            }
        }
    }

    /**
     * Handle the RoomBooking "deleted" event.
     */
    public function deleted(RoomBooking $roomBooking): void
    {
        //
    }

    /**
     * Handle the RoomBooking "restored" event.
     */
    public function restored(RoomBooking $roomBooking): void
    {
        //
    }

    /**
     * Handle the RoomBooking "force deleted" event.
     */
    public function forceDeleted(RoomBooking $roomBooking): void
    {
        //
    }
}
