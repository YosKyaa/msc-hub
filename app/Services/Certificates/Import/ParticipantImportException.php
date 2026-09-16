<?php

namespace App\Services\Certificates\Import;

use RuntimeException;

/**
 * File ditolak seluruhnya (bukan sekadar baris bermasalah).
 */
class ParticipantImportException extends RuntimeException
{
    public static function missingHeaders(array $headers): self
    {
        return new self('Kolom wajib tidak ditemukan: '.implode(', ', $headers).'. Gunakan template import resmi MSC.');
    }

    public static function tooManyRows(int $count, int $limit): self
    {
        return new self("File berisi {$count} baris data, melebihi batas {$limit} baris. Mohon pecah file menjadi beberapa bagian.");
    }

    public static function emptyFile(): self
    {
        return new self('File tidak berisi data apa pun.');
    }

    public static function unreadable(string $reason): self
    {
        return new self('File tidak dapat dibaca: '.$reason);
    }
}
