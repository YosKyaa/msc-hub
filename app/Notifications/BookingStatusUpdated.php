<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class BookingStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public $booking;
    public string $type; // 'ROOM' or 'INVENTORY'

    public $tries = 5;
    public $backoff = [10, 30, 60, 120, 240];

    /**
     * Create a new notification instance.
     */
    public function __construct($booking, string $type)
    {
        $this->booking = $booking;
        $this->type = $type;
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
        $typeName = $this->type === 'ROOM' ? 'Ruangan' : 'Inventaris';
        $code = $this->booking->booking_code;
        $status = $this->booking->status->getLabel();

        $rejectReason = '';
        if ($this->booking->status->value === 'rejected' && $this->booking->reject_reason) {
            $rejectReason = "<div style='margin-top: 10px; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;'><strong>Alasan Penolakan:</strong><br>" . $this->booking->reject_reason . "</div>";
        }

        $url = route('booking.success', ['type' => strtolower($this->type), 'code' => $code]);

        return (new MailMessage)
            ->subject("[MSC Hub] Update Status Peminjaman #{$code}")
            ->greeting("Yth. {$this->booking->requester_name},")
            ->line("Kami informasikan bahwa status permohonan peminjaman {$typeName} Anda telah diperbarui.")
            ->line(new HtmlString("
                <div style='margin-bottom: 15px;'>
                    <p style='margin: 5px 0;'><strong>Kode Booking:</strong> {$code}</p>
                    <p style='margin: 5px 0;'><strong>Unit/Instansi:</strong> {$this->booking->unit}</p>
                    <p style='margin: 5px 0;'><strong>Waktu Penggunaan:</strong> {$this->booking->start_at->format('d F Y, H:i')}</p>
                    <div style='margin-top: 10px; padding: 10px; background-color: #e2e3e5; border-radius: 4px;'>
                        <strong>Status Terbaru:</strong> <span style='font-size: 1.1em; font-weight: bold;'>{$status}</span>
                    </div>
                    {$rejectReason}
                </div>
            "))
            ->action('Lihat Detail Peminjaman', $url)
            ->line('Terima kasih telah menggunakan layanan MSC Hub.')
            ->salutation('Hormat kami,');
    }
}

