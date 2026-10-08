<?php

namespace App\Services\Certificates\Import;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Membaca seluruh lembar sebuah berkas .xlsx, .xls, .ods, atau .csv menjadi
 * teks mentah, tanpa menafsirkan isinya.
 *
 * Dulu pembacaan diserahkan ke Laravel Excel, yang hanya memberi lembar
 * pertama dan menyerahkan isi sel rumus apa adanya. Nama yang disusun dengan
 * =PROPER(C2) karena itu terbaca sebagai teks "=PROPER(C2)", dan bisa
 * tercetak begitu saja di sertifikat. Di sini sel rumus dibaca dari nilai
 * yang disimpan Excel saat berkasnya terakhir disimpan, persis yang terlihat
 * oleh orang yang menyusunnya.
 */
class SpreadsheetReader
{
    /**
     * Baris dikunci dengan nomor barisnya di lembar kerja, dan hanya baris
     * yang punya isi yang disertakan.
     *
     * @return list<array{title: string, rows: array<int, array<int, string>>}>
     *
     * @throws ParticipantImportException
     */
    public function read(string $absolutePath): array
    {
        try {
            $reader = IOFactory::createReaderForFile($absolutePath);

            // Pemisah CSV dan BOM dikenali sendiri oleh pembacanya; yang perlu
            // diminta hanya menebak encoding, karena CSV dari Excel Windows
            // kerap masih berupa ANSI, bukan UTF-8.
            if ($reader instanceof Csv) {
                $reader->setInputEncoding(Csv::GUESS_ENCODING);
            }

            $book = $reader->load($absolutePath);
        } catch (Throwable $exception) {
            // Alasan aslinya berbahasa Inggris dan teknis; disimpan di log,
            // sedangkan admin menerima penjelasan yang bisa ditindaklanjuti.
            Log::warning('Berkas impor peserta tidak dapat dibaca.', [
                'berkas' => basename($absolutePath),
                'alasan' => $exception->getMessage(),
            ]);

            throw ParticipantImportException::unreadable();
        }

        $sheets = [];

        foreach ($book->getWorksheetIterator() as $sheet) {
            $sheets[] = ['title' => $sheet->getTitle(), 'rows' => $this->rows($sheet)];
        }

        $book->disconnectWorksheets();

        return $sheets;
    }

    /**
     * Hanya sel yang benar-benar ada yang disentuh. Menyusuri setiap baris
     * sampai baris tertinggi akan berjalan sejuta kali bila satu sel nyasar
     * di dasar lembar, padahal isinya hanya beberapa ratus baris.
     *
     * @return array<int, array<int, string>>
     */
    private function rows(Worksheet $sheet): array
    {
        $rows = [];

        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            $value = $this->value($sheet->getCell($coordinate));

            if (trim($value) === '') {
                continue;
            }

            [$column, $row] = Coordinate::coordinateFromString($coordinate);
            $rows[(int) $row][Coordinate::columnIndexFromString($column) - 1] = $value;
        }

        ksort($rows);

        return $rows;
    }

    private function value(Cell $cell): string
    {
        $value = $cell->getValue();

        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            $value = $cell->getOldCalculatedValue() ?? $this->calculate($cell);
        }

        return $this->stringify($value);
    }

    /**
     * Rumus tanpa nilai tersimpan (CSV, atau berkas buatan program lain)
     * dihitung di sini. Bila gagal, teks rumusnya yang dikembalikan, supaya
     * parser bisa menolaknya dengan alasan yang jelas alih-alih mencetaknya.
     */
    private function calculate(Cell $cell): mixed
    {
        try {
            return $cell->getCalculatedValue();
        } catch (Throwable) {
            return $cell->getValue();
        }
    }

    private function stringify(mixed $value): string
    {
        if ($value instanceof RichText) {
            return $value->getPlainText();
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        // NIM yang diketik sebagai angka tersimpan sebagai float. Yang masih
        // utuh ditulis kembali tanpa ".0" maupun notasi E. Di atas lima belas
        // digit Excel sendiri sudah memotongnya, jadi bentuk E-nya dibiarkan
        // agar parser bisa mengenalinya dan memperingatkan.
        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        return (string) $value;
    }
}
