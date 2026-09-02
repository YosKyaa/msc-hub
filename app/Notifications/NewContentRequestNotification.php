<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $channels = ['database'];
        $operationalEmails = array_map('strtolower', config('msc.notification_recipients', []));

        if (in_array(strtolower($notifiable->email), $operationalEmails, true)) {
            $channels[] = 'mail';
        }

        return $channels;
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
            ->view('emails.notification', [
                'badge' => 'Tindakan diperlukan',
                'title' => 'Ada request konten baru',
                'greeting' => 'Halo, Tim MSC',
                'intro' => 'Request baru telah masuk dan menunggu peninjauan. Periksa kelengkapan brief serta deadline sebelum menentukan tindak lanjut.',
                'details' => [
                    'Kode request' => $code,
                    'Pemohon' => $requester,
                    'Unit' => $this->contentRequest->unit,
                    'Jenis konten' => $contentType,
                    'Tanggal acara' => $eventDate,
                    'Deadline' => $this->contentRequest->deadline->format('d M Y'),
                ],
                'status' => 'Perlu ditinjau',
                'statusTone' => 'warning',
                'note' => 'Tentukan PIC atau tindak lanjut melalui panel MSC Hub.',
                'actionUrl' => $url,
                'actionText' => 'Buka panel MSC',
            ]);
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
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url(url('/panel'), shouldOpenInNewTab: true),
            ])
            ->getDatabaseMessage();
    }
}
