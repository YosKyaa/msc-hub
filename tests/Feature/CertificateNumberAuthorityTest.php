<?php

namespace Tests\Feature;

use App\Enums\CertificateNumberReset;
use App\Filament\Resources\IssuerResource;
use App\Filament\Resources\IssuerResource\Pages\ListIssuers;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Issuer;
use App\Models\Participant;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateNumberCounter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Nomor sertifikat dari beberapa unit penerbit.
 *
 * Di kampus, sertifikat bisa diterbitkan Rektorat, SCD, jurusan, atau MSC
 * sendiri, dan masing-masing memegang buku register yang sudah berjalan di
 * luar sistem ini. Karena itu tiap unit butuh pola, urutan, dan nomor awalnya
 * sendiri — dan nomornya harus bisa diselaraskan dengan register yang ada.
 */
class CertificateNumberAuthorityTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $nama, string $kode, string $pola, int $mulai = 1): Issuer
    {
        return Issuer::create([
            'name' => $nama,
            'code' => $kode,
            'number_pattern' => $pola,
            'number_reset' => CertificateNumberReset::YEARLY,
            'number_start' => $mulai,
            'is_active' => true,
        ]);
    }

    private function issueOne(Issuer $issuer): Certificate
    {
        $event = CertificateEvent::factory()->published()->create(['issuer_id' => $issuer->id]);

        $participation = CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);

        return app(CertificateIssuer::class)->issue($participation);
    }

    // ------------------------------------------------ tiap unit berdiri sendiri

    /**
     * Yang diminta: tiap unit penerbit punya pola nomornya sendiri.
     */
    public function test_each_authority_stamps_its_own_format(): void
    {
        $rektorat = $this->unit('Rektorat JGU', 'REK', '{nomor:3}/SERT/{kode_unit}/{bulan_romawi}/{tahun}');
        $scd = $this->unit('Student Career Development', 'SCD', '{nomor:4}/SCD/{tahun}');

        $this->assertStringContainsString('/SERT/REK/', $this->issueOne($rektorat)->certificate_number);
        $this->assertStringContainsString('/SCD/', $this->issueOne($scd)->certificate_number);
    }

    /**
     * Urutan satu unit tidak boleh menggerus urutan unit lain.
     */
    public function test_one_authority_never_consumes_another_sequence(): void
    {
        $rektorat = $this->unit('Rektorat JGU', 'REK', '{nomor:3}/REK/{tahun}');
        $jurusan = $this->unit('Teknik Informatika', 'TI', '{nomor:3}/TI/{tahun}');

        $this->issueOne($rektorat);
        $this->issueOne($rektorat);
        $pertamaJurusan = $this->issueOne($jurusan);

        // Jurusan baru menerbitkan satu, jadi nomornya tetap 001.
        $this->assertStringStartsWith('001/TI/', $pertamaJurusan->certificate_number);
        $this->assertSame(3, app(CertificateNumberCounter::class)->nextNumber($rektorat));
    }

    // ------------------------------------------------------------ nomor awal

    /**
     * Inti permintaannya: register unit sudah berjalan di luar sistem, jadi
     * urutannya tidak boleh dipaksa mulai dari satu.
     */
    public function test_an_authority_may_start_from_its_own_number(): void
    {
        $scd = $this->unit('Student Career Development', 'SCD', '{nomor:4}/SCD/{tahun}', mulai: 120);

        $this->assertSame('0120/SCD/'.now()->format('Y'), $this->issueOne($scd)->certificate_number);
        $this->assertSame('0121/SCD/'.now()->format('Y'), $this->issueOne($scd)->certificate_number);
    }

    public function test_without_a_starting_number_it_simply_begins_at_one(): void
    {
        $msc = $this->unit('MSC JGU', 'MSC', '{nomor:3}/MSC/{tahun}');

        $this->assertStringStartsWith('001/MSC/', $this->issueOne($msc)->certificate_number);
    }

    // -------------------------------------------------------- penghitungnya

    public function test_the_counter_says_where_the_register_stands(): void
    {
        $counter = app(CertificateNumberCounter::class);
        $rektorat = $this->unit('Rektorat JGU', 'REK', '{nomor:3}/REK/{tahun}', mulai: 50);

        $this->assertNull($counter->lastNumber($rektorat));
        $this->assertSame(50, $counter->nextNumber($rektorat));

        $this->issueOne($rektorat);

        $this->assertSame(50, $counter->lastNumber($rektorat));
        $this->assertSame(51, $counter->nextNumber($rektorat));
    }

    /**
     * Menyelaraskan dengan register kertas yang sudah berjalan.
     */
    public function test_the_counter_can_be_lined_up_with_an_existing_register(): void
    {
        $counter = app(CertificateNumberCounter::class);
        $scd = $this->unit('Student Career Development', 'SCD', '{nomor:4}/SCD/{tahun}');

        $this->issueOne($scd);

        $counter->setNextNumber($scd, 200);

        $this->assertSame(200, $counter->nextNumber($scd));
        $this->assertSame('0200/SCD/'.now()->format('Y'), $this->issueOne($scd)->certificate_number);
    }

    /**
     * Penerbit yang mengulang nomor tiap kegiatan tidak punya penghitung
     * tunggal, jadi penyetelannya ditolak dengan alasan yang jelas.
     */
    public function test_a_per_event_authority_has_no_single_counter(): void
    {
        $jurusan = $this->unit('Teknik Informatika', 'TI', '{nomor:3}/TI/{kode_kegiatan}');
        $jurusan->update(['number_reset' => CertificateNumberReset::PER_EVENT]);

        $counter = app(CertificateNumberCounter::class);

        $this->assertNull($counter->currentScope($jurusan->fresh()));

        $this->expectExceptionMessage('tidak dapat disetel dari sini');

        $counter->setNextNumber($jurusan->fresh(), 5);
    }

    // ------------------------------------------------------------- di panel

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_the_panel_shows_the_next_number_for_every_authority(): void
    {
        $this->actingAs($this->admin());

        $scd = $this->unit('Student Career Development', 'SCD', '{nomor:4}/SCD/{tahun}', mulai: 120);

        Livewire::test(ListIssuers::class)
            ->assertSuccessful()
            ->assertSee('Student Career Development')
            ->assertSee('120')
            ->assertSee('Belum ada yang terbit');

        $this->issueOne($scd);

        Livewire::test(ListIssuers::class)
            ->assertSuccessful()
            ->assertSee('Terakhir terpakai: 120');
    }

    public function test_the_panel_can_line_the_counter_up(): void
    {
        $this->actingAs($this->admin());

        $rektorat = $this->unit('Rektorat JGU', 'REK', '{nomor:3}/REK/{tahun}');
        $this->issueOne($rektorat);

        Livewire::test(ListIssuers::class)
            ->callTableAction('setNextNumber', $rektorat, ['next' => 300])
            ->assertHasNoTableActionErrors();

        $this->assertSame(300, app(CertificateNumberCounter::class)->nextNumber($rektorat->fresh()));
    }

    public function test_the_resource_is_reachable(): void
    {
        $this->actingAs($this->admin());

        $this->get(IssuerResource::getUrl())->assertOk();
    }
}
