<?php

namespace App\Support;

use App\Models\CertificateTemplate;
use FontLib\Font;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Font kustom milik desain sertifikat.
 *
 * Satu-satunya tempat yang memutuskan berkas font mana yang diterima, di mana
 * ia disimpan, dan dengan nama apa ia dipanggil. Nama itu harus sama persis di
 * editor, pratinjau verifikasi, pengukur teks, dan PDF; bila tidak, PDF
 * diam-diam memakai font cadangan.
 */
final class CertificateFont
{
    public const DIRECTORY = 'certificates/fonts';

    /** Font sertifikat jarang lebih dari beberapa ratus kilobita. */
    public const MAX_KB = 5120;

    /**
     * Jenis berkas yang dilaporkan peramban maupun server untuk font.
     *
     * PHP mengenali TTF sebagai font/sfnt, jenis yang dulu tidak ada di
     * daftar, sehingga setiap TTF ditolak dengan "validation.mimetypes". Isi
     * berkasnya tetap diperiksa tersendiri oleh problem().
     */
    public const MIME_TYPES = [
        'font/ttf',
        'font/otf',
        'font/sfnt',
        'application/font-sfnt',
        'application/x-font-ttf',
        'application/x-font-truetype',
        'application/x-font-otf',
        'application/x-font-opentype',
        'application/vnd.ms-opentype',
    ];

    /**
     * Peramban tidak selalu mengenali jenis berkas font, jadi pemeriksa
     * unggahan di peramban memakai ekstensinya.
     */
    public const MIME_TYPE_MAP = ['ttf' => 'font/ttf', 'otf' => 'font/otf'];

    public const WRONG_TYPE_MESSAGE = 'Font harus berupa berkas .ttf atau .otf.';

    /**
     * Satu berkas didaftarkan untuk kedua ketebalan yang dikenal PDF.
     *
     * Untuk font bernama, dompdf hanya memakai varian yang persis diminta.
     * Tanpa pendaftaran tebal, nama bergaya Bold — bawaan editor untuk nama
     * penerima — dicetak dengan Times-Bold, bukan dengan font unggahan.
     */
    public const WEIGHTS = ['normal', 'bold'];

    /**
     * Pemisah nama berkas dari akhiran acaknya. Tidak pernah muncul di nama
     * yang sudah dirapikan, jadi nama tampilannya selalu bisa dipulihkan.
     */
    private const SUFFIX_SEPARATOR = '--';

    public function __construct(public readonly string $path) {}

    /**
     * @return list<self>
     */
    public static function forTemplate(CertificateTemplate $template): array
    {
        $fonts = [];

        foreach ((array) ($template->fonts ?? []) as $path) {
            if (is_string($path) && $path !== '') {
                $fonts[] = new self($path);
            }
        }

        return $fonts;
    }

    /**
     * Nama keluarga font di CSS maupun di dompdf: nama berkasnya tanpa
     * ekstensi.
     */
    public function family(): string
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
    }

    /**
     * Nama yang ditampilkan di editor, tanpa akhiran acak penyimpanannya.
     */
    public function label(): string
    {
        return Str::before($this->family(), self::SUFFIX_SEPARATOR);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * Alamat berkas untuk @font-face di PDF.
     *
     * Sengaja jalur biasa, tanpa awalan file:///. Dompdf mengubah
     * file:///E:/... menjadi /E:/..., yang tidak ditemukan di Windows, lalu
     * diam-diam memakai font cadangan.
     */
    public function pdfSource(): string
    {
        return str_replace('\\', '/', Storage::disk('public')->path($this->path));
    }

    /**
     * Alamat yang persis sama dengan yang dibentuk dompdf dari pdfSource().
     *
     * Dompdf menyimpan setiap font di cache menurut alamatnya. Pengukur teks
     * memakai alamat ini supaya berbagi satu entri dengan PDF; alamat yang
     * berbeda membuat dompdf membaca ulang fontnya di setiap sertifikat.
     */
    public function dompdfUri(): ?string
    {
        $nyata = realpath(Storage::disk('public')->path($this->path));

        return $nyata === false ? null : 'file://'.$nyata;
    }

    /**
     * Nama penyimpanan yang terbaca, misalnya "Great-Vibes--k3f9q2.ttf".
     *
     * Nama berkas menjadi nama font di editor, jadi nama acak bawaan Filament
     * tidak bisa dipakai. Akhiran acaknya mencegah font bernama sama milik
     * desain lain tertimpa, sekaligus mencegah dompdf memakai salinan lama
     * dari cachenya untuk berkas yang berbeda.
     */
    public static function storedName(string $originalName): string
    {
        $dasar = Str::ascii(pathinfo($originalName, PATHINFO_FILENAME));
        $dasar = trim(substr(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $dasar), '-'), 0, 60), '-');

        // Editor menulis nama ini di CSS tanpa tanda kutip, dan nama tanpa
        // kutip tidak boleh diawali angka.
        if ($dasar === '') {
            $dasar = 'font';
        } elseif (ctype_digit($dasar[0])) {
            $dasar = 'font-'.$dasar;
        }

        $ekstensi = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'otf' ? 'otf' : 'ttf';

        do {
            $nama = $dasar.self::SUFFIX_SEPARATOR.Str::lower(Str::random(6)).'.'.$ekstensi;
        } while (Storage::disk('public')->exists(self::DIRECTORY.'/'.$nama));

        return $nama;
    }

    /**
     * Alasan berkas font ditolak, dalam kalimat untuk admin; null bila layak.
     *
     * Diperiksa dari isinya, bukan dari nama atau jenis yang dilaporkan
     * peramban.
     */
    public static function problem(string $absolutePath, string $originalName): ?string
    {
        if (! in_array(strtolower(pathinfo($originalName, PATHINFO_EXTENSION)), ['ttf', 'otf'], true)) {
            return self::WRONG_TYPE_MESSAGE;
        }

        $kepala = (string) @file_get_contents($absolutePath, false, null, 0, 4);

        return match (true) {
            // Dompdf menanam font CFF dengan label TrueType. Chrome tetap
            // menampilkannya, tetapi pembaca PDF yang ketat seperti Adobe
            // Reader bisa tidak.
            $kepala === 'OTTO' => 'Font ini berformat OpenType CFF, yang belum dapat ditanam dengan benar ke PDF: di Adobe Reader hurufnya bisa tidak tampil. Unggah versi .ttf dari font yang sama.',
            $kepala === 'ttcf' => 'Berkas ini berisi kumpulan beberapa font (.ttc). Unggah satu berkas .ttf untuk setiap font.',
            in_array($kepala, ['wOFF', 'wOF2'], true) => 'Font web (WOFF) tidak dapat ditanam ke PDF. Unggah versi .ttf dari font yang sama.',
            ! in_array($kepala, ["\x00\x01\x00\x00", 'true'], true) => 'Berkas ini bukan font TrueType. Unggah berkas .ttf.',
            ! self::readable($absolutePath) => 'Berkas font ini rusak atau tidak dapat dibaca. Unduh ulang fontnya, lalu unggah kembali.',
            default => null,
        };
    }

    /**
     * Dibaca dengan pustaka yang sama yang dipakai dompdf, sampai ke metrik
     * yang ia simpan di cache: berkas yang gagal di sini gagal juga di PDF.
     */
    private static function readable(string $absolutePath): bool
    {
        $metrik = (string) tempnam(sys_get_temp_dir(), 'msc-font-');

        try {
            $font = Font::load($absolutePath);

            if ($font === null) {
                return false;
            }

            $font->parse();
            $font->saveAdobeFontMetrics($metrik);
            $font->close();

            return filesize($metrik) > 0;
        } catch (Throwable) {
            return false;
        } finally {
            @unlink($metrik);
        }
    }

    // ------------------------------------------------------ cache dompdf

    /**
     * Folder tempat dompdf menyimpan font yang didaftarkan beserta metriknya.
     *
     * @return list<string>
     */
    public static function cacheDirectories(): array
    {
        return array_values(array_unique(array_filter([
            (string) config('dompdf.options.font_dir'),
            (string) config('dompdf.options.font_cache'),
        ])));
    }

    /**
     * Dompdf tidak membuat folder cachenya sendiri. Tanpa folder itu,
     * pendaftaran font gagal diam-diam dan PDF memakai font cadangan.
     */
    public static function prepareCacheDirectory(): void
    {
        foreach (self::cacheDirectories() as $folder) {
            try {
                File::ensureDirectoryExists($folder);
            } catch (Throwable) {
                // Diberitahukan kepada admin lewat cacheIsWritable();
                // pencetakan tetap berjalan dengan font cadangan.
            }
        }
    }

    public static function cacheIsWritable(): bool
    {
        self::prepareCacheDirectory();

        foreach (self::cacheDirectories() as $folder) {
            if (! is_dir($folder) || ! is_writable($folder)) {
                return false;
            }
        }

        return true;
    }
}
