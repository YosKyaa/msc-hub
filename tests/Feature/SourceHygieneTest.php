<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kerusakan sunyi di dalam kode sumber.
 *
 * Pola regex di BorrowingFormTest pernah tertulis `/stream` diikuti baris baru
 * sungguhan, bukan dua karakter `\r?\n`. Ia tetap berupa PHP yang sah dan tidak
 * pernah melempar galat — hanya berhenti mencocokkan PDF yang pembatasnya
 * `\r\n`, sehingga testnya kadang lulus kadang tidak dan penjaga tata letak
 * yang seharusnya dijalankannya diam-diam mati.
 *
 * Kerusakan seperti ini tidak terlihat saat membaca kode: baris barunya
 * tampak seperti pemenggalan baris biasa.
 */
class SourceHygieneTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function phpFiles(): array
    {
        $berkas = [];

        foreach ([app_path(), base_path('tests'), base_path('database')] as $akar) {
            $isi = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($akar, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($isi as $file) {
                if ($file->getExtension() === 'php') {
                    $berkas[] = $file->getPathname();
                }
            }
        }

        $this->assertNotEmpty($berkas);

        return $berkas;
    }

    /**
     * Karakter kendali di dalam pola regex.
     *
     * `` yang termakan alat penyunting berubah menjadi karakter backspace
     * sungguhan. Polanya tetap PHP yang sah dan tetap berjalan tanpa keluhan,
     * hanya saja ia tidak pernah lagi mencocokkan apa pun. Penjaga yang
     * memuatnya karena itu lulus terus sambil tidak memeriksa apa-apa, dan
     * itulah yang terjadi pada penjaga isian formulir.
     */
    public function test_no_regex_pattern_contains_a_control_character(): void
    {
        $rusak = [];

        foreach ($this->phpFiles() as $berkas) {
            foreach (file($berkas) as $nomor => $baris) {
                if (! str_contains($baris, "'/") && ! str_contains($baris, '"/')) {
                    continue;
                }

                // Tab dan baris baru sah di dalam kode; yang tidak masuk akal
                // adalah karakter kendali lain seperti backspace dan escape.
                if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $baris)) {
                    $rusak[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $berkas)
                        .':'.($nomor + 1);
                }
            }
        }

        $this->assertSame([], $rusak,
            'Pola regex memuat karakter kendali, kemungkinan escape yang termakan: '
            .implode(', ', $rusak));
    }

    public function test_no_regex_pattern_contains_a_real_line_break(): void
    {
        $rusak = [];

        foreach ($this->phpFiles() as $berkas) {
            $isi = file_get_contents($berkas);

            // Pola berkutip tunggal yang diawali dan diakhiri pembatas `/`,
            // yaitu bentuk yang dipakai di seluruh proyek ini.
            preg_match_all("/'\\/[^'\\n]*\\n[^']*\\/[a-zA-Z]*'/", $isi, $cocok);

            foreach ($cocok[0] as $pola) {
                $rusak[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $berkas)
                    .': '.str_replace("\n", '⏎', mb_substr($pola, 0, 60));
            }
        }

        $this->assertSame([], $rusak,
            "Pola regex memuat baris baru sungguhan, kemungkinan `\\r` atau `\\n` yang termakan:\n"
            .implode("\n", $rusak));
    }
}
