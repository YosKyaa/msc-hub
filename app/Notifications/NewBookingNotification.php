<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        $typeName = $this->type === 'ROOM' ? 'Ruangan' : 'Inventaris';
        $code = $this->booking->booking_code;
        $requester = $this->booking->requester_name;
        $purpose = $this->booking->purpose;
        $date = $this->booking->start_at->format('d F Y, H:i') . ' - ' . $this->booking->end_at->format('d F Y, H:i');
        
        $url = url('/panel');

        return (new MailMessage)
            ->subject("[MSC Hub] Permintaan Baru #{$code} - {$typeName}")
            ->view('emails.notification', [
                'badge' => 'Tindakan diperlukan',
                'title' => "Ada booking {$typeName} baru",
                'greeting' => 'Halo, Tim MSC',
                'intro' => 'Booking baru telah masuk. Periksa ketersediaan fasilitas dan jadwal sebelum memberikan keputusan.',
                'details' => [
                    'Kode booking' => $code,
                    'Pemohon' => $requester,
                    'Unit' => $this->booking->unit,
                    'Waktu penggunaan' => $date,
                    'Keperluan' => $purpose,
                ],
                'status' => 'Perlu ditinjau',
                'statusTone' => 'warning',
                'note' => 'Berikan persetujuan atau penolakan melalui panel MSC Hub.',
                'actionUrl' => $url,
                'actionText' => 'Tinjau booking',
            ]);
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
