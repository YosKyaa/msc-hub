<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\CertificateEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bagaimana beranda terbaca oleh mesin pencari dan saat tautannya dibagikan.
 *
 * Halamannya dulu hanya membawa judul. Tanpa deskripsi, kanonik, dan tag
 * Open Graph, hasil pencariannya berupa satu baris tanpa keterangan, dan
 * tautan yang dibagikan di WhatsApp muncul sebagai alamat mentah.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------- beranda

    public function test_the_landing_page_describes_itself(): void
    {
        $response = $this->get(route('landing'))->assertOk();

        $response->assertSee('<meta name="description"', false);
        $response->assertSee('pinjam ruangan dan alat multimedia', false);
        $response->assertSee('<link rel="canonical" href="'.route('landing').'"', false);
    }

    /**
     * Tanpa ini, tautan yang dibagikan di WhatsApp atau LinkedIn muncul
     * sebagai alamat mentah tanpa judul maupun gambar.
     */
    public function test_a_shared_link_carries_a_preview(): void
    {
        $response = $this->get(route('landing'))->assertOk();

        foreach ([
            'og:site_name', 'og:type', 'og:title', 'og:description', 'og:url', 'og:image', 'og:locale',
            'twitter:card', 'twitter:title', 'twitter:image',
        ] as $tag) {
            $response->assertSee($tag, false);
        }

        $response->assertSee(asset(config('msc.seo.image')), false);
    }

    public function test_it_invites_crawlers_rather_than_staying_silent(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('content="index, follow, max-image-preview:large"', false);
    }

    /**
     * Data terstruktur harus benar-benar sah; JSON yang rusak diabaikan
     * mesin pencari tanpa memberi tahu siapa pun.
     */
    public function test_the_structured_data_is_valid_json(): void
    {
        $body = $this->get(route('landing'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $body, $cocok));

        $data = json_decode($cocok[1], true);

        $this->assertIsArray($data, 'JSON-LD tidak dapat dibaca.');
        $this->assertSame('https://schema.org', $data['@context']);

        $tipe = array_column($data['@graph'], '@type');
        $this->assertContains('Organization', $tipe);
        $this->assertContains('WebSite', $tipe);
    }

    public function test_the_structured_data_names_the_faculty_and_its_address(): void
    {
        $body = $this->get(route('landing'))->assertOk()->getContent();
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $body, $cocok);

        $organisasi = collect(json_decode($cocok[1], true)['@graph'])
            ->firstWhere('@type', 'Organization');

        $this->assertSame(config('msc.seo.organization.name'), $organisasi['name']);
        $this->assertSame('Jakarta Global University', $organisasi['parentOrganization']['name']);
        $this->assertSame('Depok', $organisasi['address']['addressLocality']);
    }

    // ------------------------------------------------------------- robots

    public function test_robots_points_at_the_sitemap_on_the_right_domain(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $response->assertSee('Sitemap: '.route('sitemap'), false);
    }

    /**
     * Halaman yang menuntut login atau memuat data milik seseorang tidak
     * pantas muncul di hasil pencarian siapa pun.
     */
    public function test_robots_keeps_crawlers_out_of_private_paths(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        foreach (['/panel', '/dashboard', '/my-bookings', '/request/status', '/verify', '/attend'] as $jalur) {
            $response->assertSee('Disallow: '.$jalur, false);
        }

        $response->assertSee('Allow: /', false);
    }

    // ------------------------------------------------------------ sitemap

    public function test_the_sitemap_is_valid_xml(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());

        $this->assertNotFalse($xml, 'Peta situs bukan XML yang sah.');
        $this->assertGreaterThan(0, count($xml->url));

        // Deklarasinya harus persis di awal berkas, tanpa spasi atau baris
        // kosong mendahuluinya. simplexml tetap menerima berkas tanpa
        // deklarasi, jadi tanpa pemeriksaan ini baris itu bisa rusak tanpa ada
        // test yang gagal.
        //
        // Penutupnya dirangkai dari dua potong, sama seperti di
        // resources/views/sitemap.blade.php: sebagian pengurai mencari tanda
        // penutup PHP tanpa peduli ia berada di dalam string, lalu salah
        // membaca sisa berkasnya. PHP sendiri bahkan mengakhiri mode PHP bila
        // tanda itu muncul di dalam komentar satu baris, jadi ia tidak boleh
        // ditulis utuh di mana pun — termasuk di kalimat ini.
        $this->assertStringStartsWith(
            '<?xml version="1.0" encoding="UTF-8"?'.'>'."\n",
            $response->getContent(),
            'Deklarasi XML tidak berada tepat di awal peta situs.',
        );
    }

    public function test_the_sitemap_grows_with_the_content(): void
    {
        $pengumuman = Announcement::create([
            'title' => 'Jadwal Peminjaman Studio',
            'summary' => 'Studio tutup selama pekan ujian.',
            'content' => 'Studio MSC tutup selama pekan ujian akhir semester.',
            'category' => 'announcement',
            'published_at' => Carbon::now()->subDay(),
            'is_active' => true,
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('announcements.show', $pengumuman->slug), false);
    }

    /**
     * Daftar penerima hanya dicantumkan bila admin memang membukanya —
     * mengumumkan yang tertutup ke mesin pencari justru membocorkannya.
     */
    public function test_only_opened_recipient_lists_reach_the_sitemap(): void
    {
        $terbuka = CertificateEvent::factory()->published()->create([
            'slug' => 'seminar-terbuka',
            'recipients_public' => true,
        ]);

        $tertutup = CertificateEvent::factory()->published()->create([
            'slug' => 'seminar-tertutup',
            'recipients_public' => false,
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee($terbuka->publicRecipientsUrl(), false)
            ->assertDontSee($tertutup->publicRecipientsUrl(), false);
    }

    /**
     * Halaman yang menuntut login hanya akan menyuguhkan pengalihan ke
     * Google bagi perayap, jadi tidak pernah dicantumkan.
     */
    public function test_the_sitemap_lists_no_page_that_demands_a_login(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach ([route('requester.dashboard'), route('my.bookings'), route('login.portal')] as $jalur) {
            $this->assertStringNotContainsString('<loc>'.$jalur.'</loc>', $xml);
        }
    }
}
