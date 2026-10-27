<?php

namespace App\Enums;

/**
 * Siapa yang menggambar sertifikat.
 *
 * Template berarti sistem mencetak nama, nomor, dan QR di atas desain latar
 * kegiatan. Desain sendiri berarti sistem hanya menerbitkan nomor dan QR
 * verifikasinya lebih dulu; sertifikatnya didesain di luar — misalnya untuk
 * pemenang dan pembicara — lalu berkas jadinya boleh diunggah kembali.
 */
enum CertificateDesign: string
{
    case TEMPLATE = 'template';
    case EXTERNAL = 'external';

    public function getLabel(): string
    {
        return match ($this) {
            self::TEMPLATE => 'Template sistem',
            self::EXTERNAL => 'Desain sendiri',
        };
    }
}
