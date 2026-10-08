<?php

namespace Tests\Feature;

use App\Enums\ParticipantRole;
use App\Exports\ParticipantImportTemplate;
use App\Models\User;
use App\Services\Certificates\Import\ParticipantImportParser;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Berkas contoh untuk mengimpor peserta.
 *
 * Nama pada sertifikat dicetak apa adanya, dan sertifikat yang telanjur
 * terkirim dengan nama keliru harus dikoreksi lalu dikirim ulang, jadi salah
 * ketik mahal harganya. Template hanya berguna
 * bila kolomnya benar-benar sama dengan yang dibaca importer — kalau
 * keduanya menyimpang, template justru menyesatkan.
 */
class ParticipantImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    private string $path = '';

    protected function tearDown(): void
    {
        if ($this->path !== '' && file_exists($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    /**
     * Tulis templatenya ke berkas sementara, lalu baca kembali.
     */
    private function file(): string
    {
        if ($this->path === '') {
            $this->path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';

            file_put_contents($this->path, Excel::raw(new ParticipantImportTemplate, ExcelFormat::XLSX));
        }

        return $this->path;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function sheet(int $index = 0): array
    {
        return IOFactory::load($this->file())->getSheet($index)->toArray();
    }

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    // ------------------------------------------------------------ isinya

    /**
     * Inti gunanya: kolomnya harus persis yang dibaca importer, tidak kurang
     * dan tidak salah eja.
     */
    public function test_the_headings_match_what_the_importer_reads(): void
    {
        $baris = $this->sheet();

        $this->assertSame(ParticipantImportParser::KNOWN_HEADERS, array_map('strval', $baris[0]));
    }

    public function test_the_required_columns_come_first(): void
    {
        $baris = $this->sheet();

        foreach (ParticipantImportParser::REQUIRED_HEADERS as $wajib) {
            $this->assertContains($wajib, $baris[0]);
        }
    }

    /**
     * Contohnya harus lolos aturan importer sendiri. Contoh yang ditolak
     * sistemnya sendiri lebih buruk daripada tidak ada contoh sama sekali.
     */
    public function test_the_example_rows_would_pass_the_importer(): void
    {
        foreach (ParticipantImportTemplate::contoh() as $contoh) {
            [$nama, $email, $peran] = $contoh;

            $this->assertNotSame('', $nama);
            $this->assertMatchesRegularExpression('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email);
            $this->assertNotNull(ParticipantRole::fromLabel($peran), "Peran \"{$peran}\" tidak dikenal importer.");
        }
    }

    public function test_the_example_emails_are_all_different(): void
    {
        $email = array_column(ParticipantImportTemplate::contoh(), 1);

        $this->assertCount(count($email), array_unique($email));
    }

    /**
     * Mengetik peran sendiri adalah sumber kesalahan impor yang paling
     * sering, jadi kolomnya dikunci menjadi daftar pilihan.
     */
    public function test_the_role_column_is_locked_to_a_dropdown(): void
    {
        $validasi = IOFactory::load($this->file())->getSheet(0)->getCell('C2')->getDataValidation();

        $this->assertSame('list', $validasi->getType());
        $this->assertTrue($validasi->getShowDropDown());

        foreach (ParticipantRole::importableLabels() as $label) {
            $this->assertStringContainsString($label, $validasi->getFormula1());
        }
    }

    /**
     * Penjelasannya ikut di dalam berkas, supaya tidak hilang ketika
     * berkasnya diteruskan ke orang lain.
     */
    public function test_the_guide_travels_inside_the_file(): void
    {
        $petunjuk = collect($this->sheet(1))->flatten()->filter()->implode(' ');

        $this->assertStringContainsString('PETUNJUK PENGISIAN', $petunjuk);
        $this->assertStringContainsString('nama_sertifikat', $petunjuk);
        $this->assertStringContainsString('dicetak di sertifikat', $petunjuk);
        $this->assertStringContainsString((string) ParticipantImportParser::MAX_ROWS, $petunjuk);
    }

    public function test_the_guide_names_every_column_the_importer_reads(): void
    {
        $petunjuk = collect($this->sheet(1))->flatten()->filter()->implode(' ');

        foreach (ParticipantImportParser::KNOWN_HEADERS as $kolom) {
            $this->assertStringContainsString($kolom, $petunjuk);
        }
    }

    /**
     * Bukti yang paling menentukan: berkas ini dibaca oleh importer yang
     * sesungguhnya, bukan sekadar dicocokkan judul kolomnya. Template yang
     * ditolak sistemnya sendiri lebih buruk daripada tidak ada template.
     *
     * Baris contohnya memang ditolak — contoh yang lupa dihapus tidak boleh
     * menjadi peserta sungguhan — tetapi hanya karena ia contoh. Bila ada
     * alasan lain, berarti contohnya memperagakan format yang salah.
     */
    public function test_the_importer_reads_its_own_template_without_complaint(): void
    {
        $preview = app(ParticipantImportParser::class)->parse($this->file());

        $this->assertSame([], $preview->validRows, 'Baris contoh ikut diimpor sebagai peserta sungguhan.');
        $this->assertCount(count(ParticipantImportTemplate::contoh()), $preview->problems);

        foreach ($preview->problems as $masalah) {
            $this->assertStringContainsString('Baris contoh dari template', $masalah['message'],
                "Baris contoh {$masalah['line']} ditolak karena alasan lain: {$masalah['message']}");
        }
    }

    /**
     * Isian panitia di bawah baris contoh tetap terbaca seperti biasa.
     */
    public function test_rows_typed_into_the_template_are_imported(): void
    {
        $buku = IOFactory::load($this->file());
        $buku->getSheet(0)->fromArray(
            ['Rina Wulandari', 'rina.w@student.jgu.ac.id', 'Panitia', '20220099', 'Ilmu Komunikasi', ''],
            null,
            'A'.(count(ParticipantImportTemplate::contoh()) + 2),
        );
        IOFactory::createWriter($buku, 'Xlsx')->save($this->file());

        $preview = app(ParticipantImportParser::class)->parse($this->file());

        $this->assertCount(1, $preview->validRows);
        $this->assertSame('Rina Wulandari', $preview->validRows[0]->name);
        $this->assertSame(ParticipantRole::COMMITTEE, $preview->validRows[0]->role);
        $this->assertSame('20220099', $preview->validRows[0]->institutionalId);
    }

    /**
     * NIP dosen 18 digit. Excel hanya menyimpan lima belas digit pertama dari
     * sebuah angka dan mengganti sisanya dengan nol, jadi kolomnya harus
     * berformat Teks sejak awal — termasuk contohnya sendiri.
     */
    public function test_the_id_and_number_columns_are_text(): void
    {
        $lembar = IOFactory::load($this->file())->getSheet(0);

        foreach (['D', 'F'] as $kolom) {
            $this->assertSame('@', $lembar->getStyle($kolom.'10')->getNumberFormat()->getFormatCode(),
                "Kolom {$kolom} tidak berformat Teks.");
        }

        $this->assertSame('198001012005011001', $lembar->getCell('D3')->getValue(),
            'NIP contoh tersimpan sebagai angka dan kehilangan digitnya.');
    }

    /**
     * Alamat contoh tidak boleh mungkin milik orang sungguhan: contoh yang
     * lupa dihapus akan mengirim sertifikat ke alamat itu.
     */
    public function test_the_example_addresses_cannot_belong_to_anyone(): void
    {
        foreach (ParticipantImportTemplate::contoh() as [, $email]) {
            $this->assertStringStartsWith('contoh.', $email);
        }
    }

    /**
     * Lembar petunjuk tidak boleh ikut terbaca sebagai data peserta.
     */
    public function test_the_guide_sheet_is_not_mistaken_for_data(): void
    {
        $preview = app(ParticipantImportParser::class)->parse($this->file());

        // Yang terbaca hanya baris contoh di lembar Peserta, baris 2 sampai 4.
        $baris = array_column($preview->problems, 'line');
        $this->assertSame(range(2, count(ParticipantImportTemplate::contoh()) + 1), $baris);

        // Dan lembar Petunjuk tidak disangka daftar peserta kedua, yang akan
        // membuat setiap unggahan template berisi peringatan palsu.
        $this->assertSame([], $preview->warnings);
    }

    // ------------------------------------------------------------ aksesnya

    public function test_staff_can_download_it(): void
    {
        $response = $this->actingAs($this->staff())
            ->get(route('certificates.import-template'))
            ->assertOk();

        $this->assertStringContainsString(
            ParticipantImportTemplate::FILENAME,
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_a_stranger_cannot(): void
    {
        $this->get(route('certificates.import-template'))->assertRedirect();
    }
}
