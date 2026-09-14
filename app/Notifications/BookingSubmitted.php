<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 5;
    public $backoff = [10, 30, 60, 120, 240];

    public function __construct(public object $booking, public string $type) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $typeName = $this->type === 'ROOM' ? 'ruangan' : 'inventaris';
        $booking = $this->booking;

        return (new MailMessage)
            ->subject("[MSC Hub] Booking {$booking->booking_code} Berhasil Diajukan")
            ->view('emails.notification', [
                'badge' => 'Booking berhasil diajukan',
                'title' => 'Booking Anda sedang menunggu peninjauan',
                'greeting' => "Halo, {$booking->requester_name}",
                'intro' => "Tim MSC sudah menerima booking {$typeName} Anda. Mohon tunggu persetujuan sebelum menggunakan fasilitas.",
                'details' => [
                    'Kode booking' => $booking->booking_code,
                    'Layanan' => ucfirst($typeName),
                    'Jadwal mulai' => $booking->start_at->format('d M Y, H:i').' WIB',
                    'Jadwal selesai' => $booking->end_at->format('d M Y, H:i').' WIB',
                    'Keperluan' => $booking->purpose,
                ],
                'status' => 'Menunggu persetujuan',
                'statusTone' => 'warning',
                'note' => 'Kami akan mengirim email kembali ketika booking disetujui, ditolak, atau selesai.',
                'actionUrl' => route('my.bookings.detail', [
                    'type' => strtolower($this->type),
                    'code' => $booking->booking_code,
                ]),
                'actionText' => 'Lihat booking saya',
            ]);
    }
}
