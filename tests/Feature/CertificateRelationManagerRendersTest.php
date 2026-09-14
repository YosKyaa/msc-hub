<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource;
use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use App\Support\CertificateStage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Satu tabel untuk seluruh perjalanan sertifikat.
 *
 * Orang yang sama dulu muncul dua kali — sekali sebagai peserta, sekali lagi
 * sebagai penerima sertifikat di tab lain — sehingga admin harus berpindah
 * bolak-balik. Test ini menjaga agar tabelnya tetap satu, aksinya lengkap,
 * dan tetap berupa tombol ikon inline: panel ActionGroup Filament dirender
 * tanpa modifier `.flip`, sehingga dropdown selalu membuka ke bawah dan
 * terpotong tepi layar pada baris terakhir.
 */
class CertificateRelationManagerRendersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function event(): CertificateEvent
    {
        $event = CertificateEvent::factory()->published()->withOpenAttendance()->create();

        CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);

        return $event;
    }

    private function table(CertificateEvent $event): Testable
    {
        return Livewire::test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ]);
    }

    /**
     * Yang diminta: satu halaman, bukan dua tab yang menampilkan orang yang
     * sama dua kali.
     */
    public function test_the_event_page_carries_exactly_one_table(): void
    {
        $this->assertSame(
            [ParticipationsRelationManager::class],
            CertificateEventResource::getRelations(),
        );
    }

    public function test_the_table_renders_its_row_actions_as_icon_buttons(): void
    {
        $this->actingAs($this->admin());

        $this->table($this->event())
            ->assertSuccessful()
            ->assertTableActionExists('toggleEligible')
            ->assertTableActionExists('correctName')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('delete')
            ->assertTableActionHasIcon('correctName', 'heroicon-o-pencil-square');
    }

    /**
     * Aksi sertifikat yang dulu ada di tab lain kini menempel pada baris
     * orangnya.
     */
    public function test_certificate_actions_live_on_the_same_row(): void
    {
        $this->actingAs($this->admin());

        $this->table($this->event())
            ->assertSuccessful()
            ->assertTableActionExists('verify')
            ->assertTableActionExists('download')
            ->assertTableActionExists('sendEmail')
            ->assertTableActionExists('revoke')
            ->assertTableActionExists('restore');
    }

    public function test_both_stages_are_offered_as_numbered_header_actions(): void
    {
        $this->actingAs($this->admin());

        $this->table($this->event())
            ->assertSuccessful()
            ->assertTableActionExists('issueAllEligible')
            ->assertTableActionExists('sendPendingEmails')
            ->assertTableBulkActionExists('issueCertificates')
            ->assertTableBulkActionExists('sendEmails');
    }

    // ---------------------------------------------------------- tahap

    /**
     * Keadaan seseorang dulu tersebar di kolom Eligible, Sertifikat, dan
     * Email; kini satu tahap bernama yang menjelaskan langkah berikutnya.
     */
    public function test_the_stage_column_names_where_each_person_stands(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();

        $this->table($event)->assertSuccessful()->assertSee('Siap diterbitkan');

        app(CertificateIssuer::class)->issue($event->participations()->sole());

        $this->table($event->fresh())->assertSuccessful()->assertSee('Terbit, belum dikirim');
    }

    public function test_the_table_summarises_the_whole_event(): void
    {
        $this->actingAs($this->admin());

        $this->table($this->event())
            ->assertSuccessful()
            ->assertSee('1 orang')
            ->assertSee('Siap diterbitkan');
    }

    /**
     * Penyaring memakai kueri tersendiri, terpisah dari aturan yang menghitung
     * tahap tiap baris. Harapannya sengaja dihitung dari aturan itu sendiri,
     * supaya keduanya tidak bisa diam-diam berbeda pendapat — admin yang
     * menyaring "Terbit, belum dikirim" tidak boleh melihat orang yang sudah
     * dikirimi.
     */
    public function test_the_stage_filter_agrees_with_the_stage_rule(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();

        $terbit = $this->extraParticipation($event);
        app(CertificateIssuer::class)->issue($terbit);

        $terkirim = $this->extraParticipation($event);
        app(CertificateIssuer::class)->issue($terkirim)->forceFill(['emailed_at' => now()])->save();

        $gagal = $this->extraParticipation($event);
        app(CertificateIssuer::class)->issue($gagal)->forceFill(['email_failed_at' => now()])->save();

        $dicabut = $this->extraParticipation($event);
        app(CertificateIssuer::class)->issue($dicabut)->forceFill(['revoked_at' => now()])->save();

        $this->extraParticipation($event, eligible: false);

        $semua = $event->participations()->with('certificate')->get();

        foreach (CertificateStage::cases() as $stage) {
            [$cocok, $tidak] = $semua->partition(fn ($p) => CertificateStage::for($p) === $stage);

            $this->table($event->fresh())
                ->filterTable('stage', $stage->value)
                ->assertCanSeeTableRecords($cocok->all())
                ->assertCanNotSeeTableRecords($tidak->all());
        }
    }

    private function extraParticipation(CertificateEvent $event, bool $eligible = true): CertificateEventParticipant
    {
        $factory = CertificateEventParticipant::factory();

        return ($eligible ? $factory->eligible() : $factory)->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);
    }

    // ------------------------------------------------------------ aksi

    public function test_the_eligible_toggle_actually_flips_the_flag(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();
        $participation = $event->participations()->sole();

        $this->table($event)->callTableAction('toggleEligible', $participation);

        $this->assertNull($participation->fresh()->eligible_at);
    }

    public function test_correcting_the_name_updates_the_master_participant(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();
        $participation = $event->participations()->sole();

        $this->table($event)->callTableAction('correctName', $participation, ['name' => 'Budi Santoso, S.Kom.']);

        $this->assertSame('Budi Santoso, S.Kom.', $participation->participant->fresh()->name);
    }

    /**
     * Menerbitkan lewat panel tidak boleh ikut mengirim email; pengirimannya
     * tombol tersendiri.
     */
    public function test_issuing_from_the_panel_sends_no_email(): void
    {
        Queue::fake();

        $this->actingAs($this->admin());

        $this->table($this->event())->callTableAction('issueAllEligible')->assertHasNoTableActionErrors();

        Queue::assertNotPushed(SendCertificateEmailJob::class);
    }

    public function test_sending_from_the_panel_queues_the_waiting_emails(): void
    {
        Bus::fake();

        $this->actingAs($this->admin());
        $event = $this->event();

        Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'event_participant_id' => $event->participations()->value('id'),
            'recipient_email' => 'budi@student.jgu.ac.id',
        ]);

        $this->table($event->fresh())->callTableAction('sendPendingEmails')->assertHasNoTableActionErrors();

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
    }

    /**
     * Kunci asing sertifikat memakai nullOnDelete, jadi menghapus peserta
     * hanya melepaskan dokumennya dari pemiliknya — hilang dari tabel tanpa
     * benar-benar hilang. Pencabutan yang dipakai untuk itu.
     */
    public function test_someone_holding_a_certificate_can_no_longer_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();

        app(CertificateIssuer::class)->issue($event->participations()->sole());

        $this->table($event->fresh())
            ->assertSuccessful()
            ->assertTableActionHidden('delete', $event->participations()->sole());
    }
}
