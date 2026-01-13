<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

use Filament\Notifications\Notification as FilamentNotification;

class NewContentRequestNotification extends Notification implements ShouldQueue
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
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $code = $this->contentRequest->request_code;
        $requester = $this->contentRequest->requester_name;
        $contentType = $this->contentRequest->content_type->getLabel();
        $eventDate = $this->contentRequest->event_date ? $this->contentRequest->event_date->format('d F Y') : '-';
        
        $url = url('/panel');

        return (new MailMessage)
            ->subject("[MSC Hub] Request Konten Baru #{$code}")
            ->greeting("Yth. Tim Administrator MSC Hub,")
            ->line("Terdapat permintaan pembuatan konten baru yang memerlukan tinjauan Anda.")
            ->line(new HtmlString("
                <div style='margin-bottom: 10px;'>
                    <p style='margin: 5px 0;'><strong>Kode Request:</strong> {$code}</p>
                    <p style='margin: 5px 0;'><strong>Nama Pemohon:</strong> {$requester}</p>
                    <p style='margin: 5px 0;'><strong>Unit/Instansi:</strong> {$this->contentRequest->unit}</p>
                    <p style='margin: 5px 0;'><strong>Jenis Konten:</strong> {$contentType}</p>
                    <p style='margin: 5px 0;'><strong>Tanggal Acara:</strong> {$eventDate}</p>
                </div>
            "))
            ->action('Tinjau & Verifikasi', $url)
            ->line('Mohon segera dilakukan pengecekan untuk persetujuan atau penolakan permintaan ini.')
            ->salutation('Hormat kami,');
    }

    public function toDatabase(object $notifiable): array
    {
        $code = $this->contentRequest->request_code;
        $contentType = $this->contentRequest->content_type->getLabel();

        return FilamentNotification::make()
            ->title("Request Konten Baru")
            ->body("Kode: {$code}\nJenis: {$contentType}\nPeminjam: {$this->contentRequest->requester_name}")
            ->info()
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->button()
                    ->url(url('/panel'), shouldOpenInNewTab: true),
            ])
            ->getDatabaseMessage();
    }
}
