<?php

namespace App\Support;

use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\ContentRequest;
use App\Models\InventoryItem;
use App\Models\Room;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Angka-angka yang ditampilkan di beranda.
 *
 * Halaman beranda yang memajang "2000+ proyek selesai" tanpa dasar akan
 * ketahuan oleh orang pertama yang menghitungnya, dan sejak itu seluruh
 * halamannya ikut diragukan. Ini portal pengajuan milik kampus sendiri —
 * pembacanya mahasiswa dan dosen yang tahu persis seberapa besar MSC.
 *
 * Semua angka di sini karena itu dihitung dari basis data. Bila kebetulan
 * masih kecil, yang ditampilkan memang angka kecil itu.
 */
class LayananStatistik
{
    /**
     * Beranda dibuka siapa saja dan sering, sedangkan angkanya berubah
     * hitungan hari. Menghitung ulang tiap kunjungan hanya membebani basis
     * data tanpa menghasilkan angka yang berbeda.
     */
    private const SEGAR_MENIT = 30;

    /**
     * @return list<array{angka: string, label: string, catatan: string}>
     */
    public static function untukBeranda(): array
    {
        $hitung = self::hitung();

        return [
            [
                'angka' => self::ringkas($hitung['pengajuan']),
                'label' => 'Pengajuan masuk',
                'catatan' => 'Konten, ruangan, dan alat',
            ],
            [
                'angka' => self::ringkas($hitung['sertifikat']),
                'label' => 'Sertifikat terbit',
                'catatan' => 'Lengkap dengan halaman verifikasi',
            ],
            [
                'angka' => self::ringkas($hitung['kegiatan']),
                'label' => 'Kegiatan terlayani',
                'catatan' => 'Seminar, pelatihan, dan acara kampus',
            ],
            [
                'angka' => self::ringkas($hitung['fasilitas']),
                'label' => 'Ruangan & alat',
                'catatan' => 'Siap dipinjam sivitas JGU',
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function hitung(): array
    {
        try {
            return Cache::remember('beranda:statistik', now()->addMinutes(self::SEGAR_MENIT), fn () => [
                'pengajuan' => ContentRequest::count(),
                'sertifikat' => Certificate::whereNull('revoked_at')->count(),
                'kegiatan' => CertificateEvent::where('status', 'published')->count(),
                'fasilitas' => Room::where('is_active', true)->count()
                    + InventoryItem::where('is_active', true)->count(),
            ]);
        } catch (Throwable) {
            // Beranda tetap harus terbuka meski basis datanya sedang tidak
            // bisa dihubungi; angka nol lebih baik daripada halaman 500.
            return ['pengajuan' => 0, 'sertifikat' => 0, 'kegiatan' => 0, 'fasilitas' => 0];
        }
    }

    /**
     * Angka besar diringkas, yang kecil ditulis apa adanya.
     *
     * "0" ditulis sebagai tanda hubung: nol yang dipajang besar-besar terbaca
     * seperti kegagalan, padahal artinya hanya belum ada datanya.
     */
    private static function ringkas(int $jumlah): string
    {
        return match (true) {
            $jumlah <= 0 => '—',
            $jumlah >= 1000 => round($jumlah / 1000, 1).'k+',
            $jumlah >= 100 => (intdiv($jumlah, 10) * 10).'+',
            default => (string) $jumlah,
        };
    }
}
