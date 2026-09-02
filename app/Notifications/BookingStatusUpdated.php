<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        $status = match ($this->booking->status->value) {
            'approved_head' => 'Disetujui final',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'checked_out' => 'Sedang dipinjam',
            'returned' => 'Sudah dikembalikan',
            'completed' => 'Selesai',
            default => $this->booking->status->getLabel(),
        };

        $url = route('my.bookings.detail', ['type' => strtolower($this->type), 'code' => $code]);
        $tone = match ($this->booking->status->value) {
            'approved_head', 'returned', 'completed' => 'success',
            'rejected', 'cancelled' => 'danger',
            default => 'info',
        };

        return (new MailMessage)
            ->subject("[MSC Hub] Update Status Peminjaman #{$code}")
            ->view('emails.notification', [
                'badge' => 'Pembaruan booking',
                'title' => "Status booking {$typeName} Anda berubah",
                'greeting' => "Halo, {$this->booking->requester_name}",
                'intro' => 'Periksa status terbaru dan detail jadwal booking Anda di bawah ini.',
                'details' => [
                    'Kode booking' => $code,
                    'Layanan' => $typeName,
                    'Unit' => $this->booking->unit,
                    'Jadwal' => $this->booking->start_at->format('d M Y, H:i').' WIB',
                ],
                'status' => $status,
                'statusTone' => $tone,
                'note' => $this->booking->reject_reason,
                'actionUrl' => $url,
                'actionText' => 'Lihat detail booking',
            ]);
    }
}
