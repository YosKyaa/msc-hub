<?php

namespace App\Observers;

use App\Models\RoomBooking;
use App\Notifications\BookingStatusUpdated;
use App\Notifications\BookingSubmitted;
use App\Notifications\NewBookingNotification;
use App\Observers\Concerns\DispatchesMscNotifications;
use Illuminate\Support\Facades\Log;

class RoomBookingObserver
{
    use DispatchesMscNotifications;

    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the RoomBooking "created" event.
     */
    public function created(RoomBooking $roomBooking): void
    {
        $this->withoutBreakingTheRequest(function () use ($roomBooking) {
            $context = ['room_booking_id' => $roomBooking->id];

            $this->notifyRequester(
                $roomBooking->requester_email,
                new BookingSubmitted($roomBooking, 'ROOM'),
                $context,
            );

            $this->notifyMscTeam(new NewBookingNotification($roomBooking, 'ROOM'), $context);
        }, ['roomBooking_id' => $roomBooking->id]);
    }

    /**
     * Handle the RoomBooking "updated" event.
     */
    public function updated(RoomBooking $roomBooking): void
    {
        // Check if status changed
        $notifiableStatuses = ['approved_head', 'rejected', 'cancelled', 'completed'];

        if ($roomBooking->isDirty('status')
            && $roomBooking->requester_email
            && in_array($roomBooking->status->value, $notifiableStatuses, true)) {
            $this->notifyRequester(
                $roomBooking->requester_email,
                new BookingStatusUpdated($roomBooking, 'ROOM'),
                ['room_booking_id' => $roomBooking->id],
            );
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
