<?php

namespace App\Services\Certificates;

use App\Enums\CertificateDesign;

/**
 * Hasil satu permintaan penerbitan.
 *
 * Panel dulu selalu menjawab "diantrekan" tanpa tahu apakah pekerjaannya
 * benar-benar dikerjakan. Dengan hasil yang bernama, jawabannya bisa jujur:
 * berapa yang sungguh terbit, atau bahwa pekerjaannya memang dititipkan ke
 * antrean karena jumlahnya banyak.
 */
final class CertificateIssueOutcome
{
    private function __construct(
        public readonly int $total,
        public readonly int $issued,
        public readonly int $failed,
        public readonly bool $queued,
        public readonly CertificateDesign $design,
    ) {}

    public static function completed(int $issued, int $failed, CertificateDesign $design = CertificateDesign::TEMPLATE): self
    {
        return new self($issued + $failed, $issued, $failed, false, $design);
    }

    public static function queued(int $total, CertificateDesign $design = CertificateDesign::TEMPLATE): self
    {
        return new self($total, 0, 0, true, $design);
    }

    public function title(): string
    {
        if ($this->queued) {
            return 'Penerbitan diantrekan';
        }

        return $this->failed > 0 ? 'Sebagian sertifikat gagal terbit' : 'Sertifikat berhasil diterbitkan';
    }

    public function body(): string
    {
        if ($this->queued) {
            return "{$this->total} sertifikat diproses di latar belakang karena jumlahnya banyak. "
                .'Pastikan pekerja antrean berjalan, lalu segarkan halaman ini.';
        }

        if ($this->design === CertificateDesign::EXTERNAL) {
            $pesan = "Nomor dan QR untuk {$this->issued} sertifikat desain sendiri sudah terbit.";

            if ($this->failed > 0) {
                $pesan .= " {$this->failed} gagal — periksa catatan aplikasi.";
            }

            return $pesan.' Langkah berikutnya: unduh Paket QR dari menu Desain Sendiri, pasang QR-nya di desain, '
                .'lalu unggah berkas jadinya. QR berlaku setelah kegiatan dipublikasikan.';
        }

        $pesan = "{$this->issued} sertifikat terbit dan halaman verifikasinya sudah aktif.";

        if ($this->failed > 0) {
            $pesan .= " {$this->failed} gagal — periksa catatan aplikasi.";
        }

        return $pesan.' Email belum dikirim — tekan Kirim Email setelah hasilnya diperiksa.';
    }

    public function isSuccessful(): bool
    {
        return $this->failed === 0;
    }
}
