<?php

namespace Tests\Feature;

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isi pengumuman ditampilkan sebagai HTML di halaman publik.
 *
 * Isinya ditulis staf lewat RichEditor, yang menormalkannya lewat tiptap saat
 * disimpan. Tetapi isi yang masuk bukan lewat editor — seeder, impor, atau
 * langsung ke basis data — tidak melewati penyaring itu, dan halaman publik
 * dulu menampilkannya mentah. Siapa pun yang membuka pengumuman, termasuk
 * admin yang sedang masuk ke panel, akan menjalankan skrip di dalamnya.
 */
class AnnouncementContentSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function show(string $content): string
    {
        $pengumuman = Announcement::factory()->create(['content' => $content]);

        return $this->get(route('announcements.show', $pengumuman->slug))->assertOk()->getContent();
    }

    public function test_scripts_written_straight_into_the_database_do_not_run(): void
    {
        $html = $this->show(
            '<p>Halo</p><script>alert(document.cookie)</script>'
            .'<img src="x" onerror="alert(1)">'
            .'<a href="javascript:alert(2)">klik</a>'
        );

        $this->assertStringNotContainsString('alert(document.cookie)', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
    }

    /**
     * Penyaringnya tidak boleh ikut membuang format yang memang dipakai staf.
     */
    public function test_ordinary_formatting_survives(): void
    {
        $html = $this->show(
            '<h2>Jadwal PKKMB</h2>'
            .'<p><strong>Hari pertama</strong> dimulai pukul 07.00.</p>'
            .'<ul><li>Bawa KTM</li><li>Pakai almamater</li></ul>'
            .'<p><a href="https://jgu.ac.id/pkkmb">Informasi lengkap</a></p>'
        );

        $this->assertStringContainsString('<h2>Jadwal PKKMB</h2>', $html);
        $this->assertStringContainsString('<strong>Hari pertama</strong>', $html);
        $this->assertStringContainsString('<li>Bawa KTM</li>', $html);
        $this->assertStringContainsString('href="https://jgu.ac.id/pkkmb"', $html);
    }
}
