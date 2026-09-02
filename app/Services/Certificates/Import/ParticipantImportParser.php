<?php

namespace App\Services\Certificates\Import;

use App\Enums\ParticipantRole;
use App\Models\Certificate;
use App\Services\Certificates\ParticipantRegistry;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Membaca file .xlsx/.csv peserta dan memvalidasinya baris per baris.
 *
 * Parser tidak pernah menulis ke database — hasilnya dipakai untuk pratinjau,
 * lalu ditulis oleh ParticipantImporter setelah admin mengkonfirmasi.
 */
class ParticipantImportParser
{
    public const MAX_ROWS = 500;

    /** Kolom yang harus ada di baris header. */
    public const REQUIRED_HEADERS = ['nama_sertifikat', 'email'];

    /** Seluruh kolom yang dikenali; selain ini diabaikan dengan peringatan. */
    public const KNOWN_HEADERS = ['nama_sertifikat', 'email', 'peran', 'nim_nip', 'unit_prodi', 'nomor_sertifikat'];

    public function parse(string $absolutePath): ParticipantImportPreview
    {
        $rows = $this->readRows($absolutePath);
        $header = $this->readHeader(array_shift($rows));

        $this->guardRowCount($rows);

        $validRows = [];
        $problems = [];
        $seenEmails = [];

        foreach ($rows as $index => $row) {
            // Header ada di baris 1, sehingga data pertama berada di baris 2.
            $line = $index + 2;
            $values = $this->mapRow($header, $row);

            if ($this->isBlank($values)) {
                continue;
            }

            $error = $this->validate($values, $seenEmails);

            if ($error !== null) {
                $problems[] = ['line' => $line, 'message' => $error];

                continue;
            }

            $email = ParticipantRegistry::normaliseEmail($values['email']);
            $seenEmails[$email] = $line;

            $validRows[] = new ParticipantImportRow(
                line: $line,
                name: $values['nama_sertifikat'],
                email: $email,
                role: ParticipantRole::fromLabel($values['peran'] ?? '') ?? ParticipantRole::PARTICIPANT,
                institutionalId: $values['nim_nip'] ?: null,
                studyProgram: $values['unit_prodi'] ?: null,
                certificateNumber: $values['nomor_sertifikat'] ?: null,
            );
        }

        return new ParticipantImportPreview($validRows, $problems, $this->unknownHeaderWarnings($header));
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $absolutePath): array
    {
        try {
            $sheets = Excel::toArray(new RawSheetImport, $absolutePath);
        } catch (Throwable $exception) {
            throw ParticipantImportException::unreadable($exception->getMessage());
        }

        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            throw ParticipantImportException::emptyFile();
        }

        return $rows;
    }

    /**
     * Pemetaan nama kolom ke indeksnya. Pencocokan berdasarkan nama, bukan
     * posisi, sehingga urutan kolom boleh berbeda.
     *
     * @param  array<int, mixed>|null  $headerRow
     * @return array<string, int>
     */
    private function readHeader(?array $headerRow): array
    {
        $header = [];

        foreach ($headerRow ?? [] as $index => $label) {
            $key = mb_strtolower(trim((string) $label));

            if ($key !== '' && ! isset($header[$key])) {
                $header[$key] = $index;
            }
        }

        $missing = array_values(array_diff(self::REQUIRED_HEADERS, array_keys($header)));

        if ($missing !== []) {
            throw ParticipantImportException::missingHeaders($missing);
        }

        return $header;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function guardRowCount(array $rows): void
    {
        $dataRows = array_filter($rows, fn (array $row) => ! $this->isBlankRow($row));

        if (count($dataRows) > self::MAX_ROWS) {
            throw ParticipantImportException::tooManyRows(count($dataRows), self::MAX_ROWS);
        }
    }

    /**
     * @param  array<string, int>  $header
     * @param  array<int, mixed>  $row
     * @return array<string, string>
     */
    private function mapRow(array $header, array $row): array
    {
        $values = [];

        foreach (self::KNOWN_HEADERS as $column) {
            $index = $header[$column] ?? null;
            $values[$column] = $index === null ? '' : trim((string) ($row[$index] ?? ''));
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, int>  $seenEmails
     */
    private function validate(array $values, array $seenEmails): ?string
    {
        if ($values['nama_sertifikat'] === '') {
            return 'Kolom nama_sertifikat wajib diisi.';
        }

        if ($values['email'] === '') {
            return 'Kolom email wajib diisi.';
        }

        $email = ParticipantRegistry::normaliseEmail($values['email']);

        if (Validator::make(['email' => $email], ['email' => 'email:rfc'])->fails()) {
            return "Format email tidak valid: {$values['email']}.";
        }

        if (isset($seenEmails[$email])) {
            return "Email {$email} duplikat dengan baris {$seenEmails[$email]} pada file yang sama.";
        }

        if ($values['peran'] !== '' && ParticipantRole::fromLabel($values['peran']) === null) {
            return "Peran \"{$values['peran']}\" tidak dikenal. Nilai yang diterima: "
                .implode(', ', ParticipantRole::importableLabels()).'.';
        }

        if ($values['nomor_sertifikat'] !== '' && $this->certificateNumberTaken($values['nomor_sertifikat'])) {
            return "Nomor sertifikat {$values['nomor_sertifikat']} sudah dipakai.";
        }

        return null;
    }

    private function certificateNumberTaken(string $number): bool
    {
        return Certificate::where('certificate_number', $number)->exists();
    }

    /**
     * @param  array<string, string>  $values
     */
    private function isBlank(array $values): bool
    {
        return implode('', $values) === '';
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, int>  $header
     * @return array<int, string>
     */
    private function unknownHeaderWarnings(array $header): array
    {
        $unknown = array_values(array_diff(array_keys($header), self::KNOWN_HEADERS));

        return $unknown === []
            ? []
            : ['Kolom berikut tidak dikenal dan diabaikan: '.implode(', ', $unknown).'.'];
    }
}
