<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Lembar petunjuk, supaya penjelasannya ikut terbawa bersama berkasnya dan
 * tidak hilang begitu berkas itu diteruskan ke orang lain.
 */
class ParticipantImportGuideSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $baris = [
            ['PETUNJUK PENGISIAN', '', '', ''],
            ['', '', '', ''],
            ['Kolom', 'Wajib?', 'Isinya apa', 'Yang perlu diperhatikan'],
        ];

        foreach (ParticipantImportTemplate::penjelasanKolom() as $kolom) {
            $baris[] = $kolom;
        }

        $baris[] = ['', '', '', ''];
        $baris[] = ['ATURAN BERKAS', '', '', ''];
        $baris[] = ['', '', '', ''];

        foreach (ParticipantImportTemplate::aturan() as $nomor => $aturan) {
            $baris[] = [($nomor + 1).'.', $aturan, '', ''];
        }

        return $baris;
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return ['A' => 22, 'B' => 12, 'C' => 42, 'D' => 72];
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:D1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $kepala = $sheet->getStyle('A3:D3');
                $kepala->getFont()->setBold(true);
                $kepala->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');

                $jumlahKolom = count(ParticipantImportTemplate::penjelasanKolom());
                $barisAturan = $jumlahKolom + 5;

                $sheet->mergeCells('A'.$barisAturan.':D'.$barisAturan);
                $sheet->getStyle('A'.$barisAturan)->getFont()->setBold(true)->setSize(12);

                // Kolom wajib ditebalkan supaya terbaca sekilas.
                for ($baris = 4; $baris <= $jumlahKolom + 3; $baris++) {
                    if ($sheet->getCell('B'.$baris)->getValue() === 'WAJIB') {
                        $sheet->getStyle('A'.$baris.':B'.$baris)->getFont()->setBold(true);
                        $sheet->getStyle('B'.$baris)->getFont()->getColor()->setRGB('B91C1C');
                    }
                }

                $sheet->getStyle('A1:D'.($sheet->getHighestRow()))
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            },
        ];
    }
}
