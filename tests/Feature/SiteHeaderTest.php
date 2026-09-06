<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Room;
use App\Support\RequesterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Header peminjam.
 *
 * Dulu tiap layout menyusun navigasinya sendiri, sehingga menunya berubah
 * susunan ketika peminjam berpindah antara pengajuan konten dan peminjaman —
 * dan dari halaman konten ia sama sekali tidak bisa mencapai peminjaman.
 */
class SiteHeaderTest extends TestCase
{
    use RefreshDatabase;

    /** Layanan yang harus selalu bisa dijangkau seorang peminjam. */
    private const LAYANAN = ['Ajukan Konten', 'Pinjam Alat', 'Booking Ruangan'];

    /** Halaman miliknya sendiri, hanya berguna setelah masuk. */
    private const MILIK_SAYA = ['Konten Saya', 'Riwayat Booking'];

    protected function setUp(): void
    {
        parent::setUp();

        Room::create([
            'name' => 'Ruang Multimedia MSC',
            'location' => 'Gedung A Lantai 3',
            'capacity' => 20,
            'is_active' => true,
        ]);

        InventoryItem::create([
            'code' => 'CAM-001',
            'name' => 'Kamera Mirrorless',
            'category' => 'camera',
            'condition_status' => 'good',
            'is_active' => true,
        ]);
    }

    private function asRequester(): static
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
     * @return array<string, TestResponse>
     */
    private function borrowerPages(): array
    {
        return [
            'Ajukan Konten' => $this->asRequester()->get(route('request.content')),
            'Pinjam Alat' => $this->asRequester()->get(route('booking.inventory')),
            'Booking Ruangan' => $this->asRequester()->get(route('booking.room')),
        ];
    }

    public function test_every_borrower_page_offers_the_same_menu(): void
    {
        foreach ($this->borrowerPages() as $halaman => $response) {
            $response->assertOk();

            foreach ([...self::LAYANAN, ...self::MILIK_SAYA] as $label) {
                $response->assertSee($label, false);
            }

            // Menu akun ikut hadir di mana pun, bukan hanya di portal booking.
            $response->assertSee('Akun saya', false);
        }
    }

    /**
     * Inti keluhannya: dari halaman pengajuan konten, peminjam tidak punya
     * jalan menuju peminjaman alat maupun ruangan.
     */
    public function test_the_content_page_can_reach_the_borrowing_services(): void
    {
        $response = $this->asRequester()->get(route('request.content'))->assertOk();

        $response->assertSee(route('booking.inventory'), false);
        $response->assertSee(route('booking.room'), false);
    }

    /**
     * Sebelumnya header tidak menampilkan cara masuk sama sekali; peminjam
     * hanya terlempar ke Google begitu menyentuh tautan yang butuh login.
     */
    public function test_a_signed_out_visitor_is_offered_a_way_in(): void
    {
        $response = $this->get(route('request.content'))->assertOk();

        $response->assertSee('Masuk dengan Google');
        $response->assertSee(route('google.redirect', ['redirect' => route('request.content')]), false);
    }

    public function test_private_pages_are_not_advertised_before_signing_in(): void
    {
        $response = $this->get(route('request.content'))->assertOk();

        foreach (self::MILIK_SAYA as $label) {
            $response->assertDontSee($label, false);
        }

        // Layanannya sendiri tetap terlihat supaya pengunjung tahu isinya.
        foreach (self::LAYANAN as $label) {
            $response->assertSee($label, false);
        }
    }

    public function test_the_page_being_viewed_is_marked_as_current(): void
    {
        $this->asRequester()
            ->get(route('booking.room'))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }

    public function test_the_logo_leads_home(): void
    {
        $this->asRequester()
            ->get(route('booking.room'))
            ->assertOk()
            ->assertSee('href="'.route('landing').'"', false);
    }
}
