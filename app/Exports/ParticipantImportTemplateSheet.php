<?php

namespace App\Exports;

use App\Enums\ParticipantRole;
use App\Services\Certificates\Import\ParticipantImportParser;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Lembar isian: nama kolom, contoh, dan pengaman agar peran tidak salah ketik.
 */
class ParticipantImportTemplateSheet implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Peserta';
    }

    /**
     * Nama kolom persis seperti yang dibaca importer; menuliskannya dari
     * sumber yang sama mencegah template dan pembacanya berbeda diam-diam.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ParticipantImportParser::KNOWN_HEADERS;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return ParticipantImportTemplate::contoh();
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return ['A' => 34, 'B' => 30, 'C' => 16, 'D' => 22, 'E' => 26, 'F' => 28];
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // Judul kolom dibedakan supaya tidak ikut terhapus atau
                // tertimpa saat data ditempelkan.
                $judul = $sheet->getStyle('A1:F1');
                $judul->getFont()->setBold(true);
                $judul->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
                $judul->getFont()->getColor()->setRGB('FFFFFF');
                $judul->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(22);

                // Dua kolom wajib ditandai agar terlihat sebelum diisi.
                $sheet->getComment('A1')->getText()->createTextRun(
                    "WAJIB. Nama yang dicetak di sertifikat, apa adanya.\n".
                    'Tulis lengkap beserta gelar bila memang ingin tercetak.'
                );
                $sheet->getComment('B1')->getText()->createTextRun(
                    "WAJIB. Alamat email penerima, dipakai sebagai kunci peserta\n".
                    'dan tujuan pengiriman sertifikat. Tidak boleh kembar.'
                );

                // Baris contoh dibuat miring dan kelabu supaya jelas bahwa ia
                // memang harus dihapus, bukan bagian dari data.
                $contoh = $sheet->getStyle('A2:F'.(count(ParticipantImportTemplate::contoh()) + 1));
                $contoh->getFont()->setItalic(true);
                $contoh->getFont()->getColor()->setRGB('6B7280');

                // Peran dikunci menjadi daftar pilihan: mengetiknya sendiri
                // adalah sumber kesalahan yang paling sering pada impor.
                $pilihan = '"'.implode(',', ParticipantRole::importableLabels()).'"';

                for ($baris = 2; $baris <= ParticipantImportParser::MAX_ROWS + 1; $baris++) {
                    $validasi = $sheet->getCell('C'.$baris)->getDataValidation();
                    $validasi->setType(DataValidation::TYPE_LIST);
                    $validasi->setErrorStyle(DataValidation::STYLE_STOP);
                    $validasi->setAllowBlank(true);
                    $validasi->setShowDropDown(true);
                    $validasi->setShowErrorMessage(true);
                    $validasi->setErrorTitle('Peran tidak dikenal');
                    $validasi->setError('Pilih salah satu dari daftar. Kosongkan bila orang ini peserta biasa.');
                    $validasi->setPromptTitle('Peran');
                    $validasi->setPrompt('Kosongkan berarti Peserta.');
                    $validasi->setFormula1($pilihan);
                }

                // NIP dosen dan tendik panjangnya 18 digit, sedangkan Excel hanya
                // menyimpan 15 digit pertama dari sebuah angka: sisanya diganti
                // nol tanpa peringatan. Begitu pula NIM berawalan nol, yang
                // kehilangan nolnya. Kolom berformat Teks menyimpan apa yang
                // diketik apa adanya.
                $akhir = ParticipantImportParser::MAX_ROWS + 1;
                $sheet->getStyle('D2:D'.$akhir)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $sheet->getStyle('F2:F'.$akhir)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                // Contohnya sendiri sudah telanjur ditulis sebagai angka oleh
                // pengisi bawaan, sehingga NIP contoh tampil 1.98001E+17.
                // Ditulis ulang sebagai teks.
                foreach (ParticipantImportTemplate::contoh() as $urutan => $contoh) {
                    $baris = $urutan + 2;
                    $sheet->setCellValueExplicit('D'.$baris, $contoh[3], DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('F'.$baris, $contoh[5], DataType::TYPE_STRING);
                }

                // Nama kolom tetap terlihat saat menggulir ratusan baris.
                $sheet->freezePane('A2');
                $sheet->setSelectedCell('A2');
            },
        ];
    }
}
