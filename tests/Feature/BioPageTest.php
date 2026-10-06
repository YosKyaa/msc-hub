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

    // --------------------------------------------------------- kontak admin

    /**
     * Orang yang bingung mengisi formulir lebih cepat tertolong dengan
     * bertanya kepada orang. Ketiganya karena itu disebut dengan nama, bukan
     * disembunyikan di balik satu tombol "kontak" yang tidak jelas menuju siapa.
     */
    public function test_the_three_admins_are_named(): void
    {
        $response = $this->get(route('bio'))->assertOk();

        $response->assertSee('Butuh bantuan?');
        $response->assertSee('Hadi');
        $response->assertSee('Chika');
        $response->assertSee('Yosua');

        // Bawaannya nomor WhatsApp ketiganya, jadi halaman ini berguna tanpa
        // perlu menyetel apa pun di server.
        $response->assertSee('https://wa.me/', false);
        $response->assertSee('WhatsApp');
    }

    /**
     * Nomor pribadi ketiga admin sengaja hanya ada di sini.
     *
     * Beranda dibagikan jauh lebih luas dan terindeks mesin pencari;
     * memasang nomor di sana berarti menyebarkannya ke tempat yang tidak
     * diminta siapa pun.
     */
    public function test_the_admin_numbers_never_reach_the_landing_page(): void
    {
        $beranda = $this->get(route('landing'))->assertOk()->getContent();

        foreach (['wa.me', '6282278775003', '6287771412625', '6282112187810'] as $jejak) {
            $this->assertStringNotContainsString($jejak, $beranda,
                "Nomor admin bocor ke beranda lewat: {$jejak}");
        }
    }

    public function test_a_phone_number_becomes_a_whatsapp_link(): void
    {
        config(['msc.bio.admins' => 'Hadi:+62 812-3456-7890']);

        $this->get(route('bio'))
            ->assertOk()
            // Spasi dan strip dibuang: orang menuliskan nomor apa adanya.
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertSee('WhatsApp');
    }

    public function test_an_address_with_an_at_sign_becomes_an_email_link(): void
    {
        config(['msc.bio.admins' => 'Chika:chika@jgu.ac.id']);

        $this->get(route('bio'))
            ->assertOk()
            ->assertSee('mailto:chika@jgu.ac.id', false)
            ->assertSee('Email');
    }

    /**
     * Keduanya boleh bercampur, karena nomor ketiga admin tidak selalu
     * terkumpul sekaligus.
     */
    public function test_phone_and_email_may_be_mixed(): void
    {
        config(['msc.bio.admins' => 'Hadi:628111,Chika:chika@jgu.ac.id']);

        $response = $this->get(route('bio'))->assertOk();

        $response->assertSee('https://wa.me/628111', false);
        $response->assertSee('mailto:chika@jgu.ac.id', false);
    }

    /**
     * Baris yang tidak lengkap dilewati, bukan menghasilkan tombol kosong
     * yang tidak menuju ke mana-mana.
     */
    public function test_an_incomplete_entry_is_skipped(): void
    {
        config(['msc.bio.admins' => 'Hadi:628111,TanpaKontak,:628222, ,Yosua:628333']);

        $response = $this->get(route('bio'))->assertOk();

        $response->assertSee('https://wa.me/628111', false);
        $response->assertSee('https://wa.me/628333', false);
        $response->assertDontSee('TanpaKontak');
        $response->assertDontSee('https://wa.me/628222', false);
    }

    public function test_the_section_disappears_when_no_admin_is_configured(): void
    {
        config(['msc.bio.admins' => '']);

        $this->get(route('bio'))
            ->assertOk()
            ->assertDontSee('Butuh bantuan?');
    }

    /**
     * Tautan keluar dibuka di tab baru, supaya halaman ini tidak hilang dari
     * riwayat orang yang sekadar membuka WhatsApp lalu ingin kembali.
     */
    public function test_outbound_links_open_in_a_new_tab_safely(): void
    {
        config(['msc.bio.admins' => 'Hadi:628111']);

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
