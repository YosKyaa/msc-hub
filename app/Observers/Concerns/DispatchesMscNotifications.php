<?php

namespace App\Observers\Concerns;

use App\Models\User;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

trait DispatchesMscNotifications
{
    protected function notifyMscTeam(LaravelNotification $notification, array $context = []): void
    {
        $notifiedEmails = [];
        $index = 0;

        try {
            $panelRecipients = User::role(['admin', 'staff_msc', 'head_msc'])->get();

            foreach ($panelRecipients as $recipient) {
                $recipient->notify((clone $notification)->delay(now()->addSeconds(++$index * 10)));
                $notifiedEmails[] = strtolower($recipient->email);
            }

            foreach (config('msc.notification_recipients', []) as $email) {
                if (in_array(strtolower($email), $notifiedEmails, true)) {
                    continue;
                }

                Notification::route('mail', $email)
                    ->notify((clone $notification)->delay(now()->addSeconds(++$index * 10)));
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to dispatch MSC operational notification.', [
                ...$context,
                'exception' => $exception,
            ]);
        }
    }

    protected function notifyRequester(string $email, LaravelNotification $notification, array $context = []): void
    {
        try {
            Notification::route('mail', $email)->notify($notification);
        } catch (Throwable $exception) {
            Log::warning('Failed to dispatch requester notification.', [
                ...$context,
                'requester_email' => $email,
                'exception' => $exception,
            ]);
        }
    }
}
