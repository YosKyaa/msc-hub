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
 * Nama pada sertifikat dicetak apa adanya dan tidak bisa ditarik kembali
 * setelah terbit, jadi salah ketik mahal harganya. Template hanya berguna
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
     */
    public function test_the_importer_reads_its_own_template_without_complaint(): void
    {
        $preview = app(ParticipantImportParser::class)->parse($this->file());

        $this->assertSame([], $preview->problems, 'Baris contoh ditolak importer.');
        $this->assertCount(count(ParticipantImportTemplate::contoh()), $preview->validRows);

        $pertama = $preview->validRows[0];
        $this->assertSame('Budi Santoso', $pertama->name);
        $this->assertSame('budi@student.jgu.ac.id', $pertama->email);
        $this->assertSame(ParticipantRole::PARTICIPANT, $pertama->role);
    }

    /**
     * Lembar petunjuk tidak boleh ikut terbaca sebagai data peserta.
     */
    public function test_the_guide_sheet_is_not_mistaken_for_data(): void
    {
        $preview = app(ParticipantImportParser::class)->parse($this->file());

        foreach ($preview->validRows as $row) {
            $this->assertStringNotContainsString('PETUNJUK', $row->name);
        }
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
