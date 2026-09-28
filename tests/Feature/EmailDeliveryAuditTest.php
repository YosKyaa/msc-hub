<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\ContentRequest;
use App\Models\InventoryBooking;
use App\Models\RoomBooking;
use App\Notifications\BookingStatusUpdated;
use App\Notifications\BookingSubmitted;
use App\Notifications\CertificateIssued;
use App\Notifications\ContentRequestStatusUpdated;
use App\Notifications\ContentRequestSubmitted;
use App\Notifications\NewBookingNotification;
use App\Notifications\NewContentRequestNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Setiap email yang dikirim sistem ini benar-benar bisa dibentuk.
 *
 * Ketujuh notifikasi berbagi satu templat, dan templat itu memakai tujuh
 * variabel tanpa penjaga `??`. Notifikasi yang lupa mengisi salah satunya
 * tetap lolos saat ditulis — kegagalannya baru muncul di dalam antrean, di
 * mana ia hanya menjadi satu baris di failed_jobs yang tidak dibaca siapa pun,
 * dan peminjam yang menunggu kabar tidak pernah menerima apa-apa.
 */
class EmailDeliveryAuditTest extends TestCase
{
    use RefreshDatabase;

    private function roomBooking(): RoomBooking
    {
        return RoomBooking::factory()->create(['status' => BookingStatus::APPROVED_HEAD]);
    }

    private function inventoryBooking(): InventoryBooking
    {
        return InventoryBooking::factory()->create(['status' => BookingStatus::REJECTED]);
    }

    private function contentRequest(): ContentRequest
    {
        return ContentRequest::factory()->create(['status' => 'approved']);
    }

    private function certificate(): Certificate
    {
        $event = CertificateEvent::factory()->published()->create();

        return Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_email' => 'peserta@student.jgu.ac.id',
        ]);
    }

    /**
     * Semua notifikasi beserta satu contoh yang masuk akal.
     *
     * @return array<string, LaravelNotification>
     */
    private function everyNotification(): array
    {
        return [
            'NewBookingNotification (ruangan)' => new NewBookingNotification($this->roomBooking(), 'ROOM'),
            'NewBookingNotification (inventaris)' => new NewBookingNotification($this->inventoryBooking(), 'INVENTORY'),
            'BookingSubmitted (ruangan)' => new BookingSubmitted($this->roomBooking(), 'ROOM'),
            'BookingSubmitted (inventaris)' => new BookingSubmitted($this->inventoryBooking(), 'INVENTORY'),
            'BookingStatusUpdated (disetujui)' => new BookingStatusUpdated($this->roomBooking(), 'ROOM'),
            'BookingStatusUpdated (ditolak)' => new BookingStatusUpdated($this->inventoryBooking(), 'INVENTORY'),
            'NewContentRequestNotification' => new NewContentRequestNotification($this->contentRequest()),
            'ContentRequestSubmitted' => new ContentRequestSubmitted($this->contentRequest()),
            'ContentRequestStatusUpdated' => new ContentRequestStatusUpdated($this->contentRequest()),
            'CertificateIssued' => new CertificateIssued($this->certificate()),
        ];
    }

    // ----------------------------------------------------- templatnya jadi

    public function test_every_notification_renders_into_a_real_email(): void
    {
        $gagal = [];

        foreach ($this->everyNotification() as $nama => $notification) {
            try {
                $mail = $notification->toMail((object) []);
                $html = $mail->render();
            } catch (\Throwable $e) {
                $gagal[] = $nama.': '.$e->getMessage();

                continue;
            }

            if (blank($mail->subject)) {
                $gagal[] = $nama.': tanpa subjek';
            }

            // Templatnya panjang; keluaran yang terlalu pendek berarti
            // sebagian besar bloknya tidak tergambar.
            if (strlen($html) < 1500) {
                $gagal[] = $nama.': hasilnya terlalu pendek ('.strlen($html).' bita)';
            }
        }

        $this->assertSame([], $gagal, "Email yang gagal dibentuk:\n".implode("\n", $gagal));
    }

    /**
     * Subjeknya adalah satu-satunya yang terbaca di daftar kotak masuk,
     * jadi ia harus menyebut kode pengajuannya.
     */
    public function test_every_subject_names_what_it_is_about(): void
    {
        $tanpaPenanda = [];

        foreach ($this->everyNotification() as $nama => $notification) {
            $subjek = (string) $notification->toMail((object) [])->subject;

            if (! str_contains($subjek, 'MSC Hub')) {
                $tanpaPenanda[] = $nama.': "'.$subjek.'"';
            }
        }

        $this->assertSame([], $tanpaPenanda,
            "Subjek tanpa penanda pengirim:\n".implode("\n", $tanpaPenanda));
    }

    // ------------------------------------------------------ cara kirimnya

    /**
     * SMTP lambat, jadi email tidak boleh dikirim di dalam permintaan yang
     * sedang ditunggu orang. Satu-satunya pengecualian adalah CertificateIssued,
     * yang memang sudah dijalankan dari dalam job yang antre.
     */
    public function test_every_notification_is_queued(): void
    {
        $langsung = [];

        foreach (File::files(app_path('Notifications')) as $berkas) {
            $kelas = 'App\\Notifications\\'.$berkas->getFilenameWithoutExtension();

            if ($kelas === CertificateIssued::class) {
                continue;
            }

            if (! is_subclass_of($kelas, ShouldQueue::class)) {
                $langsung[] = class_basename($kelas);
            }
        }

        $this->assertSame([], $langsung,
            'Notifikasi ini dikirim di dalam permintaan, sehingga orang menunggu SMTP: '
            .implode(', ', $langsung));
    }

    // ------------------------------------------------- kegagalan bersuara

    /**
     * Email yang akhirnya tidak terkirim harus meninggalkan jejak.
     *
     * Tanpa `failed()`, kegagalannya hanya menjadi satu baris di failed_jobs
     * yang tidak dibaca siapa pun: peminjam tidak pernah menerima kabar,
     * sementara di panel pengajuannya tampak sudah diproses.
     */
    public function test_every_queued_notification_reports_its_own_failure(): void
    {
        $bisu = [];

        foreach (File::files(app_path('Notifications')) as $berkas) {
            $kelas = 'App\\Notifications\\'.$berkas->getFilenameWithoutExtension();

            if (! is_subclass_of($kelas, ShouldQueue::class)) {
                continue;
            }

            if (! method_exists($kelas, 'failed')) {
                $bisu[] = class_basename($kelas);
            }
        }

        $this->assertSame([], $bisu,
            'Notifikasi ini gagal tanpa meninggalkan jejak apa pun: '.implode(', ', $bisu));
    }

    /**
     * Yang dicatat harus cukup untuk menemukan pengajuannya kembali. Pesan
     * galat tanpa kode pengajuan tidak menolong siapa pun.
     */
    public function test_a_failure_is_logged_with_enough_to_find_the_record(): void
    {
        $booking = $this->roomBooking();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $pesan, array $konteks): bool => str_contains($pesan, 'tidak terkirim')
                && $konteks['booking_code'] === $booking->booking_code
                && $konteks['notification'] === 'BookingSubmitted'
                && str_contains($konteks['error'], 'Mailbox unavailable'));

        (new BookingSubmitted($booking, 'ROOM'))
            ->failed(new TransportException('550 5.1.1 Mailbox unavailable'));
    }

    public function test_a_content_request_failure_names_its_request_code(): void
    {
        $request = $this->contentRequest();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $pesan, array $konteks): bool => $konteks['request_code'] === $request->request_code);

        (new ContentRequestSubmitted($request))->failed(new TransportException('SMTP tidak menjawab'));
    }

    /**
     * Percobaan ulang harus punya jeda yang meningkat. Penyedia SMTP menolak
     * kiriman yang terlalu rapat, dan mengulang seketika hanya menghabiskan
     * jatah percobaannya pada penolakan yang sama.
     */
    public function test_every_queued_notification_backs_off_between_attempts(): void
    {
        $tanpaJeda = [];

        foreach (File::files(app_path('Notifications')) as $berkas) {
            $kelas = 'App\\Notifications\\'.$berkas->getFilenameWithoutExtension();

            if (! is_subclass_of($kelas, ShouldQueue::class)) {
                continue;
            }

            $sifat = get_class_vars($kelas);

            if (($sifat['tries'] ?? 1) < 2 || blank($sifat['backoff'] ?? null)) {
                $tanpaJeda[] = class_basename($kelas);
            }
        }

        $this->assertSame([], $tanpaJeda,
            'Notifikasi ini mengulang tanpa jeda: '.implode(', ', $tanpaJeda));
    }
}
