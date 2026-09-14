<?php

namespace Tests\Feature;

use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * Regresi: Mailer selalu menimpa variabel view bernama `message` dengan objek
 * Illuminate\Mail\Message, sehingga template harus memakai nama lain (`note`).
 */
class NotificationTemplateTest extends TestCase
{
    public function test_the_shared_email_template_renders_its_note_and_both_actions(): void
    {
        $rendered = (new MailMessage)
            ->view('emails.notification', [
                'badge' => 'Uji',
                'title' => 'Judul percobaan',
                'greeting' => 'Halo, Budi',
                'intro' => 'Ini adalah surel percobaan.',
                'details' => ['Kode' => 'ABC-123'],
                'note' => 'Catatan tambahan yang harus tampil.',
                'actionUrl' => 'https://msc.test/utama',
                'actionText' => 'Tombol utama',
                'secondaryActionUrl' => 'https://msc.test/kedua',
                'secondaryActionText' => 'Tombol kedua',
            ])
            ->render();

        $this->assertStringContainsString('Catatan tambahan yang harus tampil.', $rendered);
        $this->assertStringContainsString('https://msc.test/utama', $rendered);
        $this->assertStringContainsString('https://msc.test/kedua', $rendered);
        $this->assertStringContainsString('ABC-123', $rendered);
    }

    public function test_the_template_still_renders_without_the_optional_pieces(): void
    {
        $rendered = (new MailMessage)
            ->view('emails.notification', [
                'title' => 'Judul percobaan',
                'greeting' => 'Halo, Budi',
                'intro' => 'Ini adalah surel percobaan.',
                'details' => ['Kode' => 'ABC-123'],
                'actionUrl' => 'https://msc.test/utama',
                'actionText' => 'Tombol utama',
            ])
            ->render();

        $this->assertStringContainsString('Tombol utama', $rendered);
        $this->assertStringNotContainsString('Lihat detail', $rendered);
    }
}
