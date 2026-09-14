<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RequesterSession;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Satu pintu masuk.
 *
 * Beranda dulu menyodorkan dua tombol berdampingan — "Masuk" untuk peminjam
 * dan "Panel Admin" — sehingga pengunjung harus tahu lebih dulu dirinya
 * termasuk yang mana. Sekarang keduanya berangkat dari /masuk.
 */
class LoginPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_portal_offers_exactly_two_ways_in(): void
    {
        $response = $this->get(route('login.portal'))->assertOk();

        $response->assertSee('Masuk sebagai');
        $response->assertSee('Mahasiswa / Pengaju');
        $response->assertSee('Admin MSC');

        // Masing-masing menuju cara masuknya sendiri.
        $response->assertSee(route('filament.admin.auth.login'), false);
        $response->assertSee('/google/redirect', false);
    }

    /**
     * Yang diminta: beranda hanya menawarkan satu tombol masuk, bukan dua
     * yang berdampingan.
     */
    public function test_the_landing_page_points_at_the_portal_and_nowhere_else(): void
    {
        $response = $this->get(route('landing'))->assertOk();

        $response->assertSee(route('login.portal'), false);
        $response->assertDontSee('Panel Admin');
        $response->assertDontSee(route('auth.google.redirect'), false);
    }

    public function test_the_portal_remembers_where_the_visitor_wanted_to_go(): void
    {
        $this->get(route('login.portal', ['redirect' => '/borrow/inventory']))
            ->assertOk()
            ->assertSee(urlencode(url('/borrow/inventory')), false);
    }

    /**
     * Tujuan hanya boleh internal — parameter ?redirect= tidak boleh dipakai
     * melempar pengunjung ke domain lain setelah masuk.
     */
    public function test_an_outside_destination_is_dropped(): void
    {
        $this->get(route('login.portal', ['redirect' => 'https://contoh-jahat.test/curi']))
            ->assertOk()
            ->assertDontSee('contoh-jahat.test', false);
    }

    public function test_a_signed_in_requester_is_not_asked_to_choose_again(): void
    {
        $this->withSession([RequesterSession::KEY => [
            'google_id' => '1234567890',
            'name' => 'Budi Santoso',
            'email' => 'budi@student.jgu.ac.id',
            'type' => 'student',
        ]])->get(route('login.portal'))->assertRedirect(route('landing'));
    }

    public function test_a_signed_in_admin_goes_straight_to_the_panel(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get(route('login.portal'))->assertRedirect('/panel');
    }

    /**
     * Yang salah pintu tidak boleh buntu di halaman login panel.
     */
    public function test_the_panel_login_offers_a_way_back_to_the_portal(): void
    {
        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('Masuk sebagai mahasiswa/pengaju')
            ->assertSee(route('login.portal'), false);
    }
}
