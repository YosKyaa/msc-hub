<?php

namespace App\Support;

use App\Models\Certificate;
use App\Models\CertificateEvent;

/**
 * Sudah sampai mana sebuah kegiatan, dan apa langkah berikutnya.
 *
 * Membuat sertifikat itu empat langkah berurutan, tetapi urutannya tidak
 * pernah tertulis di mana pun: admin harus menyimpulkannya sendiri dari tombol
 * yang tersedia dan dari kalimat ringkasan di atas tabel peserta. Orang yang
 * baru memakainya tidak tahu harus mulai dari mana, dan yang sudah terbiasa
 * pun lupa apakah emailnya sudah terkirim.
 *
 * Kelas ini menjawab keduanya sekali jalan: keadaan tiap langkah, angkanya,
 * dan satu kalimat tentang apa yang harus dikerjakan sekarang.
 *
 * Semuanya dihitung basis data lewat satu kueri agregat. Kegiatan dengan
 * seribu peserta tidak boleh membuat halaman ini berat.
 */
class CertificateProgress
{
    public const BELUM = 'belum';

    public const BERJALAN = 'berjalan';

    public const SELESAI = 'selesai';

    private function __construct(
        private readonly CertificateEvent $event,
        private readonly int $peserta,
        private readonly int $berhak,
        private readonly int $terbit,
        private readonly int $terkirim,
        private readonly int $gagal,
    ) {}

    public static function for(CertificateEvent $event): self
    {
        $peserta = $event->participations()->count();

        $berhak = CertificateStage::READY->constrain($event->participations()->getQuery())->count();

        // Sertifikat yang dicabut tidak lagi berlaku, jadi tidak ikut
        // dihitung — kalau ikut, langkahnya terlihat tuntas padahal ada yang
        // harus diterbitkan ulang.
        $sertifikat = Certificate::where('certificate_event_id', $event->id)
            ->whereNull('revoked_at')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when emailed_at is not null then 1 else 0 end) as terkirim')
            ->selectRaw('sum(case when email_failed_at is not null and emailed_at is null then 1 else 0 end) as gagal')
            ->first();

        return new self(
            $event,
            $peserta,
            // Yang sudah terbit tidak lagi berhitung sebagai "siap terbit",
            // jadi keduanya dijumlahkan agar langkah kedua tetap terlihat
            // tuntas setelah sertifikatnya jadi.
            $berhak + (int) $sertifikat?->total,
            (int) $sertifikat?->total,
            (int) $sertifikat?->terkirim,
            (int) $sertifikat?->gagal,
        );
    }

    /**
     * Empat langkah, berurutan, masing-masing dengan angkanya sendiri.
     *
     * @return list<array{kunci:string, judul:string, keadaan:string, angka:string, catatan:string}>
     */
    public function steps(): array
    {
        return [
            $this->step(
                'peserta',
                'Daftarkan peserta',
                $this->peserta > 0,
                false,
                $this->peserta.' orang terdaftar',
                'Belum ada peserta',
            ),
            $this->step(
                'berhak',
                'Tandai yang berhak',
                $this->peserta > 0 && $this->berhak >= $this->peserta,
                $this->berhak > 0,
                $this->berhak.' dari '.$this->peserta.' berhak',
                'Belum ada yang ditandai berhak',
            ),
            $this->step(
                'terbit',
                'Terbitkan digital',
                $this->berhak > 0 && $this->terbit >= $this->berhak,
                $this->terbit > 0,
                $this->terbit.' sertifikat terbit',
                'Belum ada yang diterbitkan',
            ),
            $this->step(
                'kirim',
                'Kirim email',
                $this->terbit > 0 && $this->terkirim >= $this->terbit,
                $this->terkirim > 0,
                $this->terkirim.' dari '.$this->terbit.' terkirim',
                'Belum ada email terkirim',
            ),
        ];
    }

    private function step(
        string $kunci,
        string $judul,
        bool $selesai,
        bool $berjalan,
        string $angka,
        string $kosong,
    ): array {
        $keadaan = match (true) {
            $selesai => self::SELESAI,
            $berjalan => self::BERJALAN,
            default => self::BELUM,
        };

        return [
            'kunci' => $kunci,
            'judul' => $judul,
            'keadaan' => $keadaan,
            'angka' => $keadaan === self::BELUM ? $kosong : $angka,
        ];
    }

    /**
     * Satu kalimat tentang apa yang harus dikerjakan sekarang — ditulis
     * seperti orang memberi tahu rekannya, bukan seperti pesan sistem.
     */
    public function nextStep(): string
    {
        return match (true) {
            $this->peserta === 0 => 'Mulai dengan menambahkan peserta, satu per satu atau impor dari Excel.',
            $this->berhak === 0 => 'Tandai siapa saja yang berhak menerima sertifikat lewat tombol centang di tabel peserta.',
            $this->terbit === 0 => 'Tekan "1. Terbitkan Digital". Nomor dan halaman verifikasinya dibuat, email belum dikirim.',
            // Kegagalan disebut lebih dulu daripada sisa yang belum dikirim:
            // yang belum dikirim akan terkirim kalau tombolnya ditekan, yang
            // gagal tidak akan pernah beres dengan sendirinya.
            $this->gagal > 0 => $this->gagal.' email gagal terkirim. Buka kolom "Kendala email" di tabel peserta untuk melihat sebabnya.',
            $this->terkirim === 0 => 'Periksa satu sertifikat dulu lewat tombol lihat, lalu tekan "2. Kirim Email".',
            $this->terkirim < $this->terbit => 'Tinggal '.($this->terbit - $this->terkirim).' sertifikat yang emailnya belum terkirim.',
            default => 'Semua sertifikat sudah terbit dan terkirim.',
        };
    }

    /**
     * Sertifikat baru sah setelah kegiatannya dipublikasikan. Ini paling
     * sering terlewat karena statusnya diatur di tab lain.
     */
    public function publicationWarning(): ?string
    {
        if ($this->event->status === 'published') {
            return null;
        }

        return $this->event->status === 'archived'
            ? 'Kegiatan ini diarsipkan, jadi sertifikatnya tidak bisa diterbitkan lagi.'
            : 'Kegiatan masih berstatus Draft. Ubah ke Dipublikasikan agar sertifikat sah dan emailnya bisa terkirim.';
    }

    public function isDone(): bool
    {
        return $this->terbit > 0 && $this->terkirim >= $this->terbit && $this->gagal === 0;
    }

    public function failedEmails(): int
    {
        return $this->gagal;
    }
}
