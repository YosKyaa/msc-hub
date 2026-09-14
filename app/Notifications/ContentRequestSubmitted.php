<?php

namespace App\Notifications;

use App\Models\ContentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContentRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 5;
    public $backoff = [10, 30, 60, 120, 240];

    public function __construct(public ContentRequest $contentRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->contentRequest;

        return (new MailMessage)
            ->subject("[MSC Hub] Request Konten {$request->request_code} Berhasil Dikirim")
            ->view('emails.notification', [
                'badge' => 'Request berhasil dikirim',
                'title' => 'Request konten Anda sudah kami terima',
                'greeting' => "Halo, {$request->requester_name}",
                'intro' => 'Tim MSC akan meninjau detail pengajuan Anda. Kami akan mengirim pembaruan ketika ada tindakan yang perlu Anda ketahui.',
                'details' => [
                    'Kode request' => $request->request_code,
                    'Jenis konten' => $request->content_type->getLabel(),
                    'Deadline' => $request->deadline->format('d M Y'),
                    'Unit' => $request->unit,
                ],
                'status' => 'Menunggu peninjauan',
                'statusTone' => 'warning',
                'note' => 'Simpan kode request untuk memantau progres pengajuan Anda.',
                'actionUrl' => route('request.status.detail', ['request_code' => $request->request_code]),
                'actionText' => 'Lihat request saya',
            ]);
    }
}
