<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman tautan ringkas di /bio.
 *
 * Alamat yang ditaruh di bio Instagram, jadi hampir semua yang membukanya
 * datang dari ponsel, sekali, dan belum tentu tahu MSC melayani apa saja.
 * Satu tautan yang mati di sini berarti calon pengaju berhenti di situ dan
 * tidak pernah sampai ke formulirnya.
 */
class BioPageTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------- tiga layanan utama

    /**
     * Alasan halaman ini ada: pengunjung memilih mau mengajukan apa.
     */
    public function test_it_offers_every_service_in_one_tap(): void
    {
        $response = $this->get(route('bio'))->assertOk();

        $response->assertSee(route('request.content'), false);
        $response->assertSee(route('booking.room'), false);
        $response->assertSee(route('booking.inventory'), false);
    }

    /**
     * Nama menu saja belum tentu dimengerti orang yang baru pertama ke sini,
     * jadi tiap tombol menyebutkan apa yang terjadi kalau ditekan.
     */
    public function test_every_link_explains_itself(): void
    {
        $response = $this->get(route('bio'))->assertOk();

        $response->assertSee('Minta dibuatkan foto, video, atau desain.');
        $response->assertSee('Pinjam studio atau ruang rapat MSC.');
        $response->assertSee('Kamera, lighting, audio, dan lainnya.');
    }

    /**
     * Cek status menuntut masuk dengan Google. Disebutkan di muka supaya
     * halaman login tidak terasa seperti penghalang mendadak.
     */
    public function test_the_pages_that_need_a_sign_in_say_so_beforehand(): void
    {
        $this->get(route('bio'))
            ->assertOk()
            ->assertSee('Masuk dulu dengan akun JGU Anda.')
            ->assertSee(route('request.status'), false)
            ->assertSee(route('my.bookings'), false);
    }

    // ---------------------------------------------------- bentuk halaman

    /**
     * Berdiri sendiri: menu portal justru menambah pilihan yang tidak
     * dibutuhkan orang yang baru datang dari Instagram.
     */
    public function test_it_stands_on_its_own_without_the_portal_menu(): void
    {
        $response = $this->get(route('bio'))->assertOk();

        $response->assertDontSee('Navigasi utama');
        $response->assertDontSee('id="menu-ponsel"', false);
    }

    /**
     * Dibuka hampir seluruhnya dari ponsel.
     */
    public function test_it_is_built_for_a_phone(): void
    {
        $this->get(route('bio'))
            ->assertOk()
            ->assertSee('width=device-width', false)
            ->assertSee('max-w-md', false);
    }

    /**
     * Tautannya dibagikan di Instagram dan WhatsApp, jadi pratinjaunya harus
     * menjelaskan dirinya sendiri — bukan sekadar alamat mentah.
     */
    public function test_a_shared_link_carries_a_preview(): void
    {
        $response = $this->get(route('bio'))->assertOk();

        $response->assertSee('<meta property="og:title"', false);
        $response->assertSee('<meta property="og:image"', false);
        $response->assertSee('<meta name="twitter:card"', false);
        $response->assertSee('rel="canonical"', false);
    }

    public function test_search_engines_may_index_it(): void
    {
        $this->get(route('bio'))
            ->assertOk()
            ->assertSee('index, follow', false);
    }

    /**
     * Orang mencarinya lewat mesin pencari juga, bukan hanya lewat bio.
     */
    public function test_it_is_listed_in_the_sitemap(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('bio'), false);
    }

    // ------------------------------------------------------ media sosial

    /**
     * Akun yang tidak diisi tidak boleh ditebak-tebak lalu tercetak di
     * halaman publik.
     */
    public function test_an_unset_social_account_is_simply_not_shown(): void
    {
        config(['msc.bio.instagram' => '', 'msc.bio.whatsapp' => '']);

        $response = $this->get(route('bio'))->assertOk();

        $response->assertDontSee('instagram.com', false);
        $response->assertDontSee('wa.me', false);
    }

    public function test_a_configured_account_is_shown(): void
    {
        config([
            'msc.bio.instagram' => '@msc.jgu',
            'msc.bio.whatsapp' => '+62 812-3456-7890',
        ]);

        $response = $this->get(route('bio'))->assertOk();

        // Tanda @ dan tanda baca nomor dibuang saat menyusun alamatnya.
        $response->assertSee('https://instagram.com/msc.jgu', false);
        $response->assertSee('https://wa.me/6281234567890', false);
    }

    /**
     * Tautan keluar dibuka di tab baru, supaya halaman ini tidak hilang dari
     * riwayat orang yang sekadar mampir ke Instagram lalu ingin kembali.
     */
    public function test_outbound_links_open_in_a_new_tab_safely(): void
    {
        config(['msc.bio.instagram' => '@msc.jgu']);

        $this->get(route('bio'))
            ->assertOk()
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_the_tagline_comes_from_configuration(): void
    {
        config(['msc.bio.tagline' => 'Kalimat percobaan untuk bio.']);

        $this->get(route('bio'))
            ->assertOk()
            ->assertSee('Kalimat percobaan untuk bio.');
    }
}
