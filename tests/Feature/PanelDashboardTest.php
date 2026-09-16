<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Filament\Widgets\BookingCalendarWidget;
use App\Filament\Widgets\PendingActionsWidget;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Halaman depan panel.
 *
 * Dasbor sebelumnya hanya menghitung berapa banyak arsip yang tersimpan —
 * angka yang menyenangkan tetapi tidak menyuruh siapa pun berbuat apa. Yang
 * dibutuhkan staf saat membuka panel adalah daftar pekerjaan yang tertahan
 * pada dirinya, beserta jalan pintas ke sana.
 */
class PanelDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'admin'): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function waitingBooking(): RoomBooking
    {
        $room = Room::create([
            'name' => 'Ruang Multimedia MSC',
            'location' => 'Gedung A Lantai 3',
            'capacity' => 20,
            'is_active' => true,
        ]);

        return RoomBooking::create([
            'booking_code' => 'RB-UJI-001',
            'room_id' => $room->id,
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'unit' => 'HIMATIF',
            'purpose' => 'Rapat koordinasi',
            'attendees' => 5,
            'start_at' => Carbon::now()->addDay(),
            'end_at' => Carbon::now()->addDay()->addHours(2),
            'status' => BookingStatus::PENDING,
        ]);
    }

    public function test_the_dashboard_leads_with_what_is_waiting(): void
    {
        $this->actingAs($this->staff());
        $this->waitingBooking();

        Livewire::test(PendingActionsWidget::class)
            ->assertSuccessful()
            ->assertSee('Perlu Tindakan Anda')
            ->assertSee('Booking Ruangan')
            ->assertSee('Permintaan Konten')
            ->assertSee('Sertifikat Siap Terbit')
            ->assertSee('Sertifikat Belum Dikirim');
    }

    public function test_nothing_waiting_reads_as_nothing_waiting(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(PendingActionsWidget::class)
            ->assertSuccessful()
            ->assertSee('Tidak ada yang menunggu');
    }

    /**
     * Angka sertifikatnya meminjam aturan CertificateStage, sehingga dasbor
     * tidak bisa berbeda dari yang tertulis di halaman kegiatan.
     */
    public function test_the_certificate_counts_follow_the_same_stages(): void
    {
        $this->actingAs($this->staff());

        $event = CertificateEvent::factory()->published()->create();

        $siap = CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);

        $terbit = CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);
        app(CertificateIssuer::class)->issue($terbit);

        $widget = Livewire::test(PendingActionsWidget::class)->assertSuccessful();

        // Satu siap diterbitkan, satu menunggu dikirim.
        $widget->assertSee('peserta berhak, belum diterbitkan');
        $widget->assertSee('sudah terbit, email belum dikirim');

        $this->assertNotNull($siap->fresh());
    }

    /**
     * Jadwal peminjaman memuat nama peminjam dan keperluannya, jadi tidak
     * pantas terbuka bagi siapa pun yang berhasil masuk panel — sebelumnya
     * dipaksa selalu tampil oleh sisa penyetelan sementara.
     */
    public function test_the_booking_calendar_follows_the_same_permission(): void
    {
        $this->seed(RoleSeeder::class);

        $tanpaIzin = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($tanpaIzin->fresh());
        $this->assertFalse(BookingCalendarWidget::canView());
        $this->assertFalse(PendingActionsWidget::canView());

        $this->actingAs($this->staff());
        $this->assertTrue(BookingCalendarWidget::canView());
        $this->assertTrue(PendingActionsWidget::canView());
    }

    /**
     * Widget promosi bawaan Filament tidak membantu siapa pun di sini.
     */
    public function test_the_dashboard_carries_no_vendor_promotion(): void
    {
        $this->actingAs($this->staff());

        $this->get(route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertDontSee('filament.com', false)
            ->assertDontSee('FilamentInfoWidget', false);
    }

    /**
     * Biaya halaman depan tidak boleh tumbuh bersama jumlah pengajuan.
     */
    public function test_the_widget_costs_the_same_however_much_is_waiting(): void
    {
        $this->actingAs($this->staff());

        $hitung = function (): int {
            $n = 0;
            DB::listen(function () use (&$n): void {
                $n++;
            });

            Livewire::test(PendingActionsWidget::class)->assertSuccessful();

            return $n;
        };

        $this->waitingBooking();
        $sedikit = $hitung();

        $event = CertificateEvent::factory()->published()->create();
        for ($i = 0; $i < 10; $i++) {
            CertificateEventParticipant::factory()->eligible()->create([
                'certificate_event_id' => $event->id,
                'participant_id' => Participant::factory(),
            ]);
        }

        $this->assertLessThanOrEqual($sedikit, $hitung(),
            'Jumlah kueri ikut bertambah seiring banyaknya pengajuan.');
    }
}
