<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Filament\Resources\RoomBookingResource;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Notifications\NewBookingNotification;
use App\Support\RequesterSession;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lonceng pemberitahuan di panel.
 *
 * Lonceng dan email dulu sama-sama dititipkan ke antrean, sehingga tanpa
 * pekerja antrean lonceng tidak pernah berbunyi: pemberitahuan baru muncul
 * berjam-jam kemudian ketika antreannya kebetulan dijalankan. Menulis satu
 * baris ke basis data murah; yang lambat adalah SMTP, dan hanya itu yang
 * pantas menunggu.
 */
class PanelNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function room(): Room
    {
        return Room::create([
            'name' => 'Ruang Multimedia MSC',
            'location' => 'Gedung A Lantai 3',
            'capacity' => 20,
            'is_active' => true,
        ]);
    }

    /**
     * Lingkungan tes memakai antrean `sync`, sehingga apa pun berjalan inline
     * dan lonceng tampak berfungsi walau sebenarnya menunggu pekerja. Antrean
     * sungguhan dipasang dulu supaya perbedaannya nyata.
     */
    private function withRealQueue(): void
    {
        config(['queue.default' => 'database']);
    }

    private function submitBooking(): void
    {
        $start = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->withSession([RequesterSession::KEY => [
            'google_id' => '1234567890',
            'name' => 'Budi Santoso',
            'email' => 'budi@student.jgu.ac.id',
            'type' => 'student',
        ]])->post(route('booking.room.submit'), [
            'requester_name' => 'Budi Santoso',
            'requester_phone' => '081234567890',
            'unit' => 'HIMATIF',
            'purpose' => 'Rapat koordinasi',
            'attendees' => 5,
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $start->copy()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertRedirect();
    }

    /**
     * Inti perbaikannya: pengajuan baru langsung terlihat di lonceng, tanpa
     * satu pun pekerja antrean berjalan.
     */
    public function test_a_new_booking_reaches_the_bell_without_a_queue_worker(): void
    {
        $this->withRealQueue();

        $staff = $this->staff();
        $this->room();

        $this->submitBooking();

        // Lonceng sudah berisi, padahal belum satu pun job dikerjakan.
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertGreaterThan(0, DB::table('jobs')->count());
    }

    public function test_the_bell_entry_says_what_came_in(): void
    {
        $this->withRealQueue();

        $staff = $this->staff();
        $this->room();

        $this->submitBooking();

        $isi = json_encode($staff->notifications()->sole()->data);

        $this->assertStringContainsString('Peminjaman Ruangan Baru', (string) $isi);
        $this->assertStringContainsString('Budi Santoso', (string) $isi);
        $this->assertStringContainsString(RoomBooking::sole()->booking_code, (string) $isi);
    }

    /**
     * Tombolnya harus membuka pengajuan yang dimaksud. Dulu semua
     * pemberitahuan mengarah ke beranda panel, jadi staf masih harus mencari
     * sendiri yang mana — dan membukanya di tab baru, meninggalkan tab lama
     * menumpuk.
     */
    public function test_the_bell_links_straight_to_the_submission(): void
    {
        $this->withRealQueue();

        $staff = $this->staff();
        $this->room();

        $this->submitBooking();

        $data = $staff->notifications()->sole()->data;
        $aksi = $data['actions'][0];

        $this->assertSame(
            RoomBookingResource::getUrl('view', ['record' => RoomBooking::sole()]),
            $aksi['url'],
        );
        $this->assertFalse($aksi['shouldOpenUrlInNewTab']);
        $this->assertSame('Buka pengajuan', $aksi['label']);
    }

    /**
     * Email tetap dititipkan ke antrean: SMTP lambat, dan jeda bertingkatnya
     * memang dipasang untuk menahan lajunya.
     */
    public function test_the_slow_email_is_still_left_to_the_queue(): void
    {
        $this->withRealQueue();

        $this->staff();
        $this->room();

        $this->submitBooking();

        // Emailnya menunggu di antrean; hanya itu yang pantas menunggu.
        $this->assertGreaterThan(0, DB::table('jobs')->count());
    }

    /**
     * Jeda itu milik email saja; lonceng tidak ada urusannya dengan laju SMTP.
     */
    public function test_only_the_email_carries_the_stagger_delay(): void
    {
        $booking = RoomBooking::create([
            'booking_code' => 'RB-UJI-001',
            'room_id' => $this->room()->id,
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'unit' => 'HIMATIF',
            'purpose' => 'Rapat koordinasi',
            'attendees' => 5,
            'start_at' => Carbon::now()->addDay(),
            'end_at' => Carbon::now()->addDay()->addHours(2),
            'status' => BookingStatus::PENDING,
        ]);

        $notification = (new NewBookingNotification($booking, 'ROOM'))->delay(Carbon::now()->addSeconds(30));

        $jeda = $notification->withDelay($this->staff());

        $this->assertNull($jeda['database']);
        $this->assertNotNull($jeda['mail']);
        $this->assertSame('sync', $notification->viaConnections()['database']);
    }

    /**
     * Tanpa server websocket, polling inilah yang membuat lonceng terasa
     * hidup — dan intervalnya harus disebut, bukan dibiarkan menebak.
     */
    public function test_the_panel_polls_for_new_notifications(): void
    {
        $panel = filament()->getPanel('admin');

        $this->assertTrue($panel->hasDatabaseNotifications());
        $this->assertSame('30s', $panel->getDatabaseNotificationsPollingInterval());
    }

    /**
     * Peminjaman alat menempuh jalur yang sama.
     */
    public function test_an_inventory_request_also_rings_the_bell(): void
    {
        $this->withRealQueue();

        $staff = $this->staff();

        $item = InventoryItem::create([
            'code' => 'CAM-001',
            'name' => 'Kamera Mirrorless',
            'category' => 'camera',
            'condition_status' => 'good',
            'is_active' => true,
        ]);

        $start = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->withSession([RequesterSession::KEY => [
            'google_id' => '1234567890',
            'name' => 'Budi Santoso',
            'email' => 'budi@student.jgu.ac.id',
            'type' => 'student',
        ]])->post(route('booking.inventory.submit'), [
            'requester_name' => 'Budi Santoso',
            'requester_phone' => '081234567890',
            'unit' => 'HIMATIF',
            'purpose' => 'Dokumentasi acara',
            'items' => [$item->id],
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $start->copy()->addHours(4)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertSame(1, $staff->notifications()->count());
    }
}
