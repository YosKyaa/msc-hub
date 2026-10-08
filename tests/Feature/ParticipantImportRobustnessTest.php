<?php

namespace Tests\Feature;

use App\Exports\ParticipantImportTemplate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Services\Certificates\Import\ParticipantImportException;
use App\Services\Certificates\Import\ParticipantImportParser;
use App\Services\Certificates\Import\ParticipantImportPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Berkas peserta sebagaimana sungguh dikirim panitia.
 *
 * Jarang ada yang persis seperti template. Judul kolom diketik ulang dengan
 * huruf besar, daftar hadir diawali judul kegiatan, nama disusun dengan
 * rumus, alamat disalin dari WhatsApp lengkap dengan spasi tak terlihatnya,
 * dan NIP 18 digit diketik di kolom angka. Setiap kasus di bawah pernah
 * membuat impor menolak berkas yang isinya benar, atau lebih buruk, menerima
 * data yang salah tanpa suara.
 */
class ParticipantImportRobustnessTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['nama_sertifikat', 'email', 'peran', 'nim_nip', 'unit_prodi', 'nomor_sertifikat'];

    /** @var list<string> */
    private array $berkas = [];

    protected function tearDown(): void
    {
        foreach ($this->berkas as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function workbook(array $rows = [], ?callable $ubah = null, string $format = 'Xlsx'): string
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->setTitle('Lembar1')->fromArray($rows, null, 'A1', true);

        if ($ubah !== null) {
            $ubah($buku);
        }

        $path = tempnam(sys_get_temp_dir(), 'impor').'.'.strtolower($format);
        IOFactory::createWriter($buku, $format)->save($path);

        return $this->berkas[] = $path;
    }

    private function csv(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'impor').'.csv';
        file_put_contents($path, $isi);

        return $this->berkas[] = $path;
    }

    private function parse(string $path): ParticipantImportPreview
    {
        return app(ParticipantImportParser::class)->parse($path);
    }

    /**
     * @return array<int, string>
     */
    private function messages(ParticipantImportPreview $preview): array
    {
        return array_column($preview->problems, 'message');
    }

    // ------------------------------------------------------- letak judul

    /**
     * Mengetik ulang judul kolom dengan huruf besar dan spasi itu wajar,
     * bukan kesalahan.
     */
    public function test_headers_typed_in_plain_words_are_understood(): void
    {
        $preview = $this->parse($this->workbook([
            ['Nama Sertifikat', 'E-mail', 'Peran', 'NIM/NIP', 'Unit / Prodi', 'Nomor Sertifikat'],
            ['Budi Hartono', 'budi.h@student.jgu.ac.id', 'Panitia', '20210001', 'Informatika', 'MSC/77'],
        ]));

        $this->assertSame([], $preview->problems);
        $baris = $preview->validRows[0];

        $this->assertSame('Budi Hartono', $baris->name);
        $this->assertSame('budi.h@student.jgu.ac.id', $baris->email);
        $this->assertSame('20210001', $baris->institutionalId);
        $this->assertSame('Informatika', $baris->studyProgram);
        $this->assertSame('MSC/77', $baris->certificateNumber);
    }

    /**
     * "nama" saja tidak menjelaskan apakah itu nama yang dicetak, jadi tidak
     * ditebak-tebak.
     */
    public function test_an_ambiguous_name_header_is_still_refused(): void
    {
        $this->expectException(ParticipantImportException::class);
        $this->expectExceptionMessage('nama_sertifikat');

        $this->parse($this->workbook([['Nama', 'Email'], ['Budi', 'budi.h@student.jgu.ac.id']]));
    }

    /**
     * Daftar hadir kerap diawali judul kegiatan. Nomor baris yang dilaporkan
     * tetap nomor baris di Excel, supaya admin bisa langsung menemukannya.
     */
    public function test_a_title_above_the_headers_is_skipped(): void
    {
        $preview = $this->parse($this->workbook([
            ['DAFTAR PESERTA SEMINAR KEPEMIMPINAN'],
            ['Selasa, 6 Oktober 2026'],
            [],
            ['nama_sertifikat', 'email'],
            ['Budi Hartono', 'budi.h@student.jgu.ac.id'],
            ['Rina Wulandari', 'bukan-email'],
        ]));

        $this->assertSame(1, $preview->validCount());
        $this->assertSame(5, $preview->validRows[0]->line);
        $this->assertSame(6, $preview->problems[0]['line']);
    }

    public function test_the_data_may_sit_on_another_sheet(): void
    {
        $preview = $this->parse($this->workbook([['Rekap'], ['Jumlah hadir', 1]], function (Spreadsheet $buku) {
            $buku->createSheet()->setTitle('Peserta')->fromArray([
                self::HEADER,
                ['Budi Hartono', 'budi.h@student.jgu.ac.id', '', '', '', ''],
            ]);
        }));

        $this->assertSame(1, $preview->validCount());
        $this->assertContains('Data dibaca dari lembar "Peserta".', $preview->warnings);
    }

    /**
     * Hanya satu lembar yang dibaca. Peserta di lembar lain akan hilang tanpa
     * jejak bila admin tidak diberi tahu.
     */
    public function test_a_second_list_of_participants_is_mentioned(): void
    {
        $preview = $this->parse($this->workbook([
            self::HEADER,
            ['Budi Hartono', 'budi.h@student.jgu.ac.id', '', '', '', ''],
        ], function (Spreadsheet $buku) {
            $buku->createSheet()->setTitle('Hari 2')->fromArray([
                self::HEADER,
                ['Rina Wulandari', 'rina.w@student.jgu.ac.id', '', '', '', ''],
            ]);
        }));

        $this->assertSame(1, $preview->validCount());
        $this->assertStringContainsString('Lembar "Hari 2" juga berisi daftar peserta', implode(' ', $preview->warnings));
    }

    // -------------------------------------------------------------- rumus

    /**
     * Nama yang disusun dengan rumus dulu terbaca sebagai teks rumusnya, dan
     * bisa tercetak begitu saja di sertifikat.
     */
    public function test_formula_cells_are_read_as_their_values(): void
    {
        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email', 'depan', 'alamat'],
            ['=PROPER(C2)', '=LOWER(D2)', 'budi hartono', 'BUDI.H@STUDENT.JGU.AC.ID'],
        ]));

        $this->assertSame([], $preview->problems);
        $this->assertSame('Budi Hartono', $preview->validRows[0]->name);
        $this->assertSame('budi.h@student.jgu.ac.id', $preview->validRows[0]->email);
    }

    /**
     * VLOOKUP yang tidak menemukan pasangannya menghasilkan #N/A, dan
     * #N/A mengandung huruf: tanpa penjagaan ini ia lolos sebagai nama.
     */
    public function test_a_failed_formula_is_refused_rather_than_printed(): void
    {
        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email'],
            ['=NA()', 'budi.h@student.jgu.ac.id'],
        ]));

        $this->assertSame(0, $preview->validCount());
        $this->assertStringContainsString('rumus yang tidak menghasilkan nilai', $this->messages($preview)[0]);
    }

    // -------------------------------------------------------- isi sel

    /**
     * Teks yang disalin dari web atau WhatsApp membawa spasi tak putus dan
     * karakter lebar-nol. Tidak terlihat, tetapi membuat alamat tidak
     * dikenali dan ikut tercetak.
     */
    public function test_invisible_characters_are_removed(): void
    {
        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email'],
            ["Budi\u{00A0}Hartono\u{200B}", "\u{00A0}Budi.H@Student.JGU.ac.id\u{FEFF}\u{00A0}"],
        ]));

        $this->assertSame('Budi Hartono', $preview->validRows[0]->name);
        $this->assertSame('budi.h@student.jgu.ac.id', $preview->validRows[0]->email);
    }

    /**
     * Aturan yang sama berlaku di luar impor: formulir tambah peserta dan
     * koreksi data penerima menyimpan lewat model yang sama.
     */
    public function test_the_model_stores_addresses_without_invisible_characters(): void
    {
        $peserta = Participant::factory()->create(['email' => "\u{00A0}Budi.H@Student.JGU.ac.id\u{200B}\u{00A0}"]);

        $this->assertSame('budi.h@student.jgu.ac.id', $peserta->fresh()->email);
    }

    public function test_line_breaks_and_double_spaces_in_a_name_are_collapsed(): void
    {
        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email'],
            ["Budi   Hartono,\nS.Kom.", 'budi.h@student.jgu.ac.id'],
        ]));

        $this->assertSame('Budi Hartono, S.Kom.', $preview->validRows[0]->name);
    }

    /**
     * Excel hanya menyimpan lima belas digit pertama sebuah angka. NIP
     * 18 digit yang diketik di kolom angka sudah rusak sebelum sampai sini.
     * Barisnya tetap diimpor — NIP hanya untuk pencatatan — tetapi nilainya
     * tidak disimpan, dan admin diberi tahu.
     */
    public function test_a_truncated_nip_is_dropped_with_a_warning(): void
    {
        $preview = $this->parse($this->workbook([self::HEADER], function (Spreadsheet $buku) {
            $lembar = $buku->getActiveSheet();
            $lembar->fromArray(['Dr. Djoko Susilo', 'djoko.s@jgu.ac.id'], null, 'A2');
            $lembar->setCellValueExplicit('D2', 198001012005011001.0, DataType::TYPE_NUMERIC);
        }));

        $this->assertSame(1, $preview->validCount());
        $this->assertNull($preview->validRows[0]->institutionalId);
        $this->assertStringContainsString('memotong digit setelah ke-15', implode(' ', $preview->warnings));
    }

    /**
     * Nomor sertifikat yang rusak lain soal: ia tercetak di dokumen.
     */
    public function test_a_truncated_certificate_number_is_refused(): void
    {
        $preview = $this->parse($this->workbook([self::HEADER], function (Spreadsheet $buku) {
            $lembar = $buku->getActiveSheet();
            $lembar->fromArray(['Budi Hartono', 'budi.h@student.jgu.ac.id'], null, 'A2');
            $lembar->setCellValueExplicit('F2', 202610060000000001.0, DataType::TYPE_NUMERIC);
        }));

        $this->assertSame(0, $preview->validCount());
        $this->assertStringContainsString('Ubah kolom nomor_sertifikat menjadi Teks', $this->messages($preview)[0]);
    }

    /**
     * NIM yang masih utuh tidak boleh berakhir dengan ".0".
     */
    public function test_a_numeric_nim_is_kept_whole(): void
    {
        $preview = $this->parse($this->workbook([self::HEADER], function (Spreadsheet $buku) {
            $lembar = $buku->getActiveSheet();
            $lembar->fromArray(['Budi Hartono', 'budi.h@student.jgu.ac.id'], null, 'A2');
            $lembar->setCellValueExplicit('D2', 20210001.0, DataType::TYPE_NUMERIC);
        }));

        $this->assertSame('20210001', $preview->validRows[0]->institutionalId);
    }

    // -------------------------------------------------------- baris contoh

    /**
     * Baris contoh yang lupa dihapus dulu ikut menjadi peserta, dan
     * sertifikatnya terkirim ke alamat contoh itu.
     */
    public function test_the_template_examples_are_refused(): void
    {
        $preview = $this->parse($this->workbook([
            self::HEADER,
            ...ParticipantImportTemplate::contoh(),
            ['Budi Hartono', 'budi.h@student.jgu.ac.id', '', '', '', ''],
        ]));

        $this->assertSame(1, $preview->validCount());
        $this->assertSame('Budi Hartono', $preview->validRows[0]->name);
        $this->assertCount(3, $preview->problems);
    }

    /**
     * Template lama masih tersimpan di komputer panitia, dan alamat
     * contohnya masuk akal dimiliki orang sungguhan.
     */
    public function test_the_old_template_examples_are_refused_too(): void
    {
        $preview = $this->parse($this->workbook([self::HEADER, ...ParticipantImportTemplate::contohLama()]));

        $this->assertSame(0, $preview->validCount());
        $this->assertCount(3, $preview->problems);
    }

    /**
     * Tetapi alamat itu mungkin memang milik seseorang. Selama barisnya tidak
     * sama persis dengan contoh, ia diperlakukan sebagai peserta biasa.
     */
    public function test_a_real_person_sharing_an_old_example_address_is_accepted(): void
    {
        $preview = $this->parse($this->workbook([
            self::HEADER,
            ['Siti Aminah', 'siti@jgu.ac.id', 'Pembicara', '', '', ''],
        ]));

        $this->assertSame(1, $preview->validCount());
        $this->assertSame([], $preview->warnings);
    }

    // ------------------------------------------------------ batas isian

    /**
     * Dulu lolos pratinjau, lalu menggagalkan seluruh impor di basis data.
     */
    public function test_an_overlong_name_is_refused_before_it_reaches_the_database(): void
    {
        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email'],
            [str_repeat('Budi ', 40), 'budi.h@student.jgu.ac.id'],
        ]));

        $this->assertSame(0, $preview->validCount());
        $this->assertStringContainsString('Nama terlalu panjang', $this->messages($preview)[0]);
    }

    /**
     * Kolom yang tertukar: NIM di kolom nama.
     */
    public function test_a_name_without_letters_is_refused(): void
    {
        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email'],
            ['20210001', 'budi.h@student.jgu.ac.id'],
        ]));

        $this->assertStringContainsString('tidak berisi huruf', $this->messages($preview)[0]);
    }

    /**
     * Aturan RFC menerima "budi@gmail", yang tidak akan pernah sampai.
     */
    public function test_an_address_without_a_full_domain_is_refused(): void
    {
        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email'],
            ['Budi Hartono', 'budi@gmail'],
        ]));

        $this->assertStringContainsString('Alamat email tidak lengkap', $this->messages($preview)[0]);
    }

    // --------------------------------------------------- nomor sertifikat

    /**
     * Nomor kembar dulu lolos, lalu salah satu sertifikat gagal terbit tanpa
     * admin tahu sebabnya.
     */
    public function test_a_certificate_number_used_twice_in_the_file_is_refused(): void
    {
        $preview = $this->parse($this->workbook([
            self::HEADER,
            ['Budi Hartono', 'budi.h@student.jgu.ac.id', '', '', '', 'MSC/001'],
            ['Rina Wulandari', 'rina.w@student.jgu.ac.id', '', '', '', 'MSC/001'],
        ]));

        $this->assertSame(1, $preview->validCount());
        $this->assertStringContainsString('kembar dengan baris 2', $this->messages($preview)[0]);
    }

    public function test_a_certificate_number_reserved_for_someone_else_is_refused(): void
    {
        CertificateEventParticipant::factory()->create([
            'participant_id' => Participant::factory()->create(['email' => 'orang.lain@jgu.ac.id']),
            'certificate_number' => 'MSC/002',
        ]);

        $preview = $this->parse($this->workbook([
            self::HEADER,
            ['Budi Hartono', 'budi.h@student.jgu.ac.id', '', '', '', 'MSC/002'],
        ]));

        $this->assertStringContainsString('sudah disiapkan untuk peserta lain', $this->messages($preview)[0]);
    }

    /**
     * Mengimpor ulang berkas yang sama tidak boleh menolak barisnya sendiri.
     */
    public function test_a_number_already_held_by_the_same_person_is_not_a_clash(): void
    {
        CertificateEventParticipant::factory()->create([
            'participant_id' => Participant::factory()->create(['email' => 'budi.h@student.jgu.ac.id']),
            'certificate_number' => 'MSC/003',
        ]);

        $preview = $this->parse($this->workbook([
            self::HEADER,
            ['Budi Hartono', 'budi.h@student.jgu.ac.id', '', '', '', 'MSC/003'],
        ]));

        $this->assertSame(1, $preview->validCount());
    }

    // ---------------------------------------------------------- nama master

    /**
     * Nama itulah yang akan tercetak, jadi disebut sejak pratinjau, bukan
     * baru diketahui sesudah impor.
     */
    public function test_a_different_master_name_is_announced_in_the_preview(): void
    {
        Participant::factory()->create(['email' => 'budi.h@student.jgu.ac.id', 'name' => 'Budi Hartono, S.Kom.']);

        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email'],
            ['Budi Hartono', 'budi.h@student.jgu.ac.id'],
        ]));

        $this->assertSame(1, $preview->validCount());
        $this->assertStringContainsString('sudah terdaftar atas nama "Budi Hartono, S.Kom."', implode(' ', $preview->warnings));
    }

    /**
     * Peringatan itu dulu dibuat importer sendiri; kini dari pratinjau.
     * Keduanya tidak boleh sama-sama muncul.
     */
    public function test_the_master_name_warning_appears_once_after_importing(): void
    {
        Participant::factory()->create(['email' => 'budi.h@student.jgu.ac.id', 'name' => 'Budi Hartono, S.Kom.']);

        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email'],
            ['Budi Hartono', 'budi.h@student.jgu.ac.id'],
        ]));

        $hasil = app(\App\Services\Certificates\Import\ParticipantImporter::class)
            ->import(CertificateEvent::factory()->create(), $preview);

        $this->assertCount(1, $hasil->warnings);
    }

    // ------------------------------------------------------------- format

    /**
     * Excel berlokal Indonesia menyimpan CSV dengan titik koma, karena koma
     * dipakai sebagai pemisah desimal.
     */
    public function test_a_semicolon_csv_is_read(): void
    {
        $preview = $this->parse($this->csv("nama_sertifikat;email;peran\r\nBudi Hartono;budi.h@student.jgu.ac.id;Panitia\r\n"));

        $this->assertSame(1, $preview->validCount());
        $this->assertSame('committee', $preview->validRows[0]->role->value);
    }

    /**
     * "CSV UTF-8" di Excel menulis BOM di depan judul kolom pertama.
     */
    public function test_a_csv_with_a_byte_order_mark_is_read(): void
    {
        $preview = $this->parse($this->csv("\xEF\xBB\xBFnama_sertifikat,email\r\nBudi Hartono,budi.h@student.jgu.ac.id\r\n"));

        $this->assertSame(1, $preview->validCount());
    }

    public function test_a_libreoffice_file_is_read(): void
    {
        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email'],
            ['Budi Hartono', 'budi.h@student.jgu.ac.id'],
        ], format: 'Ods'));

        $this->assertSame(1, $preview->validCount());
    }

    public function test_an_old_excel_file_is_read(): void
    {
        $preview = $this->parse($this->workbook([
            ['nama_sertifikat', 'email'],
            ['Budi Hartono', 'budi.h@student.jgu.ac.id'],
        ], format: 'Xls'));

        $this->assertSame(1, $preview->validCount());
    }

    /**
     * Alasan teknisnya berbahasa Inggris dan tidak bisa ditindaklanjuti
     * panitia; yang ditampilkan adalah apa yang bisa ia periksa sendiri.
     */
    public function test_an_unreadable_file_is_explained_in_plain_indonesian(): void
    {
        // Gambar yang keliru diunggah. Teks biasa tidak bisa dipakai di sini:
        // ia terbaca sebagai CSV dan ditolak karena judul kolomnya tidak ada.
        $path = tempnam(sys_get_temp_dir(), 'impor').'.xlsx';
        file_put_contents($path, "\x89PNG\r\n\x1a\n".str_repeat("\x00\xFF", 200));
        $this->berkas[] = $path;

        try {
            $this->parse($path);
            $this->fail('Berkas rusak tidak ditolak.');
        } catch (ParticipantImportException $exception) {
            $this->assertStringContainsString('tidak dapat dibaca sebagai lembar kerja', $exception->getMessage());
        }
    }

    public function test_an_empty_workbook_is_reported_as_empty(): void
    {
        $this->expectException(ParticipantImportException::class);
        $this->expectExceptionMessage('tidak berisi data');

        $this->parse($this->workbook([]));
    }
}
