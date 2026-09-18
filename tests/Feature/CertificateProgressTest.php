<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Support\CertificateProgress;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alur pembuatan sertifikat, terbaca sekilas.
 *
 * Urutannya selama ini hanya ada di kepala orang yang sudah terbiasa: tidak
 * tertulis di mana pun, dan harus disimpulkan dari tombol yang kebetulan
 * tersedia. Yang baru memakainya sering berhenti setelah menerbitkan, tidak
 * tahu bahwa emailnya masih harus dikirim terpisah.
 */
class CertificateProgressTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $status = 'published'): CertificateEvent
    {
        return CertificateEvent::factory()->create(['status' => $status]);
    }

    private function participant(CertificateEvent $event, bool $eligible = false): CertificateEventParticipant
    {
        $participant = Participant::factory()->create();

        return CertificateEventParticipant::create([
            'certificate_event_id' => $event->id,
            'participant_id' => $participant->id,
            'role' => 'participant',
            'eligible_at' => $eligible ? now() : null,
            'attendance_status' => $eligible ? 'attended' : 'registered',
        ]);
    }

    private function certificate(
        CertificateEvent $event,
        CertificateEventParticipant $participation,
        array $extra = [],
    ): Certificate {
        return Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'event_participant_id' => $participation->id,
            'participant_id' => $participation->participant_id,
            ...$extra,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function states(CertificateEvent $event): array
    {
        $keadaan = [];

        foreach (CertificateProgress::for($event)->steps() as $step) {
            $keadaan[$step['kunci']] = $step['keadaan'];
        }

        return $keadaan;
    }

    // ------------------------------------------------------ keempat langkah

    public function test_a_brand_new_event_has_not_started_any_step(): void
    {
        $this->assertSame([
            'peserta' => CertificateProgress::BELUM,
            'berhak' => CertificateProgress::BELUM,
            'terbit' => CertificateProgress::BELUM,
            'kirim' => CertificateProgress::BELUM,
        ], $this->states($this->event()));
    }

    public function test_the_steps_advance_as_the_work_is_done(): void
    {
        $event = $this->event();

        $satu = $this->participant($event);
        $this->assertSame(CertificateProgress::SELESAI, $this->states($event)['peserta']);
        $this->assertSame(CertificateProgress::BELUM, $this->states($event)['berhak']);

        $dua = $this->participant($event, eligible: true);
        // Satu dari dua: sudah berjalan, belum tuntas.
        $this->assertSame(CertificateProgress::BERJALAN, $this->states($event)['berhak']);

        $satu->update(['eligible_at' => now(), 'attendance_status' => 'attended']);
        $this->assertSame(CertificateProgress::SELESAI, $this->states($event)['berhak']);

        $this->certificate($event, $satu);
        $this->assertSame(CertificateProgress::BERJALAN, $this->states($event)['terbit']);

        $this->certificate($event, $dua);
        $this->assertSame(CertificateProgress::SELESAI, $this->states($event)['terbit']);
        $this->assertSame(CertificateProgress::BELUM, $this->states($event)['kirim']);
    }

    /**
     * Peserta yang sertifikatnya sudah terbit tidak lagi terhitung "siap
     * terbit". Bila itu tidak diperhitungkan, langkah kedua kembali tampak
     * belum tuntas justru setelah pekerjaannya selesai.
     */
    public function test_issuing_does_not_make_the_earlier_step_look_unfinished(): void
    {
        $event = $this->event();
        $orang = $this->participant($event, eligible: true);

        $this->assertSame(CertificateProgress::SELESAI, $this->states($event)['berhak']);

        $this->certificate($event, $orang);

        $this->assertSame(CertificateProgress::SELESAI, $this->states($event)['berhak'],
            'Langkah "tandai yang berhak" mundur setelah sertifikatnya terbit.');
    }

    public function test_sending_the_last_email_completes_the_flow(): void
    {
        $event = $this->event();
        $orang = $this->participant($event, eligible: true);
        $this->certificate($event, $orang, ['emailed_at' => now()]);

        $this->assertSame(CertificateProgress::SELESAI, $this->states($event)['kirim']);
        $this->assertTrue(CertificateProgress::for($event)->isDone());
    }

    /**
     * Sertifikat yang dicabut tidak lagi berlaku. Kalau ikut dihitung, alurnya
     * terlihat tuntas padahal ada yang harus diterbitkan ulang.
     */
    public function test_a_revoked_certificate_no_longer_counts_as_issued(): void
    {
        $event = $this->event();
        $orang = $this->participant($event, eligible: true);
        $sertifikat = $this->certificate($event, $orang, ['emailed_at' => now()]);

        $this->assertTrue(CertificateProgress::for($event)->isDone());

        $sertifikat->update(['revoked_at' => now(), 'revocation_reason' => 'Salah nama']);

        $this->assertFalse(CertificateProgress::for($event)->isDone());
        $this->assertSame(CertificateProgress::BELUM, $this->states($event)['terbit']);
    }

    // ---------------------------------------------- kalimat langkah berikut

    public function test_each_state_names_the_one_thing_to_do_next(): void
    {
        $event = $this->event();
        $this->assertStringContainsString('menambahkan peserta', CertificateProgress::for($event)->nextStep());

        $orang = $this->participant($event);
        $this->assertStringContainsString('berhak', CertificateProgress::for($event)->nextStep());

        $orang->update(['eligible_at' => now(), 'attendance_status' => 'attended']);
        $this->assertStringContainsString('Terbitkan Digital', CertificateProgress::for($event)->nextStep());

        $sertifikat = $this->certificate($event, $orang);
        $this->assertStringContainsString('Kirim Email', CertificateProgress::for($event)->nextStep());

        $sertifikat->update(['emailed_at' => now()]);
        $this->assertStringContainsString('sudah terbit dan terkirim', CertificateProgress::for($event)->nextStep());
    }

    public function test_failed_emails_are_named_instead_of_being_reported_as_done(): void
    {
        $event = $this->event();
        $orang = $this->participant($event, eligible: true);
        $this->certificate($event, $orang, [
            'email_failed_at' => now(),
            'email_error' => 'Alamat tidak ditemukan',
        ]);

        $progress = CertificateProgress::for($event);

        $this->assertSame(1, $progress->failedEmails());
        $this->assertStringContainsString('gagal terkirim', $progress->nextStep());
        $this->assertFalse($progress->isDone());
    }

    /**
     * Status Draft paling sering terlewat karena diatur di bagian lain
     * formulir, padahal selama itu sertifikatnya belum sah.
     */
    public function test_a_draft_event_is_warned_about_before_anything_else(): void
    {
        $this->assertStringContainsString('Draft', CertificateProgress::for($this->event('draft'))->publicationWarning());
        $this->assertStringContainsString('diarsipkan', CertificateProgress::for($this->event('archived'))->publicationWarning());
        $this->assertNull(CertificateProgress::for($this->event('published'))->publicationWarning());
    }

    // ------------------------------------------------------------ kinerjanya

    /**
     * Ringkasan ini dihitung di setiap pembukaan halaman, jadi ia tidak boleh
     * ikut membesar bersama jumlah peserta.
     */
    public function test_the_summary_costs_the_same_for_one_participant_as_for_many(): void
    {
        $event = $this->event();

        $hitung = function () use ($event): int {
            $n = 0;
            DB::listen(function () use (&$n): void {
                $n++;
            });

            CertificateProgress::for($event->fresh())->steps();

            return $n;
        };

        $orang = $this->participant($event, eligible: true);
        $this->certificate($event, $orang);
        $sedikit = $hitung();

        for ($i = 0; $i < 25; $i++) {
            $lain = $this->participant($event, eligible: true);
            $this->certificate($event, $lain);
        }

        $this->assertSame($sedikit, $hitung(),
            'Jumlah kueri ikut bertambah seiring jumlah peserta.');
    }

    // ------------------------------------------------------------- di panel

    public function test_the_event_page_shows_the_flow_before_the_form(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = $this->event('draft');
        $this->participant($event);

        Livewire::actingAs($admin)
            ->test(EditCertificateEvent::class, ['record' => $event->id])
            ->assertSuccessful();

        $this->actingAs($admin)
            ->get(EditCertificateEvent::getUrl(['record' => $event->id]))
            ->assertOk()
            ->assertSee('Alur pembuatan sertifikat')
            ->assertSee('Daftarkan peserta')
            ->assertSee('Terbitkan digital')
            ->assertSee('Kirim email')
            // Statusnya masih Draft, jadi peringatannya harus muncul.
            ->assertSee('belum sah', false);
    }
}
