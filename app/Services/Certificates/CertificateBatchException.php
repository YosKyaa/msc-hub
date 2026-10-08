<?php

namespace App\Services\Certificates;

use RuntimeException;

/**
 * Batch penerbitan ditolak sebelum satu job pun diantrekan.
 */
class CertificateBatchException extends RuntimeException
{
    public static function inactiveTemplate(): self
    {
        return new self('Kegiatan ini belum memakai template sertifikat yang aktif.');
    }

    public static function nothingToIssue(): self
    {
        return new self('Tidak ada peserta eligible yang belum memiliki sertifikat.');
    }

    public static function eventNotPublished(): self
    {
        return new self('Kegiatan belum dipublikasikan. Tautan verifikasi di dalam email belum akan berfungsi.');
    }

    public static function nothingToEmail(): self
    {
        return new self('Tidak ada sertifikat yang menunggu dikirim. Pastikan penerimanya punya alamat email.');
    }

    /**
     * Kuota harian akun pengirim sudah habis.
     *
     * Ditolak di sini, bukan diserahkan ke penyedia email: Gmail yang
     * menerima kiriman melewati kuotanya mengunci akun itu sampai 24 jam,
     * dan selama itu seluruh email aplikasi ikut tertahan, termasuk
     * pemberitahuan peminjaman.
     */
    public static function dailyLimitReached(int $limit, string $tersediaLagi): self
    {
        return new self("Kuota harian akun pengirim sudah habis: {$limit} email dalam 24 jam terakhir. "
            ."Kuota mulai tersedia lagi {$tersediaLagi}. Sertifikat tetap berstatus menunggu kirim, "
            .'jadi cukup tekan Kirim Email lagi setelah itu.');
    }
}
