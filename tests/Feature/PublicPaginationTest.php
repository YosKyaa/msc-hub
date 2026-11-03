<?php

namespace Tests\Feature;

use App\Filament\Resources\ParticipantResource\Pages\ListParticipants;
use App\Models\Announcement;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Paginasi daftar penerima sertifikat dan pengumuman.
 *
 * Paginasi bawaan Laravel rusak di ponsel: tombolnya bertuliskan
 * "pagination.previous" dan "pagination.next" mentah, karena aplikasi ini
 * berbahasa Indonesia tanpa berkas terjemahan paginasi, dan berubah abu-abu
 * gelap bila ponselnya memakai mode gelap. Di layar lebar ia berbahasa
 * Inggris.
 */
class PublicPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function assertIndonesianPagination(TestResponse $response): TestResponse
    {
        return $response->assertOk()
            ->assertSee('Sebelumnya')
            ->assertSee('Berikutnya')
            ->assertDontSee('pagination.previous')
            ->assertDontSee('pagination.next')
            ->assertDontSee('Showing')
            ->assertDontSee('dark:bg-gray', false);
    }

    private function recipientList(int $jumlah): CertificateEvent
    {
        $event = CertificateEvent::factory()->published()->create(['recipients_public' => true]);

        Certificate::factory()->count($jumlah)->create(['certificate_event_id' => $event->id]);

        return $event;
    }

    public function test_the_recipient_list_is_paged_in_indonesian(): void
    {
        $event = $this->recipientList(31);

        $this->assertIndonesianPagination($this->get($event->publicRecipientsUrl()))
            ->assertSee('Halaman 1 dari 2')
            ->assertSee('Menampilkan 1&ndash;30 dari 31', false);

        $this->get($event->publicRecipientsUrl().'?page=2')
            ->assertOk()
            ->assertSee('Halaman 2 dari 2')
            ->assertSee('Menampilkan 31&ndash;31 dari 31', false);
    }

    public function test_the_announcements_are_paged_in_indonesian(): void
    {
        Announcement::factory()->count(11)->create();

        $this->assertIndonesianPagination($this->get(route('announcements.index')))
            ->assertSee('Menampilkan 1&ndash;10 dari 11', false);
    }

    /**
     * Livewire mengganti tampilan paginasi bawaan selama tabel panel
     * dirender, dan di dalam satu proses tidak selalu mengembalikannya. Halaman
     * publik karena itu menyebut tampilannya sendiri, bukan memakai bawaan.
     */
    public function test_a_panel_table_rendered_earlier_does_not_change_it(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($admin->fresh())->test(ListParticipants::class)->assertOk();

        $this->assertIndonesianPagination($this->get($this->recipientList(31)->publicRecipientsUrl()));
    }
}
