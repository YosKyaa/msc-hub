<?php

namespace Tests\Feature;

use App\Enums\ParticipantRole;
use App\Enums\ParticipantSource;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Services\Certificates\Import\ParticipantImportException;
use App\Services\Certificates\Import\ParticipantImportParser;
use App\Services\Certificates\Import\ParticipantImportSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ParticipantImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['nama_sertifikat', 'email', 'peran', 'nim_nip', 'unit_prodi', 'nomor_sertifikat'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ParticipantImportSession::TEMP_DISK);
    }

    /**
     * Tulis file .xlsx ke disk sementara dan kembalikan path relatifnya.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function storeSpreadsheet(array $rows, string $name = 'peserta.xlsx'): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = ParticipantImportSession::TEMP_DIRECTORY.'/'.$name;
        Storage::disk(ParticipantImportSession::TEMP_DISK)->put($path, '');

        (new Xlsx($spreadsheet))->save(Storage::disk(ParticipantImportSession::TEMP_DISK)->path($path));

        return $path;
    }

    private function importSession(): ParticipantImportSession
    {
        return app(ParticipantImportSession::class);
    }

    private function event(): CertificateEvent
    {
        return CertificateEvent::factory()->create();
    }

    public function test_the_official_template_rows_are_imported_correctly(): void
    {
        $event = $this->event();
        $path = $this->storeSpreadsheet([
            self::HEADER,
            ['Budi Santoso', 'budi@student.jgu.ac.id', 'Peserta', '2021001', 'Informatika', ''],
            ['Siti Aminah', 'siti@jgu.ac.id', 'Pembicara', '1987001', 'Ilmu Komunikasi', 'CERT-KHUSUS-01'],
            ['John External', 'john@example.com', 'Moderator', '', '', ''],
        ]);

        $result = $this->importSession()->confirm($event, $path);

        $this->assertSame(3, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(3, $event->participations()->count());

        $speaker = $event->participations()->whereRelation('participant', 'email', 'siti@jgu.ac.id')->sole();
        $this->assertSame(ParticipantRole::SPEAKER->value, $speaker->role);
        $this->assertSame('CERT-KHUSUS-01', $speaker->certificate_number);
        $this->assertSame(ParticipantSource::IMPORT, $speaker->source);
        $this->assertSame('1987001', $speaker->participant->institutional_id);
        $this->assertSame('Ilmu Komunikasi', $speaker->participant->study_program);

        // Email di luar JGU tetap boleh lewat jalur import dan dianggap tamu.
        $this->assertSame('guest', Participant::where('email', 'john@example.com')->value('type'));
        $this->assertSame('lecturer', Participant::where('email', 'siti@jgu.ac.id')->value('type'));
    }

    public function test_a_row_without_email_is_reported_while_the_others_still_import(): void
    {
        $event = $this->event();
        $path = $this->storeSpreadsheet([
            self::HEADER,
            ['Budi Santoso', 'budi@student.jgu.ac.id', 'Peserta', '', '', ''],
            ['Tanpa Email', '', 'Peserta', '', '', ''],
            ['Siti Aminah', 'siti@jgu.ac.id', 'Peserta', '', '', ''],
        ]);

        $preview = $this->importSession()->preview($path);

        $this->assertSame(2, $preview->validCount());
        $this->assertSame(1, $preview->problemCount());
        $this->assertSame(3, $preview->problems[0]['line']);
        $this->assertStringContainsString('email wajib diisi', $preview->problems[0]['message']);

        $this->assertSame(2, $this->importSession()->confirm($event, $path)->created);
    }

    public function test_importing_the_same_file_twice_creates_nothing_the_second_time(): void
    {
        $event = $this->event();
        $rows = [
            self::HEADER,
            ['Budi Santoso', 'budi@student.jgu.ac.id', 'Peserta', '', '', ''],
            ['Siti Aminah', 'siti@jgu.ac.id', 'Peserta', '', '', ''],
        ];

        $this->assertSame(2, $this->importSession()->confirm($event, $this->storeSpreadsheet($rows, 'satu.xlsx'))->created);

        $second = $this->importSession()->confirm($event, $this->storeSpreadsheet($rows, 'dua.xlsx'));

        $this->assertSame(0, $second->created);
        $this->assertSame(2, $second->skipped);
        $this->assertSame(2, CertificateEventParticipant::count());
        $this->assertSame(2, Participant::count());
    }

    public function test_email_case_and_whitespace_are_normalised(): void
    {
        $event = $this->event();
        Participant::factory()->create(['email' => 'budi@student.jgu.ac.id', 'name' => 'Budi Santoso, S.Kom.']);

        $path = $this->storeSpreadsheet([
            self::HEADER,
            ['  Budi Dari File  ', '  BUDI@Student.JGU.ac.id ', 'Peserta', '', '', ''],
        ]);

        $result = $this->importSession()->confirm($event, $path);

        $this->assertSame(1, $result->created);
        $this->assertSame(1, Participant::count());
        // Nama master tidak ditimpa, dan admin diberi tahu lewat peringatan.
        $this->assertSame('Budi Santoso, S.Kom.', Participant::sole()->name);
        $this->assertStringContainsString('memakai nama master', $result->warnings[0]);
    }

    public function test_columns_are_matched_by_name_regardless_of_order(): void
    {
        $event = $this->event();
        $path = $this->storeSpreadsheet([
            ['nomor_sertifikat', 'peran', 'EMAIL', 'unit_prodi', ' nama_sertifikat ', 'catatan_lain'],
            ['CERT-XYZ', 'Panitia', 'rina@jgu.ac.id', 'Humas', 'Rina Wijaya', 'diabaikan'],
        ]);

        $preview = $this->importSession()->preview($path);

        $this->assertSame(1, $preview->validCount());
        $row = $preview->validRows[0];
        $this->assertSame('Rina Wijaya', $row->name);
        $this->assertSame('rina@jgu.ac.id', $row->email);
        $this->assertSame(ParticipantRole::COMMITTEE, $row->role);
        $this->assertSame('CERT-XYZ', $row->certificateNumber);
        $this->assertStringContainsString('catatan_lain', $preview->warnings[0]);

        $this->assertSame(1, $this->importSession()->confirm($event, $path)->created);
    }

    public function test_a_file_with_more_than_the_row_limit_is_rejected(): void
    {
        $rows = [self::HEADER];

        for ($i = 1; $i <= ParticipantImportParser::MAX_ROWS + 1; $i++) {
            $rows[] = ["Peserta {$i}", "peserta{$i}@student.jgu.ac.id", 'Peserta', '', '', ''];
        }

        $path = $this->storeSpreadsheet($rows, 'besar.xlsx');

        $this->expectException(ParticipantImportException::class);
        $this->expectExceptionMessage('melebihi batas 500 baris');

        $this->importSession()->preview($path);
    }

    public function test_a_file_without_the_required_headers_is_rejected(): void
    {
        $path = $this->storeSpreadsheet([
            ['nama', 'surel'],
            ['Budi', 'budi@student.jgu.ac.id'],
        ]);

        $this->expectException(ParticipantImportException::class);
        $this->expectExceptionMessage('nama_sertifikat, email');

        $this->importSession()->preview($path);
    }

    public function test_duplicate_email_inside_the_file_is_reported_on_the_second_row(): void
    {
        $path = $this->storeSpreadsheet([
            self::HEADER,
            ['Budi Santoso', 'budi@student.jgu.ac.id', 'Peserta', '', '', ''],
            ['Budi Lagi', 'BUDI@student.jgu.ac.id', 'Peserta', '', '', ''],
        ]);

        $preview = $this->importSession()->preview($path);

        $this->assertSame(1, $preview->validCount());
        $this->assertSame(3, $preview->problems[0]['line']);
        $this->assertStringContainsString('duplikat dengan baris 2', $preview->problems[0]['message']);
    }

    public function test_unknown_role_and_taken_certificate_number_are_reported(): void
    {
        Certificate::factory()->create(['certificate_number' => 'CERT-SUDAH-ADA']);

        $path = $this->storeSpreadsheet([
            self::HEADER,
            ['Budi Santoso', 'budi@student.jgu.ac.id', 'Bintang Tamu', '', '', ''],
            ['Siti Aminah', 'siti@jgu.ac.id', 'Peserta', '', '', 'CERT-SUDAH-ADA'],
            ['Rina Wijaya', 'bukan-email', 'Peserta', '', '', ''],
        ]);

        $preview = $this->importSession()->preview($path);

        $this->assertSame(0, $preview->validCount());
        $this->assertStringContainsString('Peran "Bintang Tamu" tidak dikenal', $preview->problems[0]['message']);
        $this->assertStringContainsString('sudah dipakai', $preview->problems[1]['message']);
        $this->assertStringContainsString('Format email tidak valid', $preview->problems[2]['message']);
    }

    public function test_blank_rows_are_skipped_silently(): void
    {
        $path = $this->storeSpreadsheet([
            self::HEADER,
            ['', '', '', '', '', ''],
            ['Budi Santoso', 'budi@student.jgu.ac.id', '', '', '', ''],
        ]);

        $preview = $this->importSession()->preview($path);

        $this->assertSame(1, $preview->validCount());
        $this->assertSame(0, $preview->problemCount());
        // Peran default ketika kolom dikosongkan.
        $this->assertSame(ParticipantRole::PARTICIPANT, $preview->validRows[0]->role);
    }

    public function test_the_temporary_file_and_its_cache_are_removed_after_import(): void
    {
        $event = $this->event();
        $path = $this->storeSpreadsheet([
            self::HEADER,
            ['Budi Santoso', 'budi@student.jgu.ac.id', 'Peserta', '', '', ''],
        ]);

        $this->importSession()->preview($path);
        $this->importSession()->confirm($event, $path);

        Storage::disk(ParticipantImportSession::TEMP_DISK)->assertMissing($path);
        $this->assertFalse($this->importSession()->fileExists($path));
    }

    public function test_csv_files_are_accepted_as_well(): void
    {
        $event = $this->event();
        $path = ParticipantImportSession::TEMP_DIRECTORY.'/peserta.csv';
        Storage::disk(ParticipantImportSession::TEMP_DISK)->put(
            $path,
            "nama_sertifikat,email,peran\nBudi Santoso,budi@student.jgu.ac.id,Peserta\n",
        );

        $this->assertSame(1, $this->importSession()->confirm($event, $path)->created);
    }
}
