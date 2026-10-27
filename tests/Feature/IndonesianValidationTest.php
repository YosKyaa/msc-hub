<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Pesan validasi bawaan dalam Bahasa Indonesia.
 *
 * Server dengan APP_LOCALE=id dan APP_FALLBACK_LOCALE=id dulu menampilkan
 * kunci terjemahannya mentah-mentah — "validation.mimetypes" di bawah isian
 * unggah font sertifikat — karena aplikasi ini belum punya terjemahan
 * validasi sama sekali.
 */
class IndonesianValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Persis keadaan server: tanpa bahasa cadangan Inggris.
        app()->setLocale('id');
        app('translator')->setFallback('id');
    }

    /**
     * @return array<string, string>
     */
    private function english(): array
    {
        $pesan = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

        return Arr::dot(Arr::except($pesan, ['custom', 'attributes']));
    }

    /**
     * @return array<string, string>
     */
    private function indonesian(): array
    {
        return Arr::dot(require lang_path('id/validation.php'));
    }

    public function test_a_failed_rule_reads_as_a_sentence_not_a_key(): void
    {
        $pesan = Validator::make(['nama' => ''], ['nama' => 'required'], [], ['nama' => 'nama template'])
            ->errors()
            ->first('nama');

        $this->assertSame('Nama template wajib diisi.', $pesan);
    }

    public function test_the_upload_error_from_the_font_field_is_translated(): void
    {
        $pesan = __('validation.mimetypes', ['attribute' => 'berkas font', 'values' => 'font/ttf']);

        $this->assertSame('Berkas font harus berupa berkas berjenis: font/ttf.', $pesan);
    }

    /**
     * Aturan baru yang datang bersama pembaruan Laravel ikut tertangkap di
     * sini, bukan di layar admin.
     */
    public function test_every_laravel_rule_has_an_indonesian_message(): void
    {
        $hilang = array_values(array_diff(array_keys($this->english()), array_keys($this->indonesian())));

        $this->assertSame([], $hilang, 'Aturan tanpa terjemahan: '.implode(', ', $hilang));
    }

    /**
     * Placeholder yang hilang membuat pesan tidak lagi menyebut nama isian
     * atau batasnya.
     */
    public function test_translations_keep_every_placeholder(): void
    {
        $indonesia = $this->indonesian();

        foreach ($this->english() as $kunci => $teks) {
            preg_match_all('/:([a-z_]+)/i', $teks, $asal);
            preg_match_all('/:([a-z_]+)/i', $indonesia[$kunci] ?? '', $terjemahan);

            $this->assertEqualsCanonicalizing(
                array_values(array_unique(array_map('strtolower', $asal[1]))),
                array_values(array_unique(array_map('strtolower', $terjemahan[1]))),
                "Placeholder pada {$kunci} berbeda dengan aslinya.",
            );
        }
    }
}
