<?php

namespace App\Observers;

use App\Models\ContentRequest;
use App\Models\User;
use App\Notifications\ContentRequestStatusUpdated;
use App\Notifications\NewContentRequestNotification;
use Illuminate\Support\Facades\Notification;

class ContentRequestObserver
{
    /**
     * Handle the ContentRequest "created" event.
     */
    public function created(ContentRequest $contentRequest): void
    {
        // Send notification to Staff/Head/Admin
        $recipients = User::role(['admin', 'staff_msc', 'head_msc'])->get();
        
        // Loop and add delay to avoid Mailtrap Rate Limiting
        foreach ($recipients as $index => $recipient) {
            $recipient->notify(
                (new NewContentRequestNotification($contentRequest))
                    ->delay(now()->addSeconds(($index + 1) * 10))
            );
        }
    }

    /**
     * Handle the ContentRequest "updated" event.
     */
    public function updated(ContentRequest $contentRequest): void
    {
        // Check if status changed
        if ($contentRequest->isDirty('status')) {
            // Send notification to requester
            if ($contentRequest->requester_email) {
                Notification::route('mail', $contentRequest->requester_email)
                    ->notify(new ContentRequestStatusUpdated($contentRequest));
            }
        }
    }

    /**
     * Handle the ContentRequest "deleted" event.
     */
    public function deleted(ContentRequest $contentRequest): void
    {
        //
    }

    /**
     * Handle the ContentRequest "restored" event.
     */
    public function restored(ContentRequest $contentRequest): void
    {
        //
    }

    /**
     * Handle the ContentRequest "force deleted" event.
     */
    public function forceDeleted(ContentRequest $contentRequest): void
    {
        //
    }
}
