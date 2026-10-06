<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Mengoreksi nama dan email penerima dari halaman kegiatan.
 *
 * `certificates.recipient_name` dan `recipient_email` adalah salinan nilai
 * peserta pada saat penerbitan, bukan rujukan hidup. Karena itu membetulkan
 * data di tabel peserta saja tidak mengubah apa pun yang sudah terbit: nama
 * yang salah ketik tetap tercetak di PDF dan di halaman verifikasi, dan email
 * yang sudah dibetulkan tetap dikirim ke alamat lama. Keduanya gagal tanpa
 * suara — tidak ada galat, dan panel tetap menyatakan semuanya beres.
 *
 * Test di bawah menjaga agar koreksinya benar-benar sampai ke dokumen.
 */
class RecipientCorrectionTest extends TestCase
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
        return CertificateEvent::factory()->published()->create();
    }

    /**
     * Satu keikutsertaan lengkap dengan pesertanya, dan boleh disertai
     * sertifikat yang sudah terbit.
     *
     * @param  array<string, mixed>  $peserta
     * @param  array<string, mixed>|null  $sertifikat
     */
    private function participation(
        CertificateEvent $event,
        array $peserta = [],
        ?array $sertifikat = null,
    ): CertificateEventParticipant {
        $orang = Participant::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@student.jgu.ac.id',
            ...$peserta,
        ]);

        $keikutsertaan = CertificateEventParticipant::create([
            'certificate_event_id' => $event->id,
            'participant_id' => $orang->id,
            'role' => 'participant',
            'attendance_status' => 'attended',
            'eligible_at' => now(),
        ]);

        if ($sertifikat !== null) {
            Certificate::factory()->create([
                'certificate_event_id' => $event->id,
                'participant_id' => $orang->id,
                'event_participant_id' => $keikutsertaan->id,
                'recipient_name' => $orang->name,
                'recipient_email' => $orang->email,
                ...$sertifikat,
            ]);
        }

        return $keikutsertaan->fresh();
    }

    private function table(CertificateEvent $event): Testable
    {
        return Livewire::actingAs($this->admin())->test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ]);
    }

    // ------------------------------------------------------- formulir ubah

    /**
     * Yang diminta: email bisa diganti dari formulir ubah, bukan hanya dari
     * layar lain.
     */
    public function test_the_edit_form_is_prefilled_with_the_recipient_details(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event);

        $this->table($event)
            ->mountTableAction('edit', $keikutsertaan)
            ->assertTableActionDataSet([
                'participant_name' => 'Budi Santoso',
                'participant_email' => 'budi@student.jgu.ac.id',
            ]);
    }

    public function test_the_edit_form_saves_a_new_email_onto_the_participant(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event);

        $this->table($event)
            ->callTableAction('edit', $keikutsertaan, [
                'participant_name' => 'Budi Santoso',
                'participant_email' => 'budi.santoso@student.jgu.ac.id',
                'role' => 'participant',
                'attendance_status' => 'attended',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            'budi.santoso@student.jgu.ac.id',
            $keikutsertaan->participant->fresh()->email,
        );
    }

    /**
     * Isian keikutsertaannya sendiri tetap tersimpan: penanganan simpan
     * diambil alih untuk memisahkan data peserta, dan yang mudah terjadi di
     * situ adalah sisanya ikut tertinggal.
     */
    public function test_the_edit_form_still_saves_the_participation_itself(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event);

        $this->table($event)
            ->callTableAction('edit', $keikutsertaan, [
                'participant_name' => 'Budi Santoso',
                'participant_email' => 'budi@student.jgu.ac.id',
                'role' => 'committee',
                'role_label' => 'Ketua Pelaksana',
                'attendance_status' => 'attended',
            ])
            ->assertHasNoTableActionErrors();

        $segar = $keikutsertaan->fresh();

        $this->assertSame('committee', $segar->role);
        $this->assertSame('Ketua Pelaksana', $segar->role_label);
    }

    // ------------------------------------------ rambatan ke sertifikat

    /**
     * Inti perbaikannya. Tanpa ini alamat di sertifikat tetap yang lama, dan
     * kirim ulang pun menuju ke tempat yang sama salahnya.
     */
    public function test_a_new_email_reaches_the_issued_certificate(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event, sertifikat: []);

        $this->table($event)
            ->callTableAction('edit', $keikutsertaan, [
                'participant_name' => 'Budi Santoso',
                'participant_email' => 'budi.benar@student.jgu.ac.id',
                'role' => 'participant',
                'attendance_status' => 'attended',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            'budi.benar@student.jgu.ac.id',
            $keikutsertaan->fresh()->certificate->recipient_email,
        );
    }

    /**
     * Nama yang dicetak diambil dari salinan di sertifikat, jadi koreksi nama
     * yang berhenti di tabel peserta tidak mengubah apa pun yang dilihat orang.
     */
    public function test_a_corrected_name_reaches_the_issued_certificate(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event, sertifikat: []);

        $this->table($event)
            ->callTableAction('correctRecipient', $keikutsertaan, [
                'name' => 'Budi Santoso, S.Kom.',
                'email' => 'budi@student.jgu.ac.id',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            'Budi Santoso, S.Kom.',
            $keikutsertaan->fresh()->certificate->recipient_name,
        );
    }

    /**
     * Alamatnya berganti, jadi catatan "sudah terkirim" itu menyangkut alamat
     * lain. Bila penandanya dibiarkan, tombol Kirim Email melewati sertifikat
     * ini dan orangnya tidak pernah menerima apa pun.
     */
    public function test_changing_the_email_reopens_the_certificate_for_sending(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event, sertifikat: [
            'emailed_at' => now()->subDay(),
            'email_failed_at' => now()->subDay(),
            'email_error' => 'Alamat tidak ditemukan',
        ]);

        $this->table($event)
            ->callTableAction('correctRecipient', $keikutsertaan, [
                'name' => 'Budi Santoso',
                'email' => 'budi.benar@student.jgu.ac.id',
            ])
            ->assertHasNoTableActionErrors();

        $sertifikat = $keikutsertaan->fresh()->certificate;

        $this->assertNull($sertifikat->emailed_at);
        $this->assertNull($sertifikat->email_failed_at);
        $this->assertNull($sertifikat->email_error);
        $this->assertTrue($sertifikat->awaitsEmail());
    }

    /**
     * Mengoreksi nama saja tidak boleh membuka kembali pengirimannya: orangnya
     * sudah menerima email di alamat yang tetap benar.
     */
    public function test_correcting_only_the_name_leaves_the_delivery_mark_alone(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event, sertifikat: ['emailed_at' => now()->subDay()]);

        $this->table($event)
            ->callTableAction('correctRecipient', $keikutsertaan, [
                'name' => 'Budi Santoso, S.Kom.',
                'email' => 'budi@student.jgu.ac.id',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($keikutsertaan->fresh()->certificate->emailed_at);
    }

    /**
     * Sertifikat yang dicabut memang tidak untuk dikirim, jadi alamatnya ikut
     * dibetulkan tetapi penandanya tidak dibuka kembali.
     */
    public function test_a_revoked_certificate_is_not_reopened_for_sending(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event, sertifikat: [
            'emailed_at' => now()->subDay(),
            'revoked_at' => now(),
            'revocation_reason' => 'Salah kegiatan',
        ]);

        $this->table($event)
            ->callTableAction('correctRecipient', $keikutsertaan, [
                'name' => 'Budi Santoso',
                'email' => 'budi.benar@student.jgu.ac.id',
            ])
            ->assertHasNoTableActionErrors();

        $sertifikat = $keikutsertaan->fresh()->certificate;

        $this->assertSame('budi.benar@student.jgu.ac.id', $sertifikat->recipient_email);
        $this->assertNotNull($sertifikat->emailed_at);
    }

    // ----------------------------------------------------------- penjagaan

    /**
     * Email adalah kunci dedup peserta: dua baris beralamat sama membuat
     * pencarian berdasarkan email tidak lagi menentukan siapa yang dimaksud.
     * Batas unik di basis data tidak menolongnya — di sana yang unik gabungan
     * email dan NIM, sehingga bentrokan ini justru lolos.
     */
    public function test_an_email_already_used_by_someone_else_is_refused(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event);

        Participant::factory()->create(['email' => 'sudah.dipakai@student.jgu.ac.id']);

        $this->table($event)
            ->callTableAction('correctRecipient', $keikutsertaan, [
                'name' => 'Budi Santoso',
                'email' => 'sudah.dipakai@student.jgu.ac.id',
            ])
            ->assertHasTableActionErrors(['email']);

        $this->assertSame('budi@student.jgu.ac.id', $keikutsertaan->participant->fresh()->email);
    }

    /**
     * Penjagaan yang sama berlaku di formulir ubah, bukan hanya di tombol
     * koreksi.
     */
    public function test_the_edit_form_refuses_a_duplicate_email_too(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event);

        Participant::factory()->create(['email' => 'sudah.dipakai@student.jgu.ac.id']);

        $this->table($event)
            ->callTableAction('edit', $keikutsertaan, [
                'participant_name' => 'Budi Santoso',
                'participant_email' => 'sudah.dipakai@student.jgu.ac.id',
                'role' => 'participant',
                'attendance_status' => 'attended',
            ])
            ->assertHasTableActionErrors(['participant_email']);

        $this->assertSame('budi@student.jgu.ac.id', $keikutsertaan->participant->fresh()->email);
    }

    /**
     * Alamatnya sendiri tentu bukan bentrokan.
     */
    public function test_keeping_the_same_email_is_not_a_clash(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event);

        $this->table($event)
            ->callTableAction('correctRecipient', $keikutsertaan, [
                'name' => 'Budi Santoso, S.Kom.',
                'email' => 'budi@student.jgu.ac.id',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Budi Santoso, S.Kom.', $keikutsertaan->participant->fresh()->name);
    }

    /**
     * Email dipakai sebagai kunci, jadi bentuknya harus seragam. Alamat yang
     * diketik dengan huruf besar atau terbawa spasi harus tetap bertemu
     * dengan baris yang sama.
     */
    public function test_the_email_is_stored_normalised(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event, sertifikat: []);

        $this->table($event)
            ->callTableAction('correctRecipient', $keikutsertaan, [
                'name' => 'Budi Santoso',
                'email' => '  Budi.Benar@Student.JGU.ac.id  ',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('budi.benar@student.jgu.ac.id', $keikutsertaan->participant->fresh()->email);
        $this->assertSame('budi.benar@student.jgu.ac.id', $keikutsertaan->fresh()->certificate->recipient_email);
    }

    /**
     * Peserta hasil impor ada yang memang tidak punya alamat, jadi
     * mengosongkannya harus boleh — berbeda dari nama, yang tanpa isi membuat
     * sertifikatnya tidak ada gunanya.
     */
    public function test_the_email_may_be_cleared(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event, sertifikat: []);

        $this->table($event)
            ->callTableAction('correctRecipient', $keikutsertaan, [
                'name' => 'Budi Santoso',
                'email' => '',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNull($keikutsertaan->participant->fresh()->email);
        $this->assertNull($keikutsertaan->fresh()->certificate->recipient_email);
    }

    public function test_the_name_may_not_be_cleared(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event);

        $this->table($event)
            ->callTableAction('correctRecipient', $keikutsertaan, ['name' => '', 'email' => 'budi@student.jgu.ac.id'])
            ->assertHasTableActionErrors(['name']);

        $this->assertSame('Budi Santoso', $keikutsertaan->participant->fresh()->name);
    }

    /**
     * Satu orang satu baris peserta, dipakai bersama oleh semua kegiatan yang
     * pernah diikutinya. Alamat yang dibetulkan harus berlaku untuk seluruh
     * sertifikatnya, bukan hanya yang kebetulan sedang dibuka — sertifikat
     * lain pun akan dikirim ke alamat yang sama salahnya.
     */
    public function test_the_correction_reaches_certificates_from_other_events(): void
    {
        $event = $this->event();
        $keikutsertaan = $this->participation($event, sertifikat: []);

        $lain = Certificate::factory()->create([
            'certificate_event_id' => $this->event()->id,
            'participant_id' => $keikutsertaan->participant_id,
            'recipient_name' => 'Budi Santoso',
            'recipient_email' => 'budi@student.jgu.ac.id',
        ]);

        $this->table($event)
            ->callTableAction('correctRecipient', $keikutsertaan, [
                'name' => 'Budi Santoso, S.Kom.',
                'email' => 'budi.benar@student.jgu.ac.id',
            ])
            ->assertHasNoTableActionErrors();

        $segar = $lain->fresh();

        $this->assertSame('Budi Santoso, S.Kom.', $segar->recipient_name);
        $this->assertSame('budi.benar@student.jgu.ac.id', $segar->recipient_email);
    }
}
