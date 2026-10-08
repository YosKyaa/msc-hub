<?php

namespace App\Services\Certificates\Import;

use App\Enums\ParticipantRole;
use App\Exports\ParticipantImportTemplate;
use App\Models\Certificate;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Services\Certificates\ParticipantRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

/**
 * Membaca file .xlsx/.xls/.ods/.csv peserta dan memvalidasinya baris per baris.
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

    /**
     * Ejaan lain untuk kolom email yang lazim muncul di berkas buatan sendiri
     * atau ekspor Google Form. Kolom lain sengaja tidak diberi padanan:
     * "nama" saja, misalnya, tidak menjelaskan apakah itu nama yang dicetak.
     */
    private const HEADER_ALIASES = [
        'e_mail' => 'email',
        'email_address' => 'email',
        'alamat_email' => 'email',
    ];

    /** Berapa baris berisi teratas yang diperiksa untuk mencari baris judul. */
    private const HEADER_SEARCH_ROWS = 10;

    /** Sama dengan batas nama pada absensi mandiri. */
    private const MAX_NAME_LENGTH = 150;

    private const MAX_TEXT_LENGTH = 255;

    /** Nilai yang ditampilkan Excel ketika sebuah rumus gagal. */
    private const EXCEL_ERRORS = ['#NULL!', '#DIV/0!', '#VALUE!', '#REF!', '#NAME?', '#NUM!', '#N/A', '#SPILL!', '#CALC!', '#GETTING_DATA'];

    public function __construct(private readonly SpreadsheetReader $reader) {}

    public function parse(string $absolutePath): ParticipantImportPreview
    {
        $sheets = $this->reader->read($absolutePath);

        [$sheetIndex, $headerLine, $header, $labels] = $this->locateHeader($sheets);

        $rows = array_filter(
            $sheets[$sheetIndex]['rows'],
            fn (int $line) => $line > $headerLine,
            ARRAY_FILTER_USE_KEY,
        );

        $this->guardRowCount($rows);

        $validRows = [];
        $problems = [];
        $warnings = [...$this->unknownHeaderWarnings($header, $labels), ...$this->sheetWarnings($sheets, $sheetIndex)];
        $seenEmails = [];
        $seenNumbers = [];

        foreach ($rows as $line => $row) {
            $values = $this->mapRow($header, $row);

            if ($this->isBlank($values)) {
                continue;
            }

            $values['email'] = ParticipantRegistry::normaliseEmail($values['email']);

            $error = $this->validate($values, $seenEmails, $seenNumbers);

            if ($error !== null) {
                $problems[] = ['line' => $line, 'message' => $error];

                continue;
            }

            $nimNip = $values['nim_nip'];

            // Digit setelah yang ke-15 sudah hilang di Excel, jadi nilai ini
            // pasti keliru. Barisnya tetap diimpor — NIM/NIP hanya untuk
            // pencatatan — tetapi nilainya tidak disimpan.
            if ($this->isTruncatedNumber($nimNip)) {
                $warnings[] = "Baris {$line}: nim_nip terbaca {$nimNip} karena Excel menyimpannya sebagai angka "
                    .'dan memotong digit setelah ke-15. Nilainya dikosongkan; ubah kolomnya menjadi Teks lalu ketik ulang bila perlu.';
                $nimNip = '';
            }

            $seenEmails[$values['email']] = $line;

            if ($values['nomor_sertifikat'] !== '') {
                $seenNumbers[$values['nomor_sertifikat']] = $line;
            }

            $validRows[] = new ParticipantImportRow(
                line: $line,
                name: $values['nama_sertifikat'],
                email: $values['email'],
                role: ParticipantRole::fromLabel($values['peran']) ?? ParticipantRole::PARTICIPANT,
                institutionalId: $nimNip ?: null,
                studyProgram: $values['unit_prodi'] ?: null,
                certificateNumber: $values['nomor_sertifikat'] ?: null,
            );
        }

        return new ParticipantImportPreview(
            $validRows,
            $problems,
            [...$warnings, ...$this->masterNameWarnings($validRows)],
        );
    }

    /**
     * Cari lembar dan baris judul kolomnya.
     *
     * Template meletakkan judul di baris pertama lembar pertama, tetapi
     * daftar hadir buatan panitia kerap diawali judul kegiatan, atau datanya
     * ada di lembar kedua setelah lembar rekap. Menolak berkas seperti itu
     * hanya karena letaknya bergeser membuat orang menyusun ulang berkas yang
     * isinya sudah benar.
     *
     * @param  list<array{title: string, rows: array<int, array<int, string>>}>  $sheets
     * @return array{0: int, 1: int, 2: array<string, int>, 3: array<string, string>}
     *
     * @throws ParticipantImportException
     */
    private function locateHeader(array $sheets): array
    {
        $adaIsi = false;

        foreach ($sheets as $sheetIndex => $sheet) {
            $adaIsi = $adaIsi || $sheet['rows'] !== [];

            foreach (array_slice($sheet['rows'], 0, self::HEADER_SEARCH_ROWS, true) as $line => $row) {
                [$header, $labels] = $this->headerMap($row);

                if (array_diff(self::REQUIRED_HEADERS, array_keys($header)) === []) {
                    return [$sheetIndex, $line, $header, $labels];
                }
            }
        }

        if (! $adaIsi) {
            throw ParticipantImportException::emptyFile();
        }

        // Yang disebut hilang diukur dari baris berisi pertama di lembar
        // pertama: di sanalah orang paling mungkin menaruh judul kolom.
        $pertama = collect($sheets)->first(fn (array $sheet) => $sheet['rows'] !== []);
        [$header] = $this->headerMap(reset($pertama['rows']));

        throw ParticipantImportException::missingHeaders(
            array_values(array_diff(self::REQUIRED_HEADERS, array_keys($header))),
        );
    }

    /**
     * Pemetaan nama kolom ke indeksnya, beserta tulisan aslinya untuk pesan.
     *
     * Judul diseragamkan lebih dulu: "Nama Sertifikat", "NIM/NIP", dan
     * "Unit / Prodi" terbaca sebagai nama_sertifikat, nim_nip, dan
     * unit_prodi. Mengetik ulang judul kolom dengan huruf besar dan spasi
     * adalah hal yang wajar, bukan kesalahan.
     *
     * @param  array<int, string>  $row
     * @return array{0: array<string, int>, 1: array<string, string>}
     */
    private function headerMap(array $row): array
    {
        $header = [];
        $labels = [];

        foreach ($row as $index => $label) {
            $key = $this->headerKey($label);
            $key = self::HEADER_ALIASES[$key] ?? $key;

            if ($key !== '' && ! isset($header[$key])) {
                $header[$key] = $index;
                $labels[$key] = $this->cleanText($label);
            }
        }

        return [$header, $labels];
    }

    private function headerKey(string $label): string
    {
        $key = mb_strtolower($this->cleanText($label));
        $key = preg_replace('/[^\p{L}\p{N}]+/u', '_', $key) ?? $key;

        return trim($key, '_');
    }

    /**
     * @param  array<int, array<int, string>>  $rows
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
     * @param  array<int, string>  $row
     * @return array<string, string>
     */
    private function mapRow(array $header, array $row): array
    {
        $values = [];

        foreach (self::KNOWN_HEADERS as $column) {
            $index = $header[$column] ?? null;
            $values[$column] = $index === null ? '' : $this->cleanText($row[$index] ?? '');
        }

        return $values;
    }

    /**
     * Bersihkan isi sel dari apa yang tidak pernah dimaksudkan penulisnya.
     *
     * Teks yang disalin dari halaman web, Google Sheets, atau WhatsApp kerap
     * membawa spasi tak putus dan karakter lebar-nol, dan Alt+Enter di Excel
     * menyisipkan baris baru di tengah sel. Semuanya tidak terlihat di layar,
     * tetapi ikut tercetak di sertifikat atau membuat email tidak dikenali.
     */
    private function cleanText(string $value): string
    {
        $value = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $value) ?? $value;
        $value = preg_replace('/[\s\p{Z}]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Pemeriksaan bentuk lebih dulu, baris contoh paling akhir: contoh
     * template yang lupa dihapus pun harus berformat benar, jadi yang
     * menolaknya cukup satu alasan itu.
     *
     * @param  array<string, string>  $values
     * @param  array<string, int>  $seenEmails
     * @param  array<string, int>  $seenNumbers
     */
    private function validate(array $values, array $seenEmails, array $seenNumbers): ?string
    {
        $nama = $values['nama_sertifikat'];
        $email = $values['email'];

        if ($nama === '') {
            return 'Kolom nama_sertifikat wajib diisi.';
        }

        if ($email === '') {
            return 'Kolom email wajib diisi.';
        }

        foreach (['nama_sertifikat' => $nama, 'email' => $email] as $kolom => $isi) {
            if ($this->isFormulaLeftover($isi)) {
                return "Kolom {$kolom} berisi rumus yang tidak menghasilkan nilai ({$isi}). "
                    .'Periksa rumusnya di Excel, atau salin lalu tempel sebagai nilai (Paste Values).';
            }
        }

        if (preg_match('/\p{L}/u', $nama) !== 1) {
            return "Nama \"{$nama}\" tidak berisi huruf. Periksa apakah isi kolomnya tertukar.";
        }

        if (mb_strlen($nama) > self::MAX_NAME_LENGTH) {
            return 'Nama terlalu panjang ('.mb_strlen($nama).' karakter); maksimal '.self::MAX_NAME_LENGTH.' karakter.';
        }

        if (Validator::make(['email' => $email], ['email' => 'email:rfc'])->fails()) {
            return "Format email tidak valid: {$email}.";
        }

        // Aturan RFC menerima "budi@gmail", yang tidak akan pernah sampai.
        if (preg_match('/@[^@\s]+\.\p{L}{2,}$/u', $email) !== 1) {
            return "Alamat email tidak lengkap: {$email}. Bagian setelah @ harus domain utuh, misalnya gmail.com.";
        }

        if (mb_strlen($email) > self::MAX_TEXT_LENGTH) {
            return 'Alamat email terlalu panjang; maksimal '.self::MAX_TEXT_LENGTH.' karakter.';
        }

        if (isset($seenEmails[$email])) {
            return "Email {$email} duplikat dengan baris {$seenEmails[$email]} pada file yang sama.";
        }

        if ($values['peran'] !== '' && ParticipantRole::fromLabel($values['peran']) === null) {
            return "Peran \"{$values['peran']}\" tidak dikenal. Nilai yang diterima: "
                .implode(', ', ParticipantRole::importableLabels()).'.';
        }

        foreach (['nim_nip', 'unit_prodi', 'nomor_sertifikat'] as $kolom) {
            if (mb_strlen($values[$kolom]) > self::MAX_TEXT_LENGTH) {
                return "Kolom {$kolom} terlalu panjang; maksimal ".self::MAX_TEXT_LENGTH.' karakter.';
            }
        }

        $masalahNomor = $this->certificateNumberProblem($values['nomor_sertifikat'], $email, $seenNumbers);

        if ($masalahNomor !== null) {
            return $masalahNomor;
        }

        if (ParticipantImportTemplate::isExample($nama, $email, $values['nim_nip'], $values['unit_prodi'])) {
            return 'Baris contoh dari template. Hapus baris ini sebelum mengunggah.';
        }

        return null;
    }

    /**
     * Nomor yang ditetapkan manual harus tetap unik sampai sertifikatnya
     * terbit. Bentrokan yang lolos di sini baru ketahuan saat penerbitan,
     * ketika salah satu sertifikat gagal terbit tanpa admin tahu sebabnya.
     *
     * @param  array<string, int>  $seenNumbers
     */
    private function certificateNumberProblem(string $nomor, string $email, array $seenNumbers): ?string
    {
        if ($nomor === '') {
            return null;
        }

        if ($this->isTruncatedNumber($nomor)) {
            return "Nomor sertifikat terbaca {$nomor} karena Excel menyimpannya sebagai angka. "
                .'Ubah kolom nomor_sertifikat menjadi Teks lalu ketik ulang nomornya.';
        }

        if (isset($seenNumbers[$nomor])) {
            return "Nomor sertifikat {$nomor} kembar dengan baris {$seenNumbers[$nomor]} pada file yang sama.";
        }

        if (Certificate::where('certificate_number', $nomor)->exists()) {
            return "Nomor sertifikat {$nomor} sudah dipakai.";
        }

        // Disiapkan untuk orang lain tetapi belum terbit. Milik orang yang
        // sama tidak dihitung, supaya mengimpor ulang berkas yang sama tidak
        // menolak barisnya sendiri.
        $dipesan = CertificateEventParticipant::query()
            ->where('certificate_number', $nomor)
            ->whereDoesntHave('participant', fn (Builder $query) => $query->where('email', $email))
            ->exists();

        return $dipesan ? "Nomor sertifikat {$nomor} sudah disiapkan untuk peserta lain." : null;
    }

    /**
     * Peserta yang sudah terdaftar memakai nama di sistem, bukan nama di
     * berkas: nama master hanya diubah lewat koreksi admin. Disebut sejak
     * pratinjau, karena nama itulah yang akan tercetak.
     *
     * @param  array<int, ParticipantImportRow>  $rows
     * @return array<int, string>
     */
    private function masterNameWarnings(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $master = Participant::query()
            ->whereIn('email', array_map(fn (ParticipantImportRow $row) => $row->email, $rows))
            ->pluck('name', 'email');

        $warnings = [];

        foreach ($rows as $row) {
            $nama = $master[$row->email] ?? null;

            if ($nama !== null && $nama !== $row->name) {
                $warnings[] = "Baris {$row->line}: {$row->email} sudah terdaftar atas nama \"{$nama}\". "
                    ."Nama di berkas (\"{$row->name}\") tidak dipakai; sertifikat memakai nama master. "
                    .'Ubah lewat tombol Koreksi Data Penerima bila perlu.';
            }
        }

        return $warnings;
    }

    private function isFormulaLeftover(string $value): bool
    {
        return str_starts_with($value, '=') || in_array(mb_strtoupper($value), self::EXCEL_ERRORS, true);
    }

    /**
     * Angka di atas lima belas digit yang ditulis Excel dalam notasi E,
     * misalnya NIP 18 digit yang diketik di kolom berformat angka.
     */
    private function isTruncatedNumber(string $value): bool
    {
        return preg_match('/^\d(\.\d+)?E\+?\d+$/i', $value) === 1;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function isBlank(array $values): bool
    {
        return implode('', $values) === '';
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanText($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, int>  $header
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    private function unknownHeaderWarnings(array $header, array $labels): array
    {
        $unknown = array_values(array_diff(array_keys($header), self::KNOWN_HEADERS));

        return $unknown === []
            ? []
            : ['Kolom berikut tidak dikenal dan diabaikan: '.implode(', ', array_map(fn (string $key) => $labels[$key], $unknown)).'.'];
    }

    /**
     * Hanya satu lembar yang dibaca. Bila datanya bukan di lembar pertama,
     * atau lembar lain juga tampak berisi daftar peserta, admin harus tahu —
     * peserta di lembar yang tidak terbaca akan hilang tanpa jejak.
     *
     * @param  list<array{title: string, rows: array<int, array<int, string>>}>  $sheets
     * @return array<int, string>
     */
    private function sheetWarnings(array $sheets, int $dipakai): array
    {
        if (count($sheets) === 1) {
            return [];
        }

        $warnings = [];

        if ($dipakai !== 0) {
            $warnings[] = "Data dibaca dari lembar \"{$sheets[$dipakai]['title']}\".";
        }

        foreach ($sheets as $index => $sheet) {
            if ($index === $dipakai) {
                continue;
            }

            foreach (array_slice($sheet['rows'], 0, self::HEADER_SEARCH_ROWS, true) as $row) {
                [$header] = $this->headerMap($row);

                if (array_diff(self::REQUIRED_HEADERS, array_keys($header)) === []) {
                    $warnings[] = "Lembar \"{$sheet['title']}\" juga berisi daftar peserta tetapi tidak ikut dibaca. "
                        .'Impor lembar itu sebagai berkas tersendiri.';

                    break;
                }
            }
        }

        return $warnings;
    }
}
