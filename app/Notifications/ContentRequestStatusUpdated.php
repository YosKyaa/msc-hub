<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContentRequestStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public $contentRequest;

    public $tries = 5;
    public $backoff = [10, 30, 60, 120, 240];

    /**
     * Create a new notification instance.
     */
    public function __construct($contentRequest)
    {
        $this->contentRequest = $contentRequest;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $code = $this->contentRequest->request_code;
        $status = match ($this->contentRequest->status->value) {
            'need_revision' => 'Perlu revisi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'published' => 'Selesai dan dipublikasikan',
            default => $this->contentRequest->status->getLabel(),
        };
        
        $url = route('request.status.detail', ['request_code' => $code]);
        $tone = match ($this->contentRequest->status->value) {
            'approved', 'published' => 'success',
            'rejected', 'need_revision' => 'danger',
            default => 'info',
        };

        return (new MailMessage)
            ->subject("[MSC Hub] Update Status Request Konten #{$code}")
            ->view('emails.notification', [
                'badge' => 'Pembaruan request',
                'title' => 'Status request konten Anda berubah',
                'greeting' => "Halo, {$this->contentRequest->requester_name}",
                'intro' => 'Berikut pembaruan terbaru dari tim MSC untuk request konten Anda.',
                'details' => [
                    'Kode request' => $code,
                    'Jenis konten' => $this->contentRequest->content_type->getLabel(),
                    'Unit' => $this->contentRequest->unit,
                ],
                'status' => $status,
                'statusTone' => $tone,
                'note' => $this->contentRequest->reject_reason,
                'actionUrl' => $url,
                'actionText' => 'Lihat detail request',
            ]);
    }
}
