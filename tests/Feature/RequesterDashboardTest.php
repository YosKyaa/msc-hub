<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\ContentRequest;
use App\Models\InventoryBooking;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Support\RequesterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dasbor peminjam.
 *
 * Halaman pertama setelah masuk: di sinilah ia memilih layanan dan melihat
 * pengajuannya sendiri, alih-alih harus menebak menu mana yang dibuka.
 */
class RequesterDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Ruang Multimedia MSC',
            'location' => 'Gedung A Lantai 3',
            'capacity' => 20,
            'is_active' => true,
        ]);

        $this->item = InventoryItem::create([
            'code' => 'CAM-001',
            'name' => 'Kamera Mirrorless',
            'category' => 'camera',
            'condition_status' => 'good',
            'is_active' => true,
        ]);
    }

    private function signIn(): static
    {
        $this->withSession([RequesterSession::KEY => [
            'google_id' => '1234567890',
            'name' => 'Budi Santoso',
            'email' => 'budi@student.jgu.ac.id',
            'type' => 'student',
        ]]);

        return $this;
    }

    private function roomBooking(string $code, string $email = 'budi@student.jgu.ac.id'): RoomBooking
    {
        $start = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        return RoomBooking::create([
            'booking_code' => $code,
            'room_id' => $this->room->id,
            'requester_name' => 'Budi Santoso',
            'requester_email' => $email,
            'unit' => 'HIMATIF',
            'purpose' => 'Rapat koordinasi',
            'attendees' => 5,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'status' => BookingStatus::PENDING,
        ]);
    }

    private function inventoryBooking(string $code): InventoryBooking
    {
        $start = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        $booking = InventoryBooking::create([
            'booking_code' => $code,
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'unit' => 'HIMATIF',
            'purpose' => 'Dokumentasi acara',
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(4),
            'status' => BookingStatus::PENDING,
        ]);

        $booking->items()->attach($this->item->id, ['quantity' => 1]);

        return $booking;
    }

    private function contentRequest(string $code): ContentRequest
    {
        return ContentRequest::create([
            'request_code' => $code,
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'requester_type' => 'student',
            'unit' => 'HIMATIF',
            'content_type' => 'design_poster',
            'purpose' => 'Publikasi seminar',
            'deadline' => Carbon::now()->addWeek(),
            'status' => 'incoming',
        ]);
    }

    // ------------------------------------------------------ pilihan layanan

    /**
     * Alasan utama halaman ini ada: setelah masuk, peminjam memilih mau
     * mengajukan apa.
     */
    public function test_the_dashboard_offers_every_service_as_a_choice(): void
    {
        $response = $this->signIn()->get(route('requester.dashboard'))->assertOk();

        $response->assertSee('Mau mengajukan apa?');
        $response->assertSee(route('request.content'), false);
        $response->assertSee(route('booking.room'), false);
        $response->assertSee(route('booking.inventory'), false);
    }

    public function test_a_visitor_who_is_not_signed_in_is_sent_to_the_portal(): void
    {
        $this->get(route('requester.dashboard'))
            ->assertRedirect(route('login.portal', ['redirect' => route('requester.dashboard')]));
    }

    /**
     * Ajakan di beranda semuanya butuh login, jadi mengarahkannya langsung ke
     * formulir hanya berujung pentalan ke Google.
     */
    public function test_the_landing_hero_sends_visitors_through_the_portal(): void
    {
        $response = $this->get(route('landing'))->assertOk();

        $response->assertSee(route('login.portal'), false);
        $response->assertDontSee('Panel Admin');
    }

    /**
     * Masuk tanpa tujuan khusus berakhir di dasbor, bukan di salah satu
     * formulir.
     */
    public function test_the_portal_lands_a_requester_on_the_dashboard(): void
    {
        $this->signIn()
            ->get(route('login.portal'))
            ->assertRedirect(route('requester.dashboard'));
    }

    // -------------------------------------------------- pengajuan sendiri

    public function test_the_dashboard_shows_the_requester_own_submissions(): void
    {
        $this->contentRequest('CR-UJI-001');
        $this->roomBooking('RB-UJI-001');
        $this->inventoryBooking('IB-UJI-001');

        $response = $this->signIn()->get(route('requester.dashboard'))->assertOk();

        $response->assertSee('CR-UJI-001');
        $response->assertSee('RB-UJI-001');
        $response->assertSee('IB-UJI-001');
        $response->assertSee('Ruang Multimedia MSC');
    }

    public function test_submissions_belonging_to_someone_else_never_appear(): void
    {
        $this->roomBooking('RB-ORANG-LAIN', 'orang.lain@student.jgu.ac.id');

        $this->signIn()
            ->get(route('requester.dashboard'))
            ->assertOk()
            ->assertDontSee('RB-ORANG-LAIN');
    }

    public function test_a_requester_with_nothing_yet_is_pointed_at_what_to_do(): void
    {
        $response = $this->signIn()->get(route('requester.dashboard'))->assertOk();

        $response->assertSee('Belum ada pengajuan konten');
        $response->assertSee('Belum ada peminjaman');
    }

    /**
     * Daftar di dasbor menyebut nama ruangan dan jumlah alat tiap baris.
     * Tanpa pemuatan relasi di muka, tiap pengajuan menambah satu kueri.
     */
    public function test_the_dashboard_does_not_query_once_per_row(): void
    {
        $hitung = function (): int {
            $n = 0;
            DB::listen(function () use (&$n): void {
                $n++;
            });

            $this->signIn()->get(route('requester.dashboard'))->assertOk();

            return $n;
        };

        $this->roomBooking('RB-UJI-001');
        $this->inventoryBooking('IB-UJI-001');
        $sedikit = $hitung();

        for ($i = 0; $i < 6; $i++) {
            $this->roomBooking('RB-UJI-1'.$i);
            $this->inventoryBooking('IB-UJI-1'.$i);
        }

        $this->assertLessThanOrEqual($sedikit, $hitung(),
            'Jumlah kueri ikut bertambah seiring jumlah pengajuan.');
    }
}
