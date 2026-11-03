<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Footer halaman publik menyebut cara menghubungi MSC.
 *
 * Yang dipasang hanya email resmi dan tautan ke /bio. Nomor WhatsApp admin
 * adalah nomor pribadi dan sengaja hanya tampil di /bio, sedangkan footer ini
 * muncul di setiap halaman publik.
 */
class SiteFooterTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'Hadi:+62 812-0000-0001,Chika:+62 812-0000-0002,Yosua:chika@jgu.ac.id';

    public function test_every_public_page_says_how_to_reach_msc(): void
    {
        config(['msc.contact_email' => 'media@jgu.ac.id', 'msc.bio.admins' => self::ADMIN]);

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Hubungi MSC')
            ->assertSee('mailto:media@jgu.ac.id', false)
            ->assertSee('Tanya admin MSC')
            ->assertSee('href="'.route('bio').'"', false);
    }

    public function test_the_admin_numbers_stay_on_the_bio_page(): void
    {
        config(['msc.bio.admins' => self::ADMIN]);

        $halaman = $this->get(route('announcements.index'))->assertOk()->getContent();

        foreach (['wa.me', '6281200000001', '812-0000-0002', 'chika@jgu.ac.id'] as $jejak) {
            $this->assertStringNotContainsString($jejak, $halaman, "Kontak pribadi admin bocor ke footer lewat: {$jejak}");
        }
    }

    /**
     * Selama admin belum diisi di .env, /bio tidak punya bagian kontak, jadi
     * tautannya tidak boleh menjanjikan apa-apa.
     */
    public function test_the_admin_link_waits_until_admins_are_set(): void
    {
        config(['msc.bio.admins' => '']);

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Hubungi MSC')
            ->assertDontSee('Tanya admin MSC');
    }
}
