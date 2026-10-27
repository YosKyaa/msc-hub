<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Daftar dalam Paket QR: nomor, nama, dan tautan verifikasi setiap penerima,
 * siap ditempel ke desain atau ke Bulk Create di Canva.
 */
class CertificateQrListExport implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    /**
     * @param  list<list<int|string|null>>  $rows
     */
    public function __construct(private readonly array $rows) {}

    public function title(): string
    {
        return 'Daftar QR';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['No', 'Nomor sertifikat', 'Nama penerima', 'Peran', 'Email', 'Tautan verifikasi', 'Berkas QR (PNG)', 'Berkas QR (SVG)'];
    }

    /**
     * @return list<list<int|string|null>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 32, 'C' => 34, 'D' => 22, 'E' => 30, 'F' => 60, 'G' => 48, 'H' => 48];
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $judul = $sheet->getStyle('A1:H1');
                $judul->getFont()->setBold(true);
                $judul->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
                $judul->getFont()->getColor()->setRGB('FFFFFF');
                $judul->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->freezePane('A2');

                // Nomor sertifikat selalu teks. Nomor yang kebetulan hanya
                // berisi angka, misalnya "0012", kehilangan nol di depannya
                // bila dibiarkan menjadi bilangan. Ditulis ulang di sini,
                // bukan lewat WithCustomValueBinder: pengikat itu dipasang
                // global oleh PhpSpreadsheet dan ikut mengubah lembar lain
                // yang dibaca sesudahnya di proses yang sama.
                foreach ($this->rows as $urutan => $baris) {
                    $sheet->getCell('B'.($urutan + 2))->setValueExplicit((string) ($baris[1] ?? ''), DataType::TYPE_STRING);
                }
            },
        ];
    }
}
