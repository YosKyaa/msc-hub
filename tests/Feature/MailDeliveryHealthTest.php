<?php

namespace Tests\Feature;

use App\Support\MailHealth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Email yang dicatat terkirim tetapi tidak pernah sampai.
 *
 * Laravel menganggap pengiriman berhasil selama pengantarnya tidak melempar
 * galat, dan pengantar `log` maupun `array` tidak pernah melempar apa pun:
 * keduanya menerima surat lalu membuangnya. Sistem mencatat "terkirim", panel
 * menampilkan "Email terkirim", dan penerimanya tidak menerima apa-apa.
 *
 * Antrean tanpa pekerja menghasilkan kebingungan yang sama dari arah lain:
 * pekerjaannya menumpuk tanpa pernah mengeluh.
 */
class MailDeliveryHealthTest extends TestCase
{
    use RefreshDatabase;

    // ────────────────────────────────────────── pengantar yang membuang surat

    public function test_a_throwaway_mailer_is_called_out(): void
    {
        foreach (['log', 'array', 'null'] as $pengantar) {
            config(['mail.default' => $pengantar]);

            $this->assertFalse(MailHealth::delivers(), "Pengantar {$pengantar} dianggap mengirim.");
            $this->assertStringContainsString('MAIL_MAILER', (string) MailHealth::warning());
        }
    }

    public function test_a_real_mailer_raises_nothing(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'msc@jgu.ac.id',
        ]);

        $this->assertTrue(MailHealth::delivers());
        $this->assertNull(MailHealth::warning());
    }

    /**
     * Penyedia SMTP menolak surat dari domain yang tidak dikenalnya, dan
     * penolakannya sering baru terlihat berjam-jam kemudian.
     */
    public function test_a_placeholder_sender_address_is_called_out(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'hello@example.com',
        ]);

        $this->assertStringContainsString('MAIL_FROM_ADDRESS', (string) MailHealth::warning());
    }

    public function test_an_empty_sender_address_is_called_out(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => '']);

        $this->assertStringContainsString('MAIL_FROM_ADDRESS', (string) MailHealth::warning());
    }

    // ──────────────────────────────────────────────────────────── penjadwal

    /**
     * Akar sebab yang paling sering: cron memanggil `schedule:run` tiap menit,
     * tetapi tidak ada satu pun tugas terjadwal, sehingga antreannya tidak
     * pernah tersentuh. Tidak ada galat, tidak ada keluhan, dan tidak ada
     * email.
     */
    public function test_the_queue_is_actually_worked_by_the_scheduler(): void
    {
        $perintah = collect(app(Schedule::class)->events())
            ->map(fn ($e) => $e->command ?? '')
            ->implode("\n");

        $this->assertStringContainsString('queue:work', $perintah,
            'Tidak ada tugas terjadwal yang mengerjakan antrean. Cron yang memanggil '
            .'schedule:run tiap menit karena itu tidak mengirim email apa pun.');
    }

    /**
     * Pekerja yang tidak berhenti sendiri akan ditumpuki pekerja berikutnya
     * tiap menit sampai servernya kehabisan proses.
     */
    public function test_the_scheduled_worker_stops_on_its_own(): void
    {
        $tugas = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'queue:work'));

        $this->assertNotNull($tugas);
        $this->assertStringContainsString('--stop-when-empty', $tugas->command);
        $this->assertStringContainsString('--max-time', $tugas->command);
        $this->assertNotNull($tugas->mutex, 'Pekerja terjadwal tidak dijaga withoutOverlapping.');
    }
}
