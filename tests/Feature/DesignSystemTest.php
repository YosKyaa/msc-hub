<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Satu sistem desain untuk seluruh halaman publik.
 *
 * Tiap layout dulu memuat Tailwind sendiri tanpa tetapan warna apa pun,
 * sehingga beranda memakai kertas hangat dan aksen kuning sementara halaman
 * pengajuannya tetap putih dan biru. Dua wajah untuk satu portal, dan tidak
 * ada satu tempat pun untuk memperbaikinya sekaligus.
 */
class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Halaman yang dibuka pengunjung tanpa perlu masuk.
     *
     * @return array<string, string>
     */
    private function halamanPublik(): array
    {
        return [
            'beranda' => route('landing'),
            'bio' => route('bio'),
            'portal masuk' => route('login.portal'),
            'pengumuman' => route('announcements.index'),
            'formulir konten' => route('request.content'),
            'cek status' => route('request.status'),
        ];
    }

    /**
     * Tetapan warnanya harus sampai ke tiap halaman. Tanpa itu kelas seperti
     * bg-paper dan text-sun-ink tidak berarti apa-apa, dan halamannya tampil
     * tanpa warna sama sekali.
     */
    public function test_every_public_page_carries_the_design_tokens(): void
    {
        $tanpa = [];

        foreach ($this->halamanPublik() as $nama => $url) {
            $html = $this->get($url)->assertOk()->getContent();

            foreach ([
                "ink: '#15151A'",
                "paper: '#FBFAF7'",
                "sun: '#FFD84D'",
                "'sun-ink': '#8A6000'",
            ] as $tetapan) {
                if (! str_contains($html, $tetapan)) {
                    $tanpa[] = "{$nama}: {$tetapan}";
                }
            }
        }

        $this->assertSame([], $tanpa,
            "Halaman ini tidak membawa tetapan warnanya:\n".implode("\n", $tanpa));
    }

    public function test_every_public_page_uses_the_same_typeface(): void
    {
        foreach ($this->halamanPublik() as $nama => $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('family=Inter', false);
        }
    }

    /**
     * Akar sebabnya, dijaga langsung: layout yang memuat Tailwind sendiri
     * akan kehilangan seluruh tetapan warnanya, dan kerusakannya tidak
     * terlihat sebagai galat melainkan sebagai halaman yang tampak biasa saja.
     */
    public function test_no_layout_loads_tailwind_on_its_own(): void
    {
        $nakal = [];

        foreach (File::allFiles(resource_path('views/layouts')) as $berkas) {
            $isi = file_get_contents($berkas->getPathname());

            if (str_contains($isi, 'cdn.tailwindcss.com')) {
                $nakal[] = $berkas->getFilename();
            }

            if (! str_contains($isi, '<x-organisms.design-system')) {
                $nakal[] = $berkas->getFilename().' (tidak memanggil sistem desain)';
            }
        }

        $this->assertSame([], $nakal,
            "Layout ini memuat Tailwind sendiri atau melewatkan sistem desainnya:\n"
            .implode("\n", $nakal)
            ."\nSemuanya harus lewat <x-organisms.design-system />.");
    }

    /**
     * Warna biru masih sah sebagai penanda status, tetapi tidak lagi sebagai
     * warna merek. Yang dijaga: tombol utama tidak boleh kembali menjadi biru.
     */
    public function test_the_primary_button_is_no_longer_blue(): void
    {
        $biru = [];

        foreach (File::allFiles(resource_path('views')) as $berkas) {
            $rel = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $berkas->getPathname());

            // Halaman bawaan Laravel yang tidak dirujuk rute mana pun.
            if (str_contains($rel, 'welcome.blade.php')) {
                continue;
            }

            foreach (file($berkas->getPathname()) as $nomor => $baris) {
                // Pemetaan status dilewati: di sana biru memang membedakan
                // keadaan, bukan menandai merek.
                if (preg_match("/=>\s*'bg-|@case\(|author_type/", $baris)) {
                    continue;
                }

                if (preg_match('/bg-(blue|indigo)-(5|6|7)00/', $baris)) {
                    $biru[] = $rel.':'.($nomor + 1);
                }
            }
        }

        $this->assertSame([], $biru,
            "Tombol biru kembali muncul di:\n".implode("\n", $biru)
            ."\nWarna utama portal ini kuning, mengikuti berandanya.");
    }
}
