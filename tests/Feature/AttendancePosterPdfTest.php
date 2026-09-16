<?php

namespace Tests\Feature;

use App\Enums\AttendanceAction;
use App\Models\CertificateEvent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Lembar QR absensi untuk dicetak.
 *
 * Halaman posternya dibuat untuk diproyeksikan di layar, tetapi panitia juga
 * perlu menempelnya di pintu masuk. Mencetak halaman peramban ikut membawa
 * tombol dan bilah alamat, jadi lembarnya dibuat sendiri sebagai A4.
 */
class AttendancePosterPdfTest extends TestCase
{
    use RefreshDatabase;

    private function event(): CertificateEvent
    {
        return CertificateEvent::factory()->published()->withOpenAttendance()->create([
            'name' => 'Seminar Kewirausahaan',
        ]);
    }

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function url(CertificateEvent $event, AttendanceAction $action, bool $unduh = false): string
    {
        return route('attendance.poster', array_filter([
            'event' => $event,
            'action' => $action->value,
            'unduh' => $unduh ? 1 : null,
        ]));
    }

    /**
     * Teks yang benar-benar tercetak di dalam PDF.
     *
     * Aliran isinya terkompresi, dan sebagian teks disimpan sebagai UTF-16
     * tergantung fontnya, jadi bita nol dibuang agar keduanya terbaca sebagai
     * satu untai biasa.
     */
    private function textOf(string $pdf): string
    {
        $teks = '';

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $cocok)) {
            foreach ($cocok[1] as $aliran) {
                $isi = @gzuncompress($aliran);

                if ($isi !== false) {
                    $teks .= str_replace("\0", '', $isi);
                }
            }
        }

        return $teks;
    }

    /**
     * Membandingkan langsung dengan isi PDF membuat kegagalannya memuntahkan
     * berkas biner utuh, jadi hasilnya diperiksa sebagai pernyataan benar
     * atau salah beserta keterangannya.
     */
    private function assertPrinted(string $dicari, string $teks): void
    {
        $this->assertTrue(
            str_contains($teks, $dicari),
            "Teks \"{$dicari}\" tidak ditemukan di dalam lembar PDF.",
        );
    }

    // -------------------------------------------------------------- unduhan

    public function test_both_actions_can_be_downloaded_as_a4(): void
    {
        $this->actingAs($this->staff());
        $event = $this->event();

        foreach (AttendanceAction::cases() as $action) {
            $response = $this->get($this->url($event, $action, unduh: true))->assertOk();

            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        }
    }

    public function test_the_file_is_named_after_the_action_and_the_event(): void
    {
        $this->actingAs($this->staff());
        $event = $this->event();

        $disposition = (string) $this->get($this->url($event, AttendanceAction::CHECK_IN, unduh: true))
            ->assertOk()
            ->headers->get('Content-Disposition');

        $this->assertStringContainsString('seminar-kewirausahaan', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    /**
     * Inti gunanya: lembar yang ditempel harus memuat QR-nya. PDF yang
     * terunduh tetapi kosong lebih buruk daripada tidak ada tombol unduh.
     */
    public function test_the_sheet_actually_carries_the_qr_code(): void
    {
        $this->actingAs($this->staff());
        $event = $this->event();

        $pdf = $this->get($this->url($event, AttendanceAction::CHECK_IN, unduh: true))->assertOk()->getContent();

        // Gambar tertanam sebagai objek XObject tersendiri di dalam berkas.
        $this->assertStringContainsString('/Image', $pdf);
        $this->assertGreaterThan(20000, strlen($pdf), 'Berkasnya terlalu kecil untuk memuat QR.');
    }

    public function test_the_sheet_says_which_action_and_which_event(): void
    {
        $this->actingAs($this->staff());
        $event = $this->event();

        $teks = $this->textOf(
            $this->get($this->url($event, AttendanceAction::CHECK_OUT, unduh: true))->assertOk()->getContent(),
        );

        $this->assertPrinted('Seminar Kewirausahaan', $teks);
        // Labelnya dicetak huruf besar oleh gayanya, supaya panitia bisa
        // memastikan sekilas bahwa lembar yang ditempel adalah aksi yang benar.
        $this->assertPrinted('CHECK-OUT', $teks);
        $this->assertPrinted('Pindai QR', $teks);
    }

    /**
     * Tautannya ikut tercetak supaya peserta yang kameranya bermasalah tetap
     * bisa mengetiknya.
     */
    public function test_the_sheet_prints_the_link_as_well(): void
    {
        $this->actingAs($this->staff());
        $event = $this->event();

        $teks = $this->textOf(
            $this->get($this->url($event, AttendanceAction::CHECK_IN, unduh: true))->assertOk()->getContent(),
        );

        $this->assertPrinted($event->attendanceToken(AttendanceAction::CHECK_IN), $teks);
    }

    // ------------------------------------------------------------- halaman

    /**
     * Halaman layarnya tetap jadi bawaan; tombol unduh hanya menambah jalan,
     * bukan menggantikannya.
     */
    public function test_the_screen_page_still_comes_first(): void
    {
        $this->actingAs($this->staff());
        $event = $this->event();

        $response = $this->get($this->url($event, AttendanceAction::CHECK_IN))->assertOk();

        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $response->assertSee('Unduh PDF');
        $response->assertSee($this->url($event, AttendanceAction::CHECK_IN, unduh: true), false);
    }

    /**
     * Tombolnya alat bantu layar, bukan bagian poster: mencetak halaman ini
     * langsung dari peramban tidak boleh ikut membawanya.
     */
    public function test_the_button_never_ends_up_on_paper(): void
    {
        $this->actingAs($this->staff());

        $this->get($this->url($this->event(), AttendanceAction::CHECK_IN))
            ->assertOk()
            ->assertSee('@media print', false)
            ->assertSee('class="no-print', false);
    }

    public function test_an_event_without_attendance_has_no_sheet(): void
    {
        $this->actingAs($this->staff());

        $event = CertificateEvent::factory()->published()->create(['attendance_enabled' => false]);

        $this->get($this->url($event, AttendanceAction::CHECK_IN, unduh: true))->assertNotFound();
    }

    public function test_a_stranger_cannot_download_it(): void
    {
        $event = $this->event();

        $this->get($this->url($event, AttendanceAction::CHECK_IN, unduh: true))->assertRedirect();
    }
}
