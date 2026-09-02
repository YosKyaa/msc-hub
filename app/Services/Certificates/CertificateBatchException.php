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
}
