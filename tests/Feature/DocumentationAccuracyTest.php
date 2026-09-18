<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Dokumentasi yang menyebut hal yang tidak ada.
 *
 * docs/certificate-system.md pernah menjanjikan bahwa email menyusul sendiri
 * begitu kegiatan dipublikasikan, lengkap dengan nama kelas yang mengurusnya:
 * CertificateEventObserver. Kelas itu tidak pernah ada, dan perilakunya justru
 * kebalikan dari yang sebenarnya dirancang. Siapa pun yang mempercayainya akan
 * menunggu email yang tidak akan pernah datang.
 *
 * Dokumen tidak bisa diuji kebenarannya kalimat per kalimat, tetapi nama yang
 * disebutnya bisa: kelas harus benar-benar ada, berkas harus benar-benar ada,
 * dan tombol yang disuruh ditekan harus benar-benar ada di panel.
 */
class DocumentationAccuracyTest extends TestCase
{
    /**
     * Hanya dokumen yang ikut terbawa ke repositori. Sisanya catatan lokal.
     *
     * @return list<string>
     */
    private function trackedDocs(): array
    {
        $keluaran = [];
        exec('git ls-files "*.md"', $keluaran);

        $berkas = array_values(array_filter(
            array_map(fn (string $baris) => base_path(trim($baris)), $keluaran),
            fn (string $path) => is_file($path),
        ));

        // Penjaga bagi penjaga: bila daftarnya kosong, seluruh test di bawah
        // lulus tanpa memeriksa apa pun.
        $this->assertNotEmpty($berkas, 'Tidak ada dokumen terlacak yang ditemukan.');

        return $berkas;
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    /**
     * Nama kelas yang disebut dokumen harus benar-benar ada.
     */
    public function test_every_class_the_docs_name_actually_exists(): void
    {
        $hilang = [];

        foreach ($this->trackedDocs() as $berkas) {
            $isi = file_get_contents($berkas);

            // Hanya yang ditulis dalam backtick dan bernamespace penuh:
            // itulah yang dimaksudkan pembaca sebagai nama kelas sungguhan.
            preg_match_all('/`(App\\\\[A-Za-z0-9_\\\\]+)`/', $isi, $cocok);

            foreach (array_unique($cocok[1]) as $kelas) {
                if (! class_exists($kelas) && ! interface_exists($kelas) && ! enum_exists($kelas)) {
                    $hilang[] = $this->relative($berkas).' menyebut '.$kelas;
                }
            }
        }

        $this->assertSame([], $hilang,
            "Dokumentasi menyebut kelas yang tidak ada:\n".implode("\n", $hilang));
    }

    /**
     * Begitu pula berkas yang ditunjuknya — rujukan ke berkas yang sudah
     * dipindah membuat pembaca mengira dirinya salah mencari.
     */
    public function test_every_file_the_docs_point_at_actually_exists(): void
    {
        $hilang = [];

        foreach ($this->trackedDocs() as $berkas) {
            $isi = file_get_contents($berkas);

            preg_match_all(
                '#`?((?:app|tests|resources|config|database|routes)/[A-Za-z0-9_/.-]+\.(?:php|blade\.php))`?#',
                $isi,
                $cocok,
            );

            foreach (array_unique($cocok[1]) as $rujukan) {
                if (! is_file(base_path($rujukan))) {
                    $hilang[] = $this->relative($berkas).' menunjuk '.$rujukan;
                }
            }
        }

        $this->assertSame([], $hilang,
            "Dokumentasi menunjuk berkas yang tidak ada:\n".implode("\n", $hilang));
    }

    /**
     * Setelan yang disuruh diisi di runbook harus dikenali aplikasinya,
     * kalau tidak orang menyetelnya lalu heran tidak ada yang berubah.
     */
    public function test_every_setting_the_runbook_tells_you_to_set_is_read_somewhere(): void
    {
        $runbook = file_get_contents(base_path('docs/DEPLOYMENT.md'));

        preg_match_all('/^(MSC_[A-Z0-9_]+)=/m', $runbook, $cocok);

        $disebut = array_unique($cocok[1]);
        $this->assertNotEmpty($disebut, 'Runbook tidak menyebut satu pun setelan MSC_.');

        $config = '';
        foreach (glob(config_path('*.php')) as $berkas) {
            $config .= file_get_contents($berkas);
        }

        $tidakTerbaca = array_values(array_filter(
            $disebut,
            fn (string $kunci) => ! str_contains($config, $kunci),
        ));

        $this->assertSame([], $tidakTerbaca,
            'Runbook menyuruh menyetel kunci yang tidak dibaca config mana pun: '
            .implode(', ', $tidakTerbaca));
    }

    /**
     * Setelan yang dibaca aplikasi tetapi tidak pernah disebut .env.example
     * akan terlewat saat rilis, dan bawaannya belum tentu cocok untuk server.
     */
    public function test_the_env_example_mentions_the_project_settings(): void
    {
        $contoh = file_get_contents(base_path('.env.example'));

        $config = '';
        foreach (glob(config_path('*.php')) as $berkas) {
            $config .= file_get_contents($berkas);
        }

        preg_match_all("/env\('(MSC_[A-Z0-9_]+)'/", $config, $cocok);

        $hilang = array_values(array_filter(
            array_unique($cocok[1]),
            fn (string $kunci) => ! str_contains($contoh, $kunci),
        ));

        $this->assertSame([], $hilang,
            'Setelan ini dibaca aplikasi tetapi tidak disebut .env.example: '.implode(', ', $hilang));
    }
}
