<?php

namespace App\Services\Certificates;

use App\Enums\CertificateDesign;
use App\Exports\CertificateQrListExport;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Services\QrCodeGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use ZipArchive;

/**
 * Paket QR untuk sertifikat desain sendiri.
 *
 * Satu QR untuk setiap penerima dalam dua bentuk — SVG untuk aplikasi desain
 * dan PNG resolusi tinggi untuk yang tidak menerima SVG — beserta daftar
 * nomor dan nama yang siap ditempel, dan petunjuk singkat bagi desainernya.
 * Nama berkasnya diawali nomor sertifikat, sehingga berkas jadi yang diberi
 * nama serupa bisa dicocokkan kembali secara otomatis saat diunggah.
 */
final class CertificateQrPack
{
    public function __construct(private readonly QrCodeGenerator $qr) {}

    /**
     * Sertifikat yang masuk paket: desain sendiri dan belum dicabut.
     *
     * @return Collection<int, Certificate>
     */
    public static function certificatesFor(CertificateEvent $event, ?Collection $only = null): Collection
    {
        $query = $event->certificates()
            ->where('design_source', CertificateDesign::EXTERNAL->value)
            ->whereNull('revoked_at');

        if ($only !== null) {
            $query->whereKey($only->pluck('id')->all());
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Bentuk berkas ZIP di folder sementara. Pemanggil mengirimkannya lalu
     * menghapusnya.
     *
     * @param  Collection<int, Certificate>  $certificates
     */
    public function build(CertificateEvent $event, Collection $certificates): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'paket-qr-');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Paket QR tidak bisa dibuat di folder sementara server.');
        }

        $baris = [];

        foreach ($certificates->values() as $urutan => $certificate) {
            $nama = self::fileStem($certificate);
            $tautan = $certificate->verificationUrl();

            $zip->addFromString("QR/{$nama}.svg", $this->qr->printableSvg($tautan));
            $zip->addFromString("QR/{$nama}.png", $this->qr->printablePng($tautan));

            $baris[] = [
                $urutan + 1,
                $certificate->certificate_number,
                $certificate->recipient_name,
                $certificate->recipient_role_label ?: $certificate->recipient_role,
                $certificate->recipient_email,
                $tautan,
                "QR/{$nama}.png",
                "QR/{$nama}.svg",
            ];
        }

        $zip->addFromString('Daftar QR.xlsx', Excel::raw(new CertificateQrListExport($baris), ExcelFormat::XLSX));
        $zip->addFromString('PETUNJUK.txt', $this->instructions($event, $certificates->first()));
        $zip->close();

        return $path;
    }

    /**
     * Nama berkas untuk seorang penerima: nomor sertifikat, lalu namanya —
     * "0001-CERT-MSC-JGU-IX-2026 - Budi Hartono". Hanya huruf dasar, supaya
     * terbaca sama di Windows, macOS, dan ponsel setelah diekstrak.
     */
    public static function fileStem(Certificate $certificate): string
    {
        $nomor = Certificate::flattenNumber($certificate->certificate_number) ?: $certificate->verification_token;
        $nama = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^A-Za-z0-9 .,\'-]+/', ' ', Str::ascii((string) $certificate->recipient_name))));
        $nama = rtrim(Str::limit($nama, 60, ''), ' .');

        return $nama === '' ? $nomor : "{$nomor} - {$nama}";
    }

    public static function downloadName(CertificateEvent $event): string
    {
        return 'Paket QR - '.(Str::slug($event->name) ?: 'kegiatan').'.zip';
    }

    private function instructions(CertificateEvent $event, ?Certificate $contoh): string
    {
        $contohBerkas = $contoh !== null ? self::fileStem($contoh).'.pdf' : '0001-CERT-MSC-JGU-IX-2026 - Nama Penerima.pdf';

        $baris = [
            'PAKET QR SERTIFIKAT DESAIN SENDIRI',
            $event->name,
            'Dibuat '.now()->translatedFormat('d F Y H:i').' WIB',
            '',
            'ISI PAKET',
            '- Folder QR: satu QR untuk setiap penerima, dalam dua bentuk.',
            '  SVG : vektor, tetap tajam di ukuran berapa pun. Pakai ini di Canva, Illustrator, atau Photoshop.',
            '  PNG : 1440 x 1440 piksel, untuk aplikasi yang tidak menerima SVG.',
            '- Daftar QR.xlsx: nomor sertifikat, nama, peran, dan tautan verifikasi setiap penerima.',
            '',
            'MEMASANG QR DI DESAIN',
            '1. Cocokkan QR dengan penerimanya lewat nama berkas: nomor sertifikat, lalu nama.',
            '2. Cetak QR minimal 2,5 x 2,5 cm. Jangan dipotong, diregangkan, atau diubah warnanya.',
            '3. Biarkan tepi putih di sekeliling QR, dan letakkan di atas bidang yang terang.',
            '4. Tulis nomor sertifikat, nama, dan peran persis seperti di Daftar QR.xlsx.',
            '   Pemeriksa membandingkannya dengan halaman verifikasi saat memindai QR.',
            '5. Pindai hasil akhirnya dengan ponsel sebelum dicetak atau dibagikan.',
            '',
            'MENGUNGGAH HASILNYA (DISARANKAN)',
            '- Ekspor setiap sertifikat sebagai PDF, JPG, atau PNG.',
            '- Beri nama berkas diawali nomor sertifikatnya, sama seperti nama berkas QR, misalnya:',
            '  '.$contohBerkas,
            '- Unggah lewat menu Desain Sendiri > Unggah Berkas Desain: setiap berkas dicocokkan sendiri',
            '  dengan penerimanya. Setelah itu halaman verifikasi menampilkan desain aslinya, dan',
            '  penerima bisa mengunduhnya dari email.',
            '',
            'QR berlaku setelah kegiatan dipublikasikan. Sebelum itu, halaman verifikasinya',
            'menyatakan sertifikat belum berlaku.',
        ];

        return implode("\r\n", $baris)."\r\n";
    }
}
