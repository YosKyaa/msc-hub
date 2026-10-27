<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Berkas jadi sertifikat desain sendiri.
 *
 * Satu-satunya tempat yang memutuskan berkas mana yang diterima, untuk siapa
 * berkas itu, dan di mana ia disimpan. Disimpan di disk privat dan hanya
 * dilayani lewat tautan bertoken, sehingga sertifikat yang dicabut tidak lagi
 * bisa diunduh walau berkasnya masih ada.
 */
final class CertificateDesignFile
{
    public const DISK = 'local';

    public const DIRECTORY = 'certificates/designs';

    /** PDF siap cetak dari Canva biasanya beberapa megabita. */
    public const MAX_KB = 10240;

    public const MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    /** Sisi terpanjang minimal: di bawah ini hasil cetaknya pecah. */
    public const MIN_IMAGE_SIDE = 1000;

    public const WRONG_TYPE_MESSAGE = 'Berkas desain harus berupa PDF, JPG, atau PNG.';

    private const EXTENSIONS = ['pdf' => 'pdf', 'jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png'];

    /**
     * Alasan berkas ditolak, dalam kalimat untuk admin; null bila layak.
     * Diperiksa dari isinya, bukan dari nama atau jenis yang dilaporkan
     * peramban.
     */
    public static function problem(string $absolutePath, string $originalName): ?string
    {
        $ekstensi = self::EXTENSIONS[strtolower(pathinfo($originalName, PATHINFO_EXTENSION))] ?? null;

        if ($ekstensi === null) {
            return self::WRONG_TYPE_MESSAGE;
        }

        if ($ekstensi === 'pdf') {
            return str_starts_with((string) @file_get_contents($absolutePath, false, null, 0, 5), '%PDF-')
                ? null
                : 'Berkas ini bukan PDF yang utuh. Ekspor ulang desainnya sebagai PDF.';
        }

        $gambar = @getimagesize($absolutePath);

        if ($gambar === false || ! in_array($gambar[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return 'Berkas ini bukan gambar JPG atau PNG yang utuh. Ekspor ulang desainnya.';
        }

        if (max($gambar[0], $gambar[1]) < self::MIN_IMAGE_SIDE) {
            return "Gambar terlalu kecil ({$gambar[0]} × {$gambar[1]} px) untuk sertifikat: hasil cetaknya akan pecah. "
                .'Ekspor ulang dengan sisi terpanjang sedikitnya 2000 px, atau sebagai PDF.';
        }

        return null;
    }

    /**
     * Pasang berkas jadi pada sertifikatnya, menggantikan berkas sebelumnya.
     */
    public static function attach(Certificate $certificate, string $sourcePath, string $originalName): void
    {
        $ekstensi = self::EXTENSIONS[strtolower(pathinfo($originalName, PATHINFO_EXTENSION))] ?? 'pdf';
        $tujuan = self::DIRECTORY.'/'.$certificate->id.'-'.Str::lower(Str::random(12)).'.'.$ekstensi;

        $sumber = fopen($sourcePath, 'rb');
        Storage::disk(self::DISK)->put($tujuan, $sumber);

        if (is_resource($sumber)) {
            fclose($sumber);
        }

        $lama = $certificate->design_file_path;

        $certificate->forceFill([
            'design_file_path' => $tujuan,
            'design_uploaded_at' => now(),
        ])->save();

        if (filled($lama) && $lama !== $tujuan) {
            Storage::disk(self::DISK)->delete($lama);
        }
    }

    public static function exists(Certificate $certificate): bool
    {
        return filled($certificate->design_file_path) && Storage::disk(self::DISK)->exists($certificate->design_file_path);
    }

    public static function isImage(Certificate $certificate): bool
    {
        return in_array(strtolower(pathinfo((string) $certificate->design_file_path, PATHINFO_EXTENSION)), ['jpg', 'png'], true);
    }

    /**
     * Sertifikat yang dimaksud sebuah nama berkas: lewat nomornya, atau lewat
     * nama penerimanya bila hanya satu yang cocok. Null bila tidak ada yang
     * cocok atau cocoknya meragukan — berkas yang salah pasang lebih buruk
     * daripada berkas yang diminta diunggah ulang.
     *
     * @param  Collection<int, Certificate>  $certificates
     */
    public static function match(string $originalName, Collection $certificates): ?Certificate
    {
        $berkas = self::normalise(pathinfo($originalName, PATHINFO_FILENAME));

        if ($berkas === '') {
            return null;
        }

        $menurutNomor = $certificates
            ->map(fn (Certificate $c) => [$c, self::normalise((string) $c->certificate_number)])
            ->filter(fn (array $pasangan) => self::contains($berkas, $pasangan[1]))
            ->sortByDesc(fn (array $pasangan) => strlen($pasangan[1]))
            ->values();

        // Nomor yang lebih panjang menang: "0012-x-2026" lebih tepat daripada
        // nomor lain yang kebetulan termuat di dalamnya.
        if ($menurutNomor->isNotEmpty()) {
            $terpanjang = strlen($menurutNomor[0][1]);

            return $menurutNomor->filter(fn (array $pasangan) => strlen($pasangan[1]) === $terpanjang)->count() === 1
                ? $menurutNomor[0][0]
                : null;
        }

        $menurutNama = $certificates->filter(fn (Certificate $c) => self::contains($berkas, self::normalise((string) $c->recipient_name)));

        return $menurutNama->count() === 1 ? $menurutNama->first() : null;
    }

    /**
     * Huruf kecil dan angka saja, dipisah tanda hubung, supaya "0001/CERT/IX"
     * dan "0001-CERT-IX" dianggap sama.
     */
    private static function normalise(string $teks): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(Str::ascii($teks))), '-');
    }

    /**
     * Termuat utuh, bukan sekadar sepotong: "1-x-2026" tidak cocok dengan
     * berkas bernomor "11-x-2026".
     */
    private static function contains(string $berkas, string $kunci): bool
    {
        return $kunci !== '' && preg_match('/(^|-)'.preg_quote($kunci, '/').'(-|$)/', $berkas) === 1;
    }
}
