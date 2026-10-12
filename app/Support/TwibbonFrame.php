<?php

namespace App\Support;

/**
 * Pemeriksa bingkai twibbon yang diunggah admin.
 *
 * Twibbon hanya bekerja bila bingkainya PNG yang benar-benar berlubang: foto
 * peserta digambar di bawah bingkai, dan hanya terlihat lewat bagian yang
 * transparan. Bingkai JPG, atau PNG tanpa bagian transparan, menutup foto
 * peserta sepenuhnya — dan itu baru ketahuan ketika ratusan peserta sudah
 * mencobanya.
 */
final class TwibbonFrame
{
    /** Sisi terpendek minimal, supaya hasilnya tidak pecah di Instagram. */
    public const MIN_SIDE = 1080;

    /** Bagian transparan minimal, dalam persen luas bingkai. */
    public const MIN_TRANSPARENT_PERCENT = 10.0;

    /**
     * Rasio yang lazim dipakai media sosial, dengan nama yang dikenali admin.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public const RATIOS = [
        'Persegi (1:1)' => [1, 1],
        'Potret (4:5)' => [4, 5],
        'Story (9:16)' => [9, 16],
    ];

    /** Jarak antartitik sampel saat mengukur bagian transparan. */
    private const SAMPLE_STEP = 6;

    private function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly float $transparentPercent,
        public readonly ?string $ratioLabel,
    ) {}

    /**
     * Baca ukuran dan bagian transparan sebuah berkas PNG. Null bila berkasnya
     * bukan PNG yang bisa dibaca.
     */
    public static function inspect(string $absolutePath): ?self
    {
        $info = @getimagesize($absolutePath);

        if ($info === false || ($info[2] ?? null) !== IMAGETYPE_PNG) {
            return null;
        }

        $gambar = @imagecreatefrompng($absolutePath);

        if ($gambar === false) {
            return null;
        }

        // PNG berpalet menyimpan transparansinya di tabel warna; diubah ke
        // truecolor supaya alfanya terbaca per titik dengan cara yang sama.
        if (! imageistruecolor($gambar)) {
            imagepalettetotruecolor($gambar);
        }

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);
        $sampel = 0;
        $tembus = 0;

        for ($y = 0; $y < $tinggi; $y += self::SAMPLE_STEP) {
            for ($x = 0; $x < $lebar; $x += self::SAMPLE_STEP) {
                $sampel++;

                // Alfa GD: 0 pekat, 127 tembus penuh. Di atas 100 dianggap
                // cukup tembus untuk memperlihatkan foto di bawahnya.
                if (((imagecolorat($gambar, $x, $y) >> 24) & 0x7F) > 100) {
                    $tembus++;
                }
            }
        }

        imagedestroy($gambar);

        return new self(
            $lebar,
            $tinggi,
            $sampel === 0 ? 0.0 : round($tembus / $sampel * 100, 2),
            self::ratioLabel($lebar, $tinggi),
        );
    }

    /**
     * Alasan bingkai ditolak, dalam kalimat untuk admin; null bila layak.
     */
    public static function problem(string $absolutePath): ?string
    {
        $bingkai = self::inspect($absolutePath);

        if ($bingkai === null) {
            return 'Bingkai harus berkas PNG. Format lain seperti JPG tidak punya bagian transparan, sehingga foto peserta tertutup seluruhnya.';
        }

        if (min($bingkai->width, $bingkai->height) < self::MIN_SIDE) {
            return "Bingkai terlalu kecil ({$bingkai->width} × {$bingkai->height} px). Sisi terpendeknya minimal ".self::MIN_SIDE.' px agar hasilnya tidak pecah di media sosial.';
        }

        if ($bingkai->ratioLabel === null) {
            return "Rasio bingkai {$bingkai->width} × {$bingkai->height} px tidak lazim. Gunakan ".implode(', ', array_keys(self::RATIOS)).', misalnya 1080 × 1080 atau 1080 × 1350 px.';
        }

        if ($bingkai->transparentPercent < self::MIN_TRANSPARENT_PERCENT) {
            return 'Bagian transparan bingkai ini hanya '.self::percent($bingkai->transparentPercent).' dari luasnya, sehingga foto peserta nyaris tidak terlihat. '
                .'Pastikan bagian tempat foto benar-benar dikosongkan (transparan) saat mengekspor PNG, bukan diisi warna putih.';
        }

        return null;
    }

    public static function percent(float $nilai): string
    {
        return rtrim(rtrim(number_format($nilai, 1, ',', '.'), '0'), ',').'%';
    }

    private static function ratioLabel(int $lebar, int $tinggi): ?string
    {
        foreach (self::RATIOS as $label => [$a, $b]) {
            if ($tinggi > 0 && abs(($lebar / $tinggi) - ($a / $b)) <= 0.01) {
                return $label;
            }
        }

        return null;
    }
}
