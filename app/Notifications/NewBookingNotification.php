<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

use Filament\Notifications\Notification as FilamentNotification;

class NewBookingNotification extends Notification implements ShouldQueue
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
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $typeName = $this->type === 'ROOM' ? 'Ruangan' : 'Inventaris';
        $code = $this->booking->booking_code;
        $requester = $this->booking->requester_name;
        $purpose = $this->booking->purpose;
        $date = $this->booking->start_at->format('d F Y, H:i') . ' - ' . $this->booking->end_at->format('d F Y, H:i');
        
        $url = url('/panel');

        return (new MailMessage)
            ->subject("[MSC Hub] Permintaan Baru #{$code} - {$typeName}")
            ->greeting("Yth. Tim Administrator MSC Hub,")
            ->line("Sistem telah menerima permintaan peminjaman {$typeName} baru dengan rincian sebagai berikut:")
            ->line(new HtmlString("
                <div style='margin-bottom: 10px;'>
                    <p style='margin: 5px 0;'><strong>Kode Booking:</strong> {$code}</p>
                    <p style='margin: 5px 0;'><strong>Nama Peminjam:</strong> {$requester}</p>
                    <p style='margin: 5px 0;'><strong>Unit/Instansi:</strong> {$this->booking->unit}</p>
                    <p style='margin: 5px 0;'><strong>Waktu Penggunaan:</strong><br>{$date}</p>
                    <p style='margin: 5px 0;'><strong>Keperluan:</strong><br>{$purpose}</p>
                </div>
            "))
            ->action('Tinjau & Verifikasi', $url)
            ->line('Mohon segera dilakukan pengecekan untuk persetujuan atau penolakan permintaan ini.')
            ->salutation('Hormat kami,');
    }

    public function toDatabase(object $notifiable): array
    {
        $typeName = $this->type === 'ROOM' ? 'Ruangan' : 'Alat';
        $code = $this->booking->booking_code;
        
        return FilamentNotification::make()
            ->title("Peminjaman {$typeName} Baru")
            ->body("Kode: {$code}\nPeminjam: {$this->booking->requester_name}")
            ->warning() // or ->success(), ->danger()
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->button()
                    ->url(url('/panel'), shouldOpenInNewTab: true),
            ])
            ->getDatabaseMessage();
    }
}
