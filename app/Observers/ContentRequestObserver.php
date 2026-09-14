<?php

namespace App\Observers;

use App\Models\ContentRequest;
use App\Notifications\ContentRequestSubmitted;
use App\Notifications\ContentRequestStatusUpdated;
use App\Notifications\NewContentRequestNotification;
use App\Observers\Concerns\DispatchesMscNotifications;

class ContentRequestObserver
{
    use DispatchesMscNotifications;

    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the ContentRequest "created" event.
     */
    public function created(ContentRequest $contentRequest): void
    {
        $context = ['content_request_id' => $contentRequest->id];

        $this->notifyRequester(
            $contentRequest->requester_email,
            new ContentRequestSubmitted($contentRequest),
            $context,
        );

        $this->notifyMscTeam(new NewContentRequestNotification($contentRequest), $context);
    }

    /**
     * Handle the ContentRequest "updated" event.
     */
    public function updated(ContentRequest $contentRequest): void
    {
        // Check if status changed
        $notifiableStatuses = ['need_revision', 'approved', 'rejected', 'published'];

        if ($contentRequest->isDirty('status')
            && $contentRequest->requester_email
            && in_array($contentRequest->status->value, $notifiableStatuses, true)) {
            $this->notifyRequester(
                $contentRequest->requester_email,
                new ContentRequestStatusUpdated($contentRequest),
                ['content_request_id' => $contentRequest->id],
            );
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
