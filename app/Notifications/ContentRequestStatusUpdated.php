<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

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
        $status = $this->contentRequest->status->getLabel();
        
        $rejectReason = '';
        if ($this->contentRequest->status->value === 'rejected' && $this->contentRequest->reject_reason) {
            $rejectReason = "<div style='margin-top: 10px; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;'><strong>Alasan Penolakan:</strong><br>" . $this->contentRequest->reject_reason . "</div>";
        }

        $url = route('request.status.detail', ['request_code' => $code]);

        return (new MailMessage)
            ->subject("[MSC Hub] Update Status Request Konten #{$code}")
            ->greeting("Yth. {$this->contentRequest->requester_name},")
            ->line("Kami informasikan bahwa status permintaan konten Anda telah diperbarui.")
            ->line(new HtmlString("
                <div style='margin-bottom: 15px;'>
                    <p style='margin: 5px 0;'><strong>Kode Request:</strong> {$code}</p>
                    <p style='margin: 5px 0;'><strong>Unit/Instansi:</strong> {$this->contentRequest->unit}</p>
                    <p style='margin: 5px 0;'><strong>Jenis Konten:</strong> {$this->contentRequest->content_type->getLabel()}</p>
                    <div style='margin-top: 10px; padding: 10px; background-color: #e2e3e5; border-radius: 4px;'>
                        <strong>Status Terbaru:</strong> <span style='font-size: 1.1em; font-weight: bold;'>{$status}</span>
                    </div>
                    {$rejectReason}
                </div>
            "))
            ->action('Lihat Detail Request', $url)
            ->line('Terima kasih telah menggunakan layanan MSC Hub.')
            ->salutation('Hormat kami,');
    }
}
