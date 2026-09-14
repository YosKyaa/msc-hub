<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Room;
use App\Support\RequesterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Navigasi portal peminjam.
 *
 * Menunya menetap di sisi kiri dan tersusun mengikuti cara orang berpikir:
 * apa yang bisa diajukan, lalu apa yang sedang berjalan. Tiap menu membawa
 * satu kalimat penjelas, karena namanya saja belum tentu dimengerti orang
 * yang baru pertama membuka portal ini.
 */
class SiteNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** Layanan yang harus selalu bisa dijangkau seorang peminjam. */
    private const LAYANAN = ['Ajukan Konten', 'Booking Ruangan', 'Pinjam Alat'];

    /** Halaman miliknya sendiri, hanya berguna setelah masuk. */
    private const MILIK_SAYA = ['Ringkasan', 'Konten Saya', 'Riwayat Booking'];

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
            'Ringkasan' => $this->asRequester()->get(route('requester.dashboard')),
            'Ajukan Konten' => $this->asRequester()->get(route('request.content')),
            'Pinjam Alat' => $this->asRequester()->get(route('booking.inventory')),
            'Booking Ruangan' => $this->asRequester()->get(route('booking.room')),
        ];
    }

    public function test_every_borrower_page_carries_the_same_sidebar(): void
    {
        foreach ($this->borrowerPages() as $halaman => $response) {
            $response->assertOk();

            foreach ([...self::LAYANAN, ...self::MILIK_SAYA] as $label) {
                $response->assertSee($label, false);
            }

            // Menunya tersusun, bukan sekadar berderet.
            $response->assertSee('Buat Pengajuan', false);
            $response->assertSee('Pantau Pengajuan', false);
            $response->assertSee('Akun saya', false);
        }
    }

    /**
     * Yang diminta: alurnya harus terbaca oleh orang awam. Nama menu saja
     * tidak cukup — tiap menu menjelaskan sendiri apa isinya.
     */
    public function test_each_menu_item_explains_itself_in_plain_indonesian(): void
    {
        $response = $this->asRequester()->get(route('requester.dashboard'))->assertOk();

        $response->assertSee('Minta dibuatkan foto, video, atau desain.');
        $response->assertSee('Pinjam studio atau ruang rapat MSC.');
        $response->assertSee('Kamera, lighting, audio, dan lainnya.');
        $response->assertSee('Sudah sampai mana permintaan konten Anda.');
        $response->assertSee('Ruangan dan alat yang pernah Anda pinjam.');
        $response->assertSee('Semua pengajuan Anda dalam satu halaman.');
    }

    /**
     * Inti keluhan awal: dari halaman pengajuan konten, peminjam tidak punya
     * jalan menuju peminjaman alat maupun ruangan.
     */
    public function test_the_content_page_can_reach_the_borrowing_services(): void
    {
        $response = $this->asRequester()->get(route('request.content'))->assertOk();

        $response->assertSee(route('booking.inventory'), false);
        $response->assertSee(route('booking.room'), false);
    }

    /**
     * Pengunjung yang belum masuk diberi tahu untuk apa ia harus masuk, dan
     * diarahkan ke satu pintu masuk yang sama seperti dari beranda.
     */
    public function test_a_signed_out_visitor_is_told_why_to_sign_in(): void
    {
        $response = $this->get(route('request.content'))->assertOk();

        $response->assertSee('Masuk dengan akun kampus untuk mengajukan dan memantau permintaan Anda.');
        $response->assertSee(route('login.portal', ['redirect' => route('request.content')]), false);
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

    /**
     * Di layar sempit menunya menjadi laci; tombol pembukanya diberi label
     * "Menu", bukan sekadar ikon tiga garis.
     */
    public function test_narrow_screens_get_a_labelled_menu_button(): void
    {
        $response = $this->asRequester()->get(route('requester.dashboard'))->assertOk();

        $response->assertSee('aria-controls="menu-utama"', false);
        $response->assertSee('Buka menu', false);
        $response->assertSee('>Menu<', false);
    }
}
