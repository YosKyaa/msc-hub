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
}
