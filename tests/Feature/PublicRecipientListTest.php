<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource\Pages\ListCertificateEvents;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Daftar penerima sertifikat untuk umum.
 *
 * Sebagian kegiatan ingin daftar penerimanya bisa dilihat siapa saja, seperti
 * pengumuman kelulusan di papan; sebagian lain tidak. Keputusannya dipegang
 * admin per kegiatan, dan bawaannya tertutup — membuka daftar nama orang
 * tidak boleh terjadi tanpa seseorang memilihnya.
 */
class PublicRecipientListTest extends TestCase
{
    use RefreshDatabase;

    private function event(bool $terbuka = true, string $status = 'published'): CertificateEvent
    {
        return CertificateEvent::factory()->create([
            'name' => 'Seminar Kewirausahaan',
            'slug' => 'seminar-kewirausahaan',
            'status' => $status,
            'recipients_public' => $terbuka,
        ]);
    }

    private function issue(CertificateEvent $event, string $nama, string $email): Certificate
    {
        $participation = CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory()->create(['name' => $nama, 'email' => $email]),
        ]);

        return app(CertificateIssuer::class)->issue($participation);
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    // ----------------------------------------------------- saklarnya

    /**
     * Inti permintaannya: daftarnya terbuka hanya bila admin membukanya.
     */
    public function test_the_list_is_closed_until_an_admin_opens_it(): void
    {
        $event = $this->event(terbuka: false);
        $this->issue($event, 'Budi Santoso', 'budi@student.jgu.ac.id');

        // Tertutup: tidak ada satu pun nama yang bocor.
        $this->get($event->publicRecipientsUrl())
            ->assertForbidden()
            ->assertDontSee('Budi Santoso');

        $event->update(['recipients_public' => true]);

        $this->get($event->publicRecipientsUrl())->assertOk()->assertSee('Budi Santoso');
    }

    /**
     * Yang membuka tautannya umumnya sudah memegangnya dari penyelenggara,
     * jadi halaman kosong bertulisan 404 tidak menolong siapa pun. Ia berhak
     * tahu apa yang terjadi dan ke mana harus bertanya.
     */
    public function test_a_closed_list_explains_itself_instead_of_showing_404(): void
    {
        $event = $this->event(terbuka: false);

        $response = $this->get($event->publicRecipientsUrl())->assertForbidden();

        $response->assertSee('Daftar penerima belum dibuka');
        $response->assertSee('Seminar Kewirausahaan');
        $response->assertSee(config('msc.contact_email'));
        $response->assertSee('Hubungi tim Media &amp; Strategic Communications', false);
    }

    /**
     * Pemilik sertifikat tidak perlu daftar ini sama sekali untuk memeriksa
     * miliknya sendiri, dan itu disebutkan supaya ia tidak menunggu sia-sia.
     */
    public function test_a_closed_list_points_owners_at_their_own_certificate(): void
    {
        $event = $this->event(terbuka: false);

        $this->get($event->publicRecipientsUrl())
            ->assertForbidden()
            ->assertSee('Sudah punya sertifikatnya?')
            ->assertSee('kode QR');
    }

    public function test_a_brand_new_event_starts_closed(): void
    {
        // Dibaca ulang dari basis data: nilainya datang dari default kolomnya,
        // bukan dari atribut yang diisi saat pembuatan.
        $this->assertFalse(CertificateEvent::factory()->create()->fresh()->recipients_public);
    }

    /**
     * Kegiatan yang masih draf berarti sertifikatnya belum sah, jadi
     * daftarnya pun belum pantas diumumkan meski saklarnya sudah dinyalakan.
     */
    public function test_a_draft_event_stays_hidden_even_when_switched_on(): void
    {
        $event = $this->event(terbuka: true, status: 'draft');
        $this->issue($event, 'Budi Santoso', 'budi@student.jgu.ac.id');

        $this->get($event->publicRecipientsUrl())
            ->assertForbidden()
            ->assertDontSee('Budi Santoso');
    }

    // -------------------------------------------------------- isinya

    public function test_it_shows_what_is_printed_on_the_certificate(): void
    {
        $event = $this->event();
        $certificate = $this->issue($event, 'Budi Santoso', 'budi@student.jgu.ac.id');

        $this->get($event->publicRecipientsUrl())
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee($certificate->certificate_number)
            ->assertSee($certificate->verificationUrl(), false)
            ->assertSee('Seminar Kewirausahaan');
    }

    /**
     * Alamat email tidak pernah ikut: itu data pribadi, bukan bagian dari
     * dokumen yang dicetak.
     */
    public function test_it_never_publishes_an_email_address(): void
    {
        $event = $this->event();
        $this->issue($event, 'Budi Santoso', 'budi@student.jgu.ac.id');

        $this->get($event->publicRecipientsUrl())
            ->assertOk()
            ->assertDontSee('budi@student.jgu.ac.id');
    }

    /**
     * Sertifikat yang dicabut tidak lagi sah, jadi tidak pantas tercantum
     * sebagai penerima.
     */
    public function test_a_revoked_certificate_leaves_the_list(): void
    {
        $event = $this->event();
        $this->issue($event, 'Budi Santoso', 'budi@student.jgu.ac.id');
        $dicabut = $this->issue($event, 'Siti Nurhaliza', 'siti@jgu.ac.id');

        $dicabut->forceFill(['revoked_at' => now()])->save();

        $this->get($event->publicRecipientsUrl())
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Nurhaliza');
    }

    public function test_it_can_be_searched_by_name_or_number(): void
    {
        $event = $this->event();
        $this->issue($event, 'Budi Santoso', 'budi@student.jgu.ac.id');
        $this->issue($event, 'Siti Nurhaliza', 'siti@jgu.ac.id');

        $this->get($event->publicRecipientsUrl().'?cari=Siti')
            ->assertOk()
            ->assertSee('Siti Nurhaliza')
            ->assertDontSee('Budi Santoso');
    }

    /**
     * Kegiatan yang memang tidak ada tetap 404: tidak ada yang bisa
     * dikatakan tentangnya, dan mengarang penjelasan justru menyesatkan.
     */
    public function test_an_event_nobody_knows_is_simply_not_found(): void
    {
        $this->get(route('certificates.recipients', 'kegiatan-entah-apa'))->assertNotFound();
    }

    // ------------------------------------------------------- di panel

    /**
     * Saklarnya harus bisa dibalik tanpa membuka formulirnya: keputusan ini
     * sering diambil mendadak, sebelum atau sesudah acara.
     */
    public function test_an_admin_can_flip_it_from_the_event_list(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event(terbuka: false);

        Livewire::test(ListCertificateEvents::class)
            ->callTableAction('toggleRecipients', $event)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($event->fresh()->recipients_public);

        Livewire::test(ListCertificateEvents::class)
            ->callTableAction('toggleRecipients', $event->fresh());

        $this->assertFalse($event->fresh()->recipients_public);
    }

    /**
     * Membuka daftar nama orang adalah keputusan yang harus diambil sadar,
     * jadi akibatnya disebutkan sebelum disetujui.
     */
    public function test_the_panel_spells_out_what_opening_it_exposes(): void
    {
        $this->actingAs($this->admin());
        $this->event(terbuka: false);

        $event = CertificateEvent::latest('id')->sole();

        // Modal Filament dirender di sisi peramban, jadi teksnya diperiksa
        // pada aksinya langsung, bukan pada HTML halamannya.
        $aksi = Livewire::test(ListCertificateEvents::class)
            ->instance()
            ->getTable()
            ->getAction('toggleRecipients')
            ->record($event);

        $this->assertStringContainsString(
            'nama, peran, dan nomor sertifikat seluruh penerima',
            (string) $aksi->getModalDescription(),
        );
        $this->assertStringContainsString('Alamat email tidak pernah ditampilkan', (string) $aksi->getModalDescription());
        $this->assertTrue($aksi->isConfirmationRequired());
    }
}
