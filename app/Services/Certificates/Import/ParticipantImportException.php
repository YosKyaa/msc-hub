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
        return new self('Kolom wajib tidak ditemukan: '.implode(', ', $headers).'. '
            .'Pastikan ada satu baris judul kolom yang memuat nama_sertifikat dan email, '
            .'atau gunakan template import resmi MSC.');
    }

    public static function tooManyRows(int $count, int $limit): self
    {
        return new self("File berisi {$count} baris data, melebihi batas {$limit} baris. Mohon pecah file menjadi beberapa bagian.");
    }

    public static function emptyFile(): self
    {
        return new self('File tidak berisi data apa pun.');
    }

    /**
     * Alasan teknisnya dicatat di log oleh pembaca berkas. Yang perlu
     * diketahui admin adalah apa yang bisa ia periksa sendiri.
     */
    public static function unreadable(): self
    {
        return new self('Berkas tidak dapat dibaca sebagai lembar kerja. Pastikan formatnya .xlsx, .xls, .ods, '
            .'atau .csv, tidak rusak, dan tidak dikunci kata sandi. Bila perlu, buka berkasnya lalu simpan ulang '
            .'sebagai .xlsx.');
    }
}
