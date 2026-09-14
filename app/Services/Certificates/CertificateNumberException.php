<?php

namespace App\Services\Certificates;

use RuntimeException;

class CertificateNumberException extends RuntimeException
{
    public static function exhausted(string $pattern): self
    {
        return new self(
            "Tidak dapat membuat nomor sertifikat yang unik dari pola \"{$pattern}\". "
            .'Pastikan pola memuat token {nomor} agar setiap sertifikat mendapat nomor urut berbeda.'
        );
    }

    public static function perEventCounter(): self
    {
        return new self('Penerbit ini mengulang nomor pada tiap kegiatan, sehingga penghitungnya tidak tunggal dan tidak dapat disetel dari sini.');
    }
}
