<?php

namespace App\Services\Certificates\Import;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Pembaca sheet apa adanya: baris pertama tetap dianggap data mentah supaya
 * pencocokan header dilakukan sendiri oleh ParticipantImportParser.
 */
class RawSheetImport implements ToArray
{
    public function array(array $array): void
    {
        // Tidak ada pemrosesan di sini; Excel::toArray sudah mengembalikan isinya.
    }
}
