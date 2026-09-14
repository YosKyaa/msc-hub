<?php

namespace App\Services\Certificates\Import;

/**
 * Laporan akhir setelah baris valid ditulis ke database.
 */
class ParticipantImportResult
{
    /**
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly int $created = 0,
        public readonly int $skipped = 0,
        public readonly array $warnings = [],
    ) {}

    public function summary(): string
    {
        return "{$this->created} peserta dibuat, {$this->skipped} dilewati karena sudah terdaftar.";
    }
}
