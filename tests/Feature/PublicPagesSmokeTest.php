<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Announcement;
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
 * Setiap halaman yang bisa dibuka peminjam harus tampil, baik saat sudah
 * masuk maupun belum. Test ini menangkap halaman yang rusak karena perubahan
 * di layout atau komponen bersama — kelas yang hilang, variabel yang tidak
 * pernah dikirim, relasi yang belum dimuat.
 */
class PublicPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private RoomBooking $roomBooking;

    private InventoryBooking $inventoryBooking;

    private ContentRequest $contentRequest;

    private Announcement $announcement;

    protected function setUp(): void
    {
        parent::setUp();

        $room = Room::create([
            'name' => 'Ruang Multimedia MSC',
            'location' => 'Gedung A Lantai 3',
            'capacity' => 20,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'code' => 'CAM-001',
            'name' => 'Kamera Mirrorless',
            'category' => 'camera',
            'condition_status' => 'good',
            'is_active' => true,
        ]);

        $start = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->roomBooking = RoomBooking::create([
            'booking_code' => 'RB-UJI-001',
            'room_id' => $room->id,
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'requester_phone' => '081234567890',
            'supervisor_name' => 'Dr. Djoko Susilo',
            'unit' => 'HIMATIF',
            'purpose' => 'Rapat koordinasi',
            'attendees' => 5,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'status' => BookingStatus::PENDING,
        ]);

        $this->inventoryBooking = InventoryBooking::create([
            'booking_code' => 'IB-UJI-001',
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'requester_phone' => '081234567890',
            'unit' => 'HIMATIF',
            'purpose' => 'Dokumentasi acara',
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(4),
            'status' => BookingStatus::PENDING,
        ]);

        $this->inventoryBooking->items()->attach($item->id, ['quantity' => 1]);

        $this->contentRequest = ContentRequest::create([
            'request_code' => 'CR-UJI-001',
            'requester_name' => 'Budi Santoso',
            'requester_email' => 'budi@student.jgu.ac.id',
            'requester_type' => 'student',
            'unit' => 'HIMATIF',
            'phone' => '081234567890',
            'content_type' => 'design_poster',
            'purpose' => 'Publikasi seminar',
            'deadline' => $start->copy()->addWeek(),
            'status' => 'incoming',
        ]);

        $this->announcement = Announcement::create([
            'title' => 'Jadwal Peminjaman Studio',
            'summary' => 'Studio tutup selama pekan ujian.',
            'content' => 'Studio MSC tutup selama pekan ujian akhir semester.',
            'category' => 'announcement',
            'published_at' => Carbon::now()->subDay(),
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

    /**
     * @return array<string, string>
     */
    private function pages(): array
    {
        return [
            'Beranda' => route('landing'),
            'Pengumuman' => route('announcements.index'),
            'Detail pengumuman' => route('announcements.show', $this->announcement->slug),
            'Ajukan konten' => route('request.content'),
            'Status konten' => route('request.status'),
            'Detail konten' => route('request.status.detail', $this->contentRequest->request_code),
            'Pinjam alat' => route('booking.inventory'),
            'Booking ruangan' => route('booking.room'),
            'Riwayat booking' => route('my.bookings'),
            'Detail booking ruangan' => route('my.bookings.detail', ['type' => 'room', 'code' => $this->roomBooking->booking_code]),
            'Detail booking alat' => route('my.bookings.detail', ['type' => 'inventory', 'code' => $this->inventoryBooking->booking_code]),
            'Sukses booking' => route('booking.success', ['type' => 'room', 'code' => $this->roomBooking->booking_code]),
        ];
    }

    public function test_every_page_renders_for_a_signed_in_requester(): void
    {
        foreach ($this->pages() as $nama => $url) {
            $response = $this->signIn()->get($url);

            $this->assertSame(200, $response->getStatusCode(), "Halaman {$nama} gagal tampil ({$url}).");
        }
    }

    /**
     * Halaman pengumuman dulu berupa dokumen HTML berdiri sendiri dengan
     * header dan menunya sendiri, sehingga labelnya pun berbeda dari halaman
     * lain ("Pinjam Inventaris" alih-alih "Pinjam Alat").
     */
    public function test_every_page_wears_the_shared_header(): void
    {
        foreach ($this->pages() as $nama => $url) {
            if ($url === route('landing')) {
                continue; // Beranda punya header pemasarannya sendiri.
            }

            $this->signIn()->get($url)->assertSee('Layanan Media &amp; Peminjaman', false);
        }
    }

    /**
     * Formulir sempat memuat SweetAlert untuk konfirmasi kedua, di samping
     * dialog milik sendiri — dua rancangan berbeda untuk satu keputusan, dan
     * yang lama terikat ke formulir keluar di header lewat
     * document.querySelector('form').
     */
    public function test_only_one_confirmation_dialog_is_shipped(): void
    {
        $forms = [
            'Ajukan konten' => route('request.content'),
            'Pinjam alat' => route('booking.inventory'),
            'Booking ruangan' => route('booking.room'),
        ];

        foreach ($forms as $nama => $url) {
            $response = $this->signIn()->get($url)->assertOk();

            $response->assertDontSee('sweetalert', false);
            $response->assertDontSee("querySelector('form')", false);

            // Dialog yang benar-benar dipakai tetap ada.
            $response->assertSee('Ya, ', false);
        }
    }

    /**
     * Daftar riwayat menampilkan jumlah alat dan nama ruangan tiap baris.
     * Tanpa pemuatan relasi di muka, tiap booking menambah satu kueri.
     */
    public function test_the_bookings_history_does_not_query_once_per_row(): void
    {
        $hitung = function (): int {
            $n = 0;
            DB::listen(function () use (&$n): void {
                $n++;
            });

            $this->signIn()->get(route('my.bookings'))->assertOk();

            return $n;
        };

        $sedikit = $hitung();

        for ($i = 0; $i < 8; $i++) {
            $this->roomBooking->replicate()->fill(['booking_code' => 'RB-UJI-1'.$i])->save();
            $this->inventoryBooking->replicate()->fill(['booking_code' => 'IB-UJI-1'.$i])->save();
        }

        $this->assertLessThanOrEqual($sedikit, $hitung(),
            'Jumlah kueri ikut bertambah seiring jumlah booking.');
    }

    /**
     * Pengunjung yang belum masuk tidak boleh menemui 500; ia boleh melihat
     * halamannya atau diarahkan untuk masuk, tetapi bukan halaman galat.
     */
    public function test_no_page_breaks_for_a_signed_out_visitor(): void
    {
        foreach ($this->pages() as $nama => $url) {
            $status = $this->get($url)->getStatusCode();

            $this->assertContains($status, [200, 302], "Halaman {$nama} menghasilkan {$status} ({$url}).");
        }
    }
}
