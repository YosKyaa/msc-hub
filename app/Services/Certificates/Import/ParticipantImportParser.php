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

    /**
     * Kolom yang menandai seseorang. Baris yang ketiganya kosong tidak
     * memuat siapa pun, walau peran atau prodinya terisi: panitia kerap
     * mengisi kolom peran sampai ratusan baris ke bawah sebelum namanya.
     */
    private const IDENTITY_COLUMNS = ['nama_sertifikat', 'email', 'nim_nip'];

    /** Berapa baris berisi teratas yang diperiksa untuk mencari baris judul. */
    private const HEADER_SEARCH_ROWS = 10;

    /** Sama dengan batas nama pada absensi mandiri. */
    private const MAX_NAME_LENGTH = 150;

    private const MAX_TEXT_LENGTH = 255;

    /** Berapa letak baris yang dirinci dalam satu peringatan ringkas. */
    private const MAX_LOCATIONS_LISTED = 10;

    /** Nilai yang ditampilkan Excel ketika sebuah rumus gagal. */
    private const EXCEL_ERRORS = ['#NULL!', '#DIV/0!', '#VALUE!', '#REF!', '#NAME?', '#NUM!', '#N/A', '#SPILL!', '#CALC!', '#GETTING_DATA'];

    public function __construct(private readonly SpreadsheetReader $reader) {}

    public function parse(string $absolutePath): ParticipantImportPreview
    {
        $sheets = $this->reader->read($absolutePath);
        $tables = $this->locateTables($sheets);

        // Nama lembar baru disebut bila daftarnya lebih dari satu; tanpa itu
        // nomor baris saja sudah cukup menunjuk.
        $sebutLembar = count($tables) > 1;

        $this->guardRowCount($tables);

        $validRows = [];
        $problems = [];
        $warnings = [...$this->unknownHeaderWarnings($tables), ...$this->sheetWarnings($sheets, $tables)];
        $seen = ['emails' => [], 'numbers' => [], 'withoutEmail' => []];

        foreach ($tables as $table) {
            $sheet = $sebutLembar ? $table['title'] : null;
            $peranLembar = $this->roleFromSheetTitle($table['title']);
            $diisiDariLembar = 0;

            foreach ($table['rows'] as $line => $row) {
                $values = $this->mapRow($table['header'], $row);

                if ($this->isBlank($values)) {
                    continue;
                }

                $values['email'] = ParticipantRegistry::normaliseEmail($values['email']);
                $lokasi = ParticipantImportPreview::location($sheet, $line);

                $error = $this->validate($values, $seen);

                if ($error !== null) {
                    $problems[] = ['line' => $line, 'sheet' => $sheet, 'message' => $error];

                    continue;
                }

                $nimNip = $values['nim_nip'];

                // Digit setelah yang ke-15 sudah hilang di Excel, jadi nilai ini
                // pasti keliru. Barisnya tetap diimpor — NIM/NIP hanya untuk
                // pencatatan — tetapi nilainya tidak disimpan.
                if ($this->isTruncatedNumber($nimNip)) {
                    $warnings[] = "{$lokasi}: nim_nip terbaca {$nimNip} karena Excel menyimpannya sebagai angka "
                        .'dan memotong digit setelah ke-15. Nilainya dikosongkan; ubah kolomnya menjadi Teks lalu ketik ulang bila perlu.';
                    $nimNip = '';
                }

                $this->remember($values, lcfirst($lokasi), $seen);

                // Peran yang ditulis selalu menang. Yang kosong mengikuti nama
                // lembarnya: daftar di lembar "Panitia" tanpa kolom peran yang
                // terisi jelas bukan daftar peserta, dan peran itulah yang
                // tercetak di sertifikat.
                $role = ParticipantRole::fromLabel($values['peran']);

                if ($role === null) {
                    $role = $peranLembar ?? ParticipantRole::PARTICIPANT;
                    $diisiDariLembar += $role === ParticipantRole::PARTICIPANT ? 0 : 1;
                }

                $validRows[] = new ParticipantImportRow(
                    line: $line,
                    name: $values['nama_sertifikat'],
                    email: $values['email'] ?: null,
                    role: $role,
                    institutionalId: $nimNip ?: null,
                    studyProgram: $values['unit_prodi'] ?: null,
                    certificateNumber: $values['nomor_sertifikat'] ?: null,
                    sheet: $sheet,
                );
            }

            if ($diisiDariLembar > 0) {
                $warnings[] = "Lembar \"{$table['title']}\": {$diisiDariLembar} baris tanpa peran dicatat sebagai "
                    ."{$peranLembar->getLabel()}, sesuai nama lembarnya.";
            }
        }

        return new ParticipantImportPreview($validRows, $problems, [
            ...$warnings,
            ...$this->domainWarnings($validRows),
            ...$this->withoutEmailWarnings($validRows),
            ...$this->masterNameWarnings($validRows),
        ]);
    }

    /**
     * Cari setiap lembar yang berisi daftar, beserta baris judul kolomnya.
     *
     * Template meletakkan judul di baris pertama lembar pertama, tetapi
     * daftar hadir buatan panitia kerap diawali judul kegiatan, ada di lembar
     * kedua setelah lembar rekap, atau dipecah per lembar — Panitia di satu
     * lembar, Peserta di lembar lain. Semuanya dibaca. Menolak atau membaca
     * hanya sebagian membuat orang menyusun ulang berkas yang isinya benar,
     * atau lebih buruk, mengira seluruh daftarnya sudah masuk.
     *
     * @param  list<array{title: string, rows: array<int, array<int, string>>}>  $sheets
     * @return list<array{index: int, title: string, header: array<string, int>, labels: array<string, string>, rows: array<int, array<int, string>>}>
     *
     * @throws ParticipantImportException
     */
    private function locateTables(array $sheets): array
    {
        $tables = [];

        foreach ($sheets as $index => $sheet) {
            foreach (array_slice($sheet['rows'], 0, self::HEADER_SEARCH_ROWS, true) as $headerLine => $row) {
                [$header, $labels] = $this->headerMap($row);

                if (array_diff(self::REQUIRED_HEADERS, array_keys($header)) === []) {
                    $tables[] = [
                        'index' => $index,
                        'title' => $sheet['title'],
                        'header' => $header,
                        'labels' => $labels,
                        'rows' => array_filter($sheet['rows'], fn (int $line) => $line > $headerLine, ARRAY_FILTER_USE_KEY),
                    ];

                    break;
                }
            }
        }

        if ($tables !== []) {
            return $tables;
        }

        $pertama = collect($sheets)->first(fn (array $sheet) => $sheet['rows'] !== []);

        if ($pertama === null) {
            throw ParticipantImportException::emptyFile();
        }

        // Yang disebut hilang diukur dari baris berisi pertama di lembar
        // pertama: di sanalah orang paling mungkin menaruh judul kolom.
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
     * Peran yang tersirat dari nama lembar: "Panitia" atau "Panitia PKKMB"
     * berarti Panitia. Nama yang menyebut dua peran, seperti
     * "Panitia-Peserta", tidak menyiratkan apa pun.
     */
    private function roleFromSheetTitle(string $title): ?ParticipantRole
    {
        $persis = ParticipantRole::fromLabel($title);

        if ($persis !== null) {
            return $persis;
        }

        $judul = mb_strtolower($title);

        $cocok = array_values(array_filter(
            ParticipantRole::cases(),
            fn (ParticipantRole $role) => preg_match(
                '/\b'.preg_quote(mb_strtolower($role->getLabel()), '/').'\b/u',
                $judul,
            ) === 1,
        ));

        return count($cocok) === 1 ? $cocok[0] : null;
    }

    /**
     * @param  list<array{header: array<string, int>, rows: array<int, array<int, string>>}>  $tables
     */
    private function guardRowCount(array $tables): void
    {
        $jumlah = 0;

        foreach ($tables as $table) {
            foreach ($table['rows'] as $row) {
                $jumlah += $this->isBlank($this->mapRow($table['header'], $row)) ? 0 : 1;
            }
        }

        if ($jumlah > self::MAX_ROWS) {
            throw ParticipantImportException::tooManyRows($jumlah, self::MAX_ROWS);
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
     * Email boleh kosong. Peserta yang belum punya alamat tetap mendapat
     * sertifikat — terbit, bisa dicari di halaman daftar penerima — hanya
     * tidak dikirimi lewat email sampai alamatnya ditambahkan.
     *
     * @param  array<string, string>  $values
     * @param  array{emails: array<string, string>, numbers: array<string, string>, withoutEmail: array<string, string>}  $seen
     */
    private function validate(array $values, array $seen): ?string
    {
        $nama = $values['nama_sertifikat'];
        $email = $values['email'];

        if ($nama === '') {
            return 'Kolom nama_sertifikat wajib diisi.';
        }

        foreach (['nama_sertifikat' => $nama, 'email' => $email] as $kolom => $isi) {
            if ($isi !== '' && $this->isFormulaLeftover($isi)) {
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

        $masalahEmail = $email === ''
            ? $this->withoutEmailProblem($values, $seen)
            : $this->emailProblem($email, $seen);

        if ($masalahEmail !== null) {
            return $masalahEmail;
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

        $masalahNomor = $this->certificateNumberProblem($values['nomor_sertifikat'], $email, $nama, $seen['numbers']);

        if ($masalahNomor !== null) {
            return $masalahNomor;
        }

        if (ParticipantImportTemplate::isExample($nama, $email, $values['nim_nip'], $values['unit_prodi'])) {
            return 'Baris contoh dari template. Hapus baris ini sebelum mengunggah.';
        }

        return null;
    }

    /**
     * Alamat pribadi seperti Gmail diterima sama seperti alamat kampus: yang
     * diperiksa hanya bentuknya, bukan domainnya.
     *
     * @param  array{emails: array<string, string>}  $seen
     */
    private function emailProblem(string $email, array $seen): ?string
    {
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

        if (isset($seen['emails'][$email])) {
            return "Email {$email} duplikat dengan {$seen['emails'][$email]} pada file yang sama.";
        }

        return null;
    }

    /**
     * Tanpa email, orang yang sama dikenali dari nama dan NIM/NIP-nya —
     * aturan yang sama dipakai saat menulis (ParticipantRegistry::
     * findOrCreateWithoutEmail), jadi baris kembar di sini akan menjadi satu
     * orang di sana.
     *
     * @param  array<string, string>  $values
     * @param  array{withoutEmail: array<string, string>}  $seen
     */
    private function withoutEmailProblem(array $values, array $seen): ?string
    {
        $kunci = $this->withoutEmailKey($values);

        if (! isset($seen['withoutEmail'][$kunci])) {
            return null;
        }

        return "{$values['nama_sertifikat']} tanpa email sudah tercantum di {$seen['withoutEmail'][$kunci]}. "
            .'Bila keduanya memang orang berbeda, isi email atau NIM/NIP-nya.';
    }

    /**
     * @param  array<string, string>  $values
     */
    private function withoutEmailKey(array $values): string
    {
        return mb_strtolower($values['nama_sertifikat']).'|'.$values['nim_nip'];
    }

    /**
     * @param  array<string, string>  $values
     * @param  array{emails: array<string, string>, numbers: array<string, string>, withoutEmail: array<string, string>}  $seen
     */
    private function remember(array $values, string $lokasi, array &$seen): void
    {
        if ($values['email'] !== '') {
            $seen['emails'][$values['email']] = $lokasi;
        } else {
            $seen['withoutEmail'][$this->withoutEmailKey($values)] = $lokasi;
        }

        if ($values['nomor_sertifikat'] !== '') {
            $seen['numbers'][$values['nomor_sertifikat']] = $lokasi;
        }
    }

    /**
     * Nomor yang ditetapkan manual harus tetap unik sampai sertifikatnya
     * terbit. Bentrokan yang lolos di sini baru ketahuan saat penerbitan,
     * ketika salah satu sertifikat gagal terbit tanpa admin tahu sebabnya.
     *
     * @param  array<string, string>  $seenNumbers
     */
    private function certificateNumberProblem(string $nomor, string $email, string $nama, array $seenNumbers): ?string
    {
        if ($nomor === '') {
            return null;
        }

        if ($this->isTruncatedNumber($nomor)) {
            return "Nomor sertifikat terbaca {$nomor} karena Excel menyimpannya sebagai angka. "
                .'Ubah kolom nomor_sertifikat menjadi Teks lalu ketik ulang nomornya.';
        }

        if (isset($seenNumbers[$nomor])) {
            return "Nomor sertifikat {$nomor} kembar dengan {$seenNumbers[$nomor]} pada file yang sama.";
        }

        if (Certificate::where('certificate_number', $nomor)->exists()) {
            return "Nomor sertifikat {$nomor} sudah dipakai.";
        }

        // Disiapkan untuk orang lain tetapi belum terbit. Milik orang yang
        // sama tidak dihitung, supaya mengimpor ulang berkas yang sama tidak
        // menolak barisnya sendiri.
        $dipesan = CertificateEventParticipant::query()
            ->where('certificate_number', $nomor)
            ->whereDoesntHave('participant', fn (Builder $query) => $email !== ''
                ? $query->where('email', $email)
                : $query->whereNull('email')->where('name', $nama))
            ->exists();

        return $dipesan ? "Nomor sertifikat {$nomor} sudah disiapkan untuk peserta lain." : null;
    }

    /**
     * Domain yang hampir pasti salah ketik. Diperingatkan, bukan ditolak:
     * pesertanya tetap masuk, tetapi admin perlu tahu emailnya tidak akan
     * sampai sebelum seratus sertifikat dikirim ke alamat yang keliru.
     *
     * @param  array<int, ParticipantImportRow>  $rows
     * @return array<int, string>
     */
    private function domainWarnings(array $rows): array
    {
        $warnings = [];

        foreach ($rows as $row) {
            $saran = ParticipantRegistry::suggestDomain($row->email);

            if ($saran !== null) {
                $domain = ParticipantRegistry::domainOf($row->email);

                $warnings[] = "{$row->location()}: domain {$domain} mirip {$saran}, kemungkinan salah ketik "
                    ."({$row->email}). Email ke alamat ini hampir pasti tidak sampai; betulkan di berkas sebelum "
                    .'mengimpor, atau lewat tombol Koreksi Data Penerima sesudahnya.';
            }
        }

        return $warnings;
    }

    /**
     * Satu peringatan ringkas, bukan satu per baris: daftar PKKMB bisa
     * memuat puluhan mahasiswa baru yang belum punya email kampus.
     *
     * @param  array<int, ParticipantImportRow>  $rows
     * @return array<int, string>
     */
    private function withoutEmailWarnings(array $rows): array
    {
        $tanpaEmail = array_values(array_filter($rows, fn (ParticipantImportRow $row) => $row->email === null));

        if ($tanpaEmail === []) {
            return [];
        }

        $letak = array_map(
            fn (ParticipantImportRow $row) => lcfirst($row->location()),
            array_slice($tanpaEmail, 0, self::MAX_LOCATIONS_LISTED),
        );

        $sisa = count($tanpaEmail) - count($letak);

        return [count($tanpaEmail).' orang tanpa email ('.implode(', ', $letak).($sisa > 0 ? ", dan {$sisa} lainnya" : '').'). '
            .'Sertifikatnya tetap terbit dan bisa dicari di halaman daftar penerima, tetapi tidak dikirim lewat email. '
            .'Email bisa ditambahkan kapan saja lewat tombol Koreksi Data Penerima.'];
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
        $beremail = array_filter($rows, fn (ParticipantImportRow $row) => $row->email !== null);

        if ($beremail === []) {
            return [];
        }

        $master = Participant::query()
            ->whereIn('email', array_map(fn (ParticipantImportRow $row) => $row->email, $beremail))
            ->pluck('name', 'email');

        $warnings = [];

        foreach ($beremail as $row) {
            $nama = $master[$row->email] ?? null;

            if ($nama !== null && $nama !== $row->name) {
                $warnings[] = "{$row->location()}: {$row->email} sudah terdaftar atas nama \"{$nama}\". "
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
     * Baris tanpa nama, email, maupun NIM/NIP tidak memuat siapa pun.
     *
     * @param  array<string, string>  $values
     */
    private function isBlank(array $values): bool
    {
        foreach (self::IDENTITY_COLUMNS as $kolom) {
            if ($values[$kolom] !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{header: array<string, int>, labels: array<string, string>}>  $tables
     * @return array<int, string>
     */
    private function unknownHeaderWarnings(array $tables): array
    {
        $unknown = [];

        foreach ($tables as $table) {
            foreach (array_diff(array_keys($table['header']), self::KNOWN_HEADERS) as $key) {
                $unknown[$key] = $table['labels'][$key];
            }
        }

        return $unknown === []
            ? []
            : ['Kolom berikut tidak dikenal dan diabaikan: '.implode(', ', $unknown).'.'];
    }

    /**
     * Konfirmasi lembar mana yang dibaca, bila tidak jelas dengan sendirinya:
     * datanya bukan di lembar pertama, atau tersebar di beberapa lembar.
     *
     * @param  list<array{title: string}>  $sheets
     * @param  list<array{index: int, title: string}>  $tables
     * @return array<int, string>
     */
    private function sheetWarnings(array $sheets, array $tables): array
    {
        if (count($tables) === 1 && $tables[0]['index'] === 0) {
            return [];
        }

        $judul = array_map(fn (array $table) => '"'.$table['title'].'"', $tables);
        $terakhir = array_pop($judul);

        return ['Data dibaca dari lembar '.($judul === [] ? $terakhir : implode(', ', $judul).' dan '.$terakhir).'.'];
    }
}
