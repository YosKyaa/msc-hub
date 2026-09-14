<?php

namespace Tests\Feature;

use App\Enums\CertificateNumberReset;
use App\Filament\Pages\CertificateNumberSettings;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateNumberException;
use App\Services\Certificates\CertificateNumberFormat;
use App\Services\Certificates\CertificateNumberGenerator;
use App\Support\AppSetting;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Penomoran sertifikat mengikuti ketentuan kampus: satu pola default berlaku
 * otomatis untuk semua sertifikat, tiap kegiatan boleh menimpanya, dan nomor
 * per peserta tetap dapat ditetapkan manual.
 */
class CertificateNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function setPattern(string $pattern, CertificateNumberReset $reset = CertificateNumberReset::YEARLY, string $unit = 'MSC-JGU'): void
    {
        AppSetting::set(CertificateNumberFormat::SETTING_PATTERN, $pattern);
        AppSetting::set(CertificateNumberFormat::SETTING_RESET, $reset->value);
        AppSetting::set(CertificateNumberFormat::SETTING_UNIT_CODE, $unit);
    }

    private function eligible(CertificateEvent $event): CertificateEventParticipant
    {
        return CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);
    }

    // ------------------------------------------------------------ pola

    public function test_the_default_pattern_is_applied_to_every_certificate(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $this->setPattern('{nomor:4}/CERT/{kode_unit}/{bulan_romawi}/{tahun}');

        $event = CertificateEvent::factory()->published()->create();
        $issuer = app(CertificateIssuer::class);

        $first = $issuer->issue($this->eligible($event));
        $second = $issuer->issue($this->eligible($event));

        $this->assertSame('0001/CERT/MSC-JGU/IX/2026', $first->certificate_number);
        $this->assertSame('0002/CERT/MSC-JGU/IX/2026', $second->certificate_number);
    }

    public function test_every_token_is_rendered(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $this->setPattern('{nomor:3}-{tahun}-{tahun_pendek}-{bulan}-{bulan_romawi}-{tanggal}-{kode_kegiatan}-{kode_unit}');

        $event = CertificateEvent::factory()->published()->create(['certificate_code' => 'ESSENTIAL']);

        $number = app(CertificateIssuer::class)->issue($this->eligible($event))->certificate_number;

        $this->assertSame('001-2026-26-09-IX-06-ESSENTIAL-MSC-JGU', $number);
    }

    public function test_the_event_code_falls_back_to_the_event_name(): void
    {
        $this->setPattern('{nomor}/{kode_kegiatan}');

        $event = CertificateEvent::factory()->published()->create([
            'name' => 'Pelatihan Jurnalistik',
            'certificate_code' => null,
        ]);

        $number = app(CertificateIssuer::class)->issue($this->eligible($event))->certificate_number;

        $this->assertSame('1/PELATIHANJURNALISTIK', $number);
    }

    public function test_an_event_may_override_the_campus_pattern(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $this->setPattern('{nomor:4}/CERT/{kode_unit}/{tahun}');

        $standard = CertificateEvent::factory()->published()->create();
        $special = CertificateEvent::factory()->published()->create([
            'certificate_code' => 'HACKATHON',
            'certificate_number_format' => '{nomor:5}/PLT/{kode_kegiatan}/{tahun}',
        ]);

        $issuer = app(CertificateIssuer::class);

        $this->assertSame('0001/CERT/MSC-JGU/2026', $issuer->issue($this->eligible($standard))->certificate_number);
        $this->assertSame('00002/PLT/HACKATHON/2026', $issuer->issue($this->eligible($special))->certificate_number);
    }

    // -------------------------------------------------------- nomor urut

    public function test_the_sequence_restarts_each_year(): void
    {
        $this->setPattern('{nomor:3}/{tahun}', CertificateNumberReset::YEARLY);
        $event = CertificateEvent::factory()->published()->create();
        $issuer = app(CertificateIssuer::class);

        $this->travelTo(now()->setDate(2026, 12, 31));
        $this->assertSame('001/2026', $issuer->issue($this->eligible($event))->certificate_number);

        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->assertSame('001/2027', $issuer->issue($this->eligible($event))->certificate_number);
    }

    public function test_the_sequence_can_restart_for_each_event(): void
    {
        $this->setPattern('{nomor:3}/{kode_kegiatan}', CertificateNumberReset::PER_EVENT);

        $first = CertificateEvent::factory()->published()->create(['certificate_code' => 'SATU']);
        $second = CertificateEvent::factory()->published()->create(['certificate_code' => 'DUA']);
        $issuer = app(CertificateIssuer::class);

        $issuer->issue($this->eligible($first));
        $this->assertSame('002/SATU', $issuer->issue($this->eligible($first))->certificate_number);
        $this->assertSame('001/DUA', $issuer->issue($this->eligible($second))->certificate_number);
    }

    public function test_the_sequence_can_run_continuously(): void
    {
        $this->setPattern('{nomor:4}', CertificateNumberReset::NEVER);
        $issuer = app(CertificateIssuer::class);

        $first = CertificateEvent::factory()->published()->create();
        $second = CertificateEvent::factory()->published()->create();

        $issuer->issue($this->eligible($first));
        $this->travelTo(now()->addYear());

        $this->assertSame('0002', $issuer->issue($this->eligible($second))->certificate_number);
    }

    public function test_a_pattern_without_a_sequence_token_is_refused_on_the_second_certificate(): void
    {
        $this->setPattern('CERT/{tahun}');
        $event = CertificateEvent::factory()->published()->create();
        $issuer = app(CertificateIssuer::class);

        $issuer->issue($this->eligible($event));

        $this->expectException(CertificateNumberException::class);
        $this->expectExceptionMessage('{nomor}');

        $issuer->issue($this->eligible($event));
    }

    // ------------------------------------------------------------ manual

    public function test_a_manual_number_always_wins_over_the_pattern(): void
    {
        $this->setPattern('{nomor:4}/CERT/{tahun}');
        $event = CertificateEvent::factory()->published()->create();

        $participation = $this->eligible($event);
        $participation->update(['certificate_number' => '0057/PLT2260383/ESSENTIAL/2026']);

        $certificate = app(CertificateIssuer::class)->issue($participation->fresh());

        $this->assertSame('0057/PLT2260383/ESSENTIAL/2026', $certificate->certificate_number);
        // Nomor manual tidak menghabiskan jatah urutan otomatis.
        $this->assertSame(0, DB::table('certificate_number_sequences')->count());
    }

    public function test_manual_and_generated_numbers_live_side_by_side(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $this->setPattern('{nomor:4}/CERT/{tahun}');
        $event = CertificateEvent::factory()->published()->create();
        $issuer = app(CertificateIssuer::class);

        $manual = $this->eligible($event);
        $manual->update(['certificate_number' => 'NOMOR-KHUSUS-KAMPUS']);

        $this->assertSame('NOMOR-KHUSUS-KAMPUS', $issuer->issue($manual->fresh())->certificate_number);
        $this->assertSame('0001/CERT/2026', $issuer->issue($this->eligible($event))->certificate_number);
    }

    // --------------------------------------------------------- pengaturan

    public function test_the_settings_page_stores_and_previews_the_pattern(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user->fresh());

        Livewire::test(CertificateNumberSettings::class)
            ->assertSuccessful()
            ->set('data.pattern', '{nomor:5}/SK/{kode_unit}/{tahun}')
            ->set('data.unit_code', 'FTI-JGU')
            ->set('data.reset', CertificateNumberReset::MONTHLY->value)
            ->call('save')
            ->assertHasNoErrors();

        $format = CertificateNumberFormat::default();
        $this->assertSame('{nomor:5}/SK/{kode_unit}/{tahun}', $format->pattern);
        $this->assertSame('FTI-JGU', $format->unitCode);
        $this->assertSame(CertificateNumberReset::MONTHLY, $format->reset);
        $this->assertStringContainsString('00057/SK/FTI-JGU/', $format->preview());
    }

    public function test_the_settings_page_rejects_a_pattern_without_a_sequence_token(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user->fresh());

        Livewire::test(CertificateNumberSettings::class)
            ->set('data.pattern', 'CERT/{tahun}')
            ->set('data.unit_code', 'MSC')
            ->set('data.reset', 'yearly')
            ->call('save')
            ->assertHasErrors('data.pattern');

        // Pengaturan lama tidak boleh ikut berubah saat validasi gagal.
        $this->assertSame(CertificateNumberFormat::DEFAULT_PATTERN, CertificateNumberFormat::default()->pattern);
    }

    public function test_concurrent_issuing_never_repeats_a_number(): void
    {
        $this->setPattern('{nomor:4}/CERT/{tahun}');
        $event = CertificateEvent::factory()->published()->create();
        $generator = app(CertificateNumberGenerator::class);

        $numbers = collect(range(1, 25))->map(fn () => $generator->next($event));

        $this->assertCount(25, $numbers->unique());
    }
}
