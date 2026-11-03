<?php

namespace Tests\Feature;

use App\Enums\ParticipantRole;
use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\User;
use App\Services\CertificateRenderService;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\Import\ParticipantImporter;
use App\Services\Certificates\Import\ParticipantImportParser;
use App\Services\Certificates\Import\ParticipantImportPreview;
use App\Services\Certificates\Import\ParticipantImportRow;
use App\Services\Certificates\Import\ParticipantImportSession;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Jabatan di kolom peran.
 *
 * Kolom peran dulu hanya menerima sembilan peran baku, sehingga baris
 * berisi "Ketua Pelaksana" atau "Juara 1" ditolak seluruhnya. Sekarang teks
 * di luar daftar disimpan sebagai jabatan dan dicetak apa adanya, sedangkan
 * kategorinya disimpulkan: dari nama lembar, lalu dari kata peran di dalam
 * jabatannya, lalu dari Kategori default yang dipilih saat import.
 */
class ParticipantImportRoleLabelTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['nama_sertifikat', 'email', 'peran', 'nim_nip', 'unit_prodi', 'nomor_sertifikat'];

    /** @var list<string> */
    private array $berkas = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ParticipantImportSession::TEMP_DISK);
    }

    protected function tearDown(): void
    {
        foreach ($this->berkas as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /**
     * Satu lembar per daftar, dengan nama lembar yang dipilih sendiri.
     *
     * @param  array<string, array<int, array<int, string>>>  $lembar
     */
    private function workbook(array $lembar): string
    {
        $buku = new Spreadsheet;
        $buku->removeSheetByIndex(0);

        foreach ($lembar as $judul => $rows) {
            $sheet = $buku->createSheet()->setTitle($judul);

            foreach ($rows as $r => $row) {
                foreach (array_values($row) as $c => $isi) {
                    $sheet->setCellValueExplicit([$c + 1, $r + 1], $isi, DataType::TYPE_STRING);
                }
            }
        }

        $buku->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'jabatan').'.xlsx';
        IOFactory::createWriter($buku, 'Xlsx')->save($path);

        return $this->berkas[] = $path;
    }

    /**
     * @param  array<string, array<int, array<int, string>>>  $lembar
     */
    private function upload(array $lembar): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('peserta.xlsx', file_get_contents($this->workbook($lembar)));
    }

    /**
     * @param  array<string, array<int, array<int, string>>>  $lembar
     */
    private function parse(array $lembar): ParticipantImportPreview
    {
        return app(ParticipantImportParser::class)->parse($this->workbook($lembar));
    }

    private function row(ParticipantImportPreview $preview, string $nama): ParticipantImportRow
    {
        foreach ($preview->validRows as $row) {
            if ($row->name === $nama) {
                return $row;
            }
        }

        $this->fail("{$nama} tidak ada di antara baris yang valid.");
    }

    private function table(CertificateEvent $event): Testable
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Livewire::actingAs($admin->fresh())->test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ]);
    }

    /**
     * Isi langkah Pratinjau sebagaimana dibaca admin.
     */
    private function previewText(Testable $modal): string
    {
        return collect($modal->instance()->mountedActionSchema0->getFlatComponents())
            ->filter(fn ($komponen) => $komponen instanceof Text && $komponen->isVisible())
            ->map(fn (Text $komponen) => html_entity_decode(strip_tags((string) $komponen->getContent())))
            ->implode(PHP_EOL);
    }

    // ------------------------------------------------------- peran baku

    public function test_a_listed_role_is_matched_whatever_its_case(): void
    {
        $preview = $this->parse(['Sheet1' => [
            self::HEADER,
            ['Rina Wulandari', 'rina@jgu.ac.id', 'PANITIA', '', '', ''],
            ['Yoga Pratama', 'yoga@jgu.ac.id', 'pembicara', '', '', ''],
            ['Dewi Lestari', 'dewi@jgu.ac.id', '  Juri ', '', '', ''],
        ]]);

        $this->assertSame([], $preview->problems);
        $this->assertSame(ParticipantRole::COMMITTEE, $this->row($preview, 'Rina Wulandari')->role);
        $this->assertSame(ParticipantRole::SPEAKER, $this->row($preview, 'Yoga Pratama')->role);
        $this->assertSame(ParticipantRole::JUDGE, $this->row($preview, 'Dewi Lestari')->role);

        foreach ($preview->validRows as $row) {
            $this->assertNull($row->roleLabel, 'Peran baku ikut tersimpan sebagai jabatan.');
        }
    }

    // ---------------------------------------------------------- jabatan

    /**
     * Inti permintaannya: baris berjabatan tidak lagi ditolak.
     */
    public function test_an_unlisted_role_is_kept_as_the_title(): void
    {
        $preview = $this->parse(['Sheet1' => [
            self::HEADER,
            ['Rina Wulandari', 'rina@jgu.ac.id', 'Ketua Pelaksana', '', '', ''],
        ]]);

        $this->assertSame([], $preview->problems);

        $rina = $this->row($preview, 'Rina Wulandari');
        $this->assertSame('Ketua Pelaksana', $rina->roleLabel);
        $this->assertNull($rina->role, 'Kategori yang tidak tersirat harus menunggu Kategori default.');
    }

    /**
     * Jabatan panitia kerap memuat kata peran lain. Nama lembar didahulukan,
     * supaya "Koordinator Peserta" di lembar Panitia tetap panitia.
     */
    public function test_the_sheet_title_decides_the_category_first(): void
    {
        $preview = $this->parse([
            'Panitia' => [
                self::HEADER,
                ['Rina Wulandari', 'rina@jgu.ac.id', 'Koordinator Peserta', '', '', ''],
                ['Yoga Pratama', 'yoga@jgu.ac.id', 'LO Pembicara', '', '', ''],
            ],
            'Peserta' => [
                self::HEADER,
                ['Bagas Pratama', 'bagas@gmail.com', 'Peserta Lomba', '', '', ''],
            ],
        ]);

        foreach (['Rina Wulandari', 'Yoga Pratama'] as $nama) {
            $this->assertSame(ParticipantRole::COMMITTEE, $this->row($preview, $nama)->role);
            $this->assertSame(ParticipantImportRow::ROLE_FROM_SHEET, $this->row($preview, $nama)->roleSource);
        }

        $this->assertSame(ParticipantRole::PARTICIPANT, $this->row($preview, 'Bagas Pratama')->role);
        $this->assertSame('Koordinator Peserta', $this->row($preview, 'Rina Wulandari')->roleLabel);
    }

    /**
     * @return array<string, array{0: string, 1: ?ParticipantRole}>
     */
    public static function titles(): array
    {
        return [
            'peran di belakang' => ['Juri Tamu', ParticipantRole::JUDGE],
            'peran di depan' => ['Panitia Inti', ParticipantRole::COMMITTEE],
            'huruf kecil' => ['ketua panitia', ParticipantRole::COMMITTEE],
            // Dua peran sekaligus tidak menyiratkan satu pun.
            'dua peran' => ['Mentor & Juri', null],
            'tanpa peran' => ['Ketua Pelaksana', null],
            // Kata di dalam kata lain bukan peran.
            'bagian kata' => ['Koordinator Penjurian', null],
        ];
    }

    /**
     * Di lembar yang namanya tidak menyebut peran, kata peran di dalam
     * jabatannya yang menentukan.
     */
    #[DataProvider('titles')]
    public function test_a_role_named_in_the_title_comes_next(string $jabatan, ?ParticipantRole $harapan): void
    {
        $preview = $this->parse(['Sheet1' => [
            self::HEADER,
            ['Rina Wulandari', 'rina@jgu.ac.id', $jabatan, '', '', ''],
        ]]);

        $rina = $this->row($preview, 'Rina Wulandari');

        $this->assertSame($jabatan, $rina->roleLabel);
        $this->assertSame($harapan, $rina->role);
        $this->assertSame($harapan === null ? null : ParticipantImportRow::ROLE_FROM_LABEL, $rina->roleSource);
    }

    /**
     * Sel kosong tetap mengikuti aturan lama: nama lembar, lalu Peserta. Kategori
     * default hanya untuk jabatan.
     */
    public function test_an_empty_role_still_ignores_the_default_category(): void
    {
        $event = CertificateEvent::factory()->create();

        $preview = $this->parse(['Sheet1' => [
            self::HEADER,
            ['Bagas Pratama', 'bagas@gmail.com', '', '', '', ''],
        ]]);

        app(ParticipantImporter::class)->import($event, $preview, ParticipantRole::SPEAKER);

        $peserta = $event->participations()->sole();
        $this->assertSame(ParticipantRole::PARTICIPANT->value, $peserta->role);
        $this->assertNull($peserta->role_label);
    }

    // ------------------------------------------------- yang tetap ditolak

    /**
     * "-" biasa dipakai untuk sel kosong dan tidak boleh tercetak sebagai
     * jabatan.
     */
    public function test_a_role_without_letters_is_refused(): void
    {
        $preview = $this->parse(['Sheet1' => [
            self::HEADER,
            ['Rina Wulandari', 'rina@jgu.ac.id', '-', '', '', ''],
        ]]);

        $this->assertSame(0, $preview->validCount());
        $this->assertStringContainsString('tidak berisi huruf', $preview->problems[0]['message']);
    }

    public function test_a_title_longer_than_the_limit_is_refused(): void
    {
        $batas = CertificateEventParticipant::ROLE_LABEL_MAX_LENGTH;

        $preview = $this->parse(['Sheet1' => [
            self::HEADER,
            ['Rina Wulandari', 'rina@jgu.ac.id', str_repeat('a', $batas), '', '', ''],
            ['Yoga Pratama', 'yoga@jgu.ac.id', str_repeat('a', $batas + 1), '', '', ''],
        ]]);

        $this->assertSame(['Rina Wulandari'], array_map(fn (ParticipantImportRow $row) => $row->name, $preview->validRows));
        $this->assertStringContainsString("maksimal {$batas} karakter", $preview->problems[0]['message']);
    }

    /**
     * Rumus yang gagal di Excel terbaca sebagai teksnya, misalnya #N/A dari
     * VLOOKUP. Dulu ditolak sebagai peran tak dikenal; sekarang ia tidak boleh
     * lolos sebagai jabatan.
     */
    public function test_a_failed_formula_in_the_role_column_is_refused(): void
    {
        $preview = $this->parse(['Sheet1' => [
            self::HEADER,
            ['Rina Wulandari', 'rina@jgu.ac.id', '#N/A', '', '', ''],
        ]]);

        $this->assertSame(0, $preview->validCount());
        $this->assertStringContainsString('Kolom peran berisi rumus', $preview->problems[0]['message']);
    }

    // ------------------------------------------------------ penyimpanan

    public function test_the_default_category_is_applied_when_nothing_implies_one(): void
    {
        $event = CertificateEvent::factory()->create();

        $preview = $this->parse(['Sheet1' => [
            self::HEADER,
            ['Rina Wulandari', 'rina@jgu.ac.id', 'Ketua Pelaksana', '', '', ''],
            ['Yoga Pratama', 'yoga@jgu.ac.id', 'Juri Tamu', '', '', ''],
        ]]);

        app(ParticipantImporter::class)->import($event, $preview, ParticipantRole::VOLUNTEER);

        $rina = $event->participations()->whereHas('participant', fn ($q) => $q->where('name', 'Rina Wulandari'))->sole();
        $this->assertSame(ParticipantRole::VOLUNTEER->value, $rina->role);
        $this->assertSame('Ketua Pelaksana', $rina->role_label);

        // Kategori yang tersirat tidak ditimpa Kategori default.
        $yoga = $event->participations()->whereHas('participant', fn ($q) => $q->where('name', 'Yoga Pratama'))->sole();
        $this->assertSame(ParticipantRole::JUDGE->value, $yoga->role);
        $this->assertSame('Juri Tamu', $yoga->role_label);
    }

    public function test_the_default_category_is_committee_unless_chosen(): void
    {
        $event = CertificateEvent::factory()->create();

        $preview = $this->parse(['Sheet1' => [
            self::HEADER,
            ['Rina Wulandari', 'rina@jgu.ac.id', 'Ketua Pelaksana', '', '', ''],
        ]]);

        app(ParticipantImporter::class)->import($event, $preview);

        $this->assertSame(ParticipantRole::COMMITTEE->value, $event->participations()->sole()->role);
    }

    /**
     * Pratinjau disimpan di cache selama setengah jam. Yang tersimpan sebelum
     * rilis ini tidak memuat jabatan, dan tetap harus terbaca.
     */
    public function test_a_preview_cached_before_titles_existed_still_reads(): void
    {
        $lama = ParticipantImportRow::fromArray([
            'line' => 2, 'name' => 'Rina Wulandari', 'email' => 'rina@jgu.ac.id', 'role' => 'committee',
            'institutional_id' => null, 'study_program' => null, 'certificate_number' => null, 'sheet' => null,
        ]);

        $this->assertSame(ParticipantRole::COMMITTEE, $lama->role);
        $this->assertNull($lama->roleLabel);

        $baru = ParticipantImportRow::fromArray((new ParticipantImportRow(
            line: 3, name: 'Yoga Pratama', email: null, role: null, roleLabel: 'Ketua Pelaksana',
        ))->toArray());

        $this->assertNull($baru->role, 'Kategori yang menunggu Kategori default hilang setelah lewat cache.');
        $this->assertSame('Ketua Pelaksana', $baru->roleLabel);
    }

    // --------------------------------------------------------- pratinjau

    public function test_titles_are_summarised_in_one_warning(): void
    {
        $preview = $this->parse([
            'Panitia' => [
                self::HEADER,
                ['Rina Wulandari', 'rina@jgu.ac.id', 'Koordinator Peserta', '', '', ''],
            ],
            'Daftar' => [
                self::HEADER,
                ['Yoga Pratama', 'yoga@jgu.ac.id', 'Juri Tamu', '', '', ''],
                ['Dewi Lestari', 'dewi@jgu.ac.id', 'Ketua Pelaksana', '', '', ''],
                ['Bagas Pratama', 'bagas@gmail.com', 'Peserta', '', '', ''],
            ],
        ]);

        $peringatan = implode(' | ', $preview->warnings);

        $this->assertStringContainsString('3 baris berisi jabatan', $peringatan);
        $this->assertStringContainsString('1 dari nama lembar, 1 dari kata peran di jabatannya, 1 mengikuti Kategori default', $peringatan);
    }

    /**
     * Yang diminta: hasil simpulan kategorinya terlihat sebelum import, untuk
     * setiap baris berjabatan, bukan hanya yang masuk contoh lima baris.
     */
    public function test_the_preview_shows_where_each_category_came_from(): void
    {
        $event = CertificateEvent::factory()->create();

        $modal = $this->table($event)
            ->mountTableAction('importParticipants')
            ->setTableActionData(['file' => $this->upload([
                'Panitia' => [
                    self::HEADER,
                    ['Budi Hartono', 'budi@jgu.ac.id', 'Koordinator Peserta', '', '', ''],
                ],
                'Daftar' => [
                    self::HEADER,
                    ['Yoga Pratama', 'yoga@jgu.ac.id', 'Juri Tamu', '', '', ''],
                    ['Dewi Lestari', 'dewi@jgu.ac.id', 'Ketua Pelaksana', '', '', ''],
                ],
            ])]);

        $pratinjau = $this->previewText($modal);

        $this->assertStringContainsString('Jabatan di luar daftar peran', $pratinjau);
        $this->assertStringContainsString('Budi Hartono, Koordinator Peserta (Panitia — dari nama lembar)', $pratinjau);
        $this->assertStringContainsString('Yoga Pratama, Juri Tamu (Juri — dari kata peran di jabatannya)', $pratinjau);
        $this->assertStringContainsString('Dewi Lestari, Ketua Pelaksana (Panitia — Kategori default)', $pratinjau);

        // Mengganti pilihannya langsung terlihat, tanpa mengunggah ulang.
        $modal->setTableActionData(['default_category' => ParticipantRole::MENTOR->value]);

        $this->assertStringContainsString('Dewi Lestari, Ketua Pelaksana (Mentor — Kategori default)', $this->previewText($modal));
    }

    /**
     * Berkas tanpa jabatan tidak perlu ditanyai kategorinya.
     */
    public function test_the_preview_leaves_out_titles_when_there_are_none(): void
    {
        $event = CertificateEvent::factory()->create();

        $modal = $this->table($event)
            ->mountTableAction('importParticipants')
            ->setTableActionData(['file' => $this->upload(['Sheet1' => [
                self::HEADER,
                ['Budi Hartono', 'budi@jgu.ac.id', 'Peserta', '', '', ''],
            ]])]);

        $this->assertStringNotContainsString('Jabatan di luar daftar peran', $this->previewText($modal));
        $this->assertFalse($this->defaultCategoryField($modal)->isVisible());
    }

    public function test_the_default_category_sits_beside_the_titles(): void
    {
        $event = CertificateEvent::factory()->create();

        $modal = $this->table($event)
            ->mountTableAction('importParticipants')
            ->setTableActionData(['file' => $this->upload(['Sheet1' => [
                self::HEADER,
                ['Rina Wulandari', 'rina@jgu.ac.id', 'Ketua Pelaksana', '', '', ''],
            ]])]);

        $kategori = $this->defaultCategoryField($modal);

        $this->assertTrue($kategori->isVisible());
        $this->assertSame('Kategori default', $kategori->getLabel());
        $this->assertSame(ParticipantRole::COMMITTEE->value, $kategori->getState());
    }

    private function defaultCategoryField(Testable $modal): Select
    {
        return collect($modal->instance()->mountedActionSchema0->getFlatComponents(withHidden: true))
            ->first(fn ($komponen) => $komponen instanceof Select && $komponen->getName() === 'default_category')
            ?? $this->fail('Pilihan Kategori default tidak ada di formulir import.');
    }

    // ------------------------------------------------------- dari panel

    public function test_the_chosen_default_category_reaches_the_database(): void
    {
        $event = CertificateEvent::factory()->create();

        $this->table($event)
            ->callTableAction('importParticipants', data: [
                'file' => $this->upload(['Sheet1' => [
                    self::HEADER,
                    ['Rina Wulandari', 'rina@jgu.ac.id', 'Ketua Pelaksana', '', '', ''],
                ]]),
                'default_category' => ParticipantRole::VOLUNTEER->value,
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Import selesai');

        $rina = $event->participations()->sole();
        $this->assertSame(ParticipantRole::VOLUNTEER->value, $rina->role);
        $this->assertSame('Ketua Pelaksana', $rina->role_label);
    }

    public function test_the_form_starts_with_committee_as_the_default_category(): void
    {
        $event = CertificateEvent::factory()->create();

        $this->table($event)
            ->callTableAction('importParticipants', data: ['file' => $this->upload(['Sheet1' => [
                self::HEADER,
                ['Rina Wulandari', 'rina@jgu.ac.id', 'Ketua Pelaksana', '', '', ''],
            ]])])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ParticipantRole::COMMITTEE->value, $event->participations()->sole()->role);
    }

    /**
     * Pilihan di formulir hanya pelengkap; yang menentukan adalah validasi
     * di server.
     */
    public function test_an_unknown_default_category_is_refused(): void
    {
        $event = CertificateEvent::factory()->create();

        $this->table($event)
            ->callTableAction('importParticipants', data: [
                'file' => $this->upload(['Sheet1' => [
                    self::HEADER,
                    ['Rina Wulandari', 'rina@jgu.ac.id', 'Ketua Pelaksana', '', '', ''],
                ]]),
                'default_category' => 'presiden',
            ])
            ->assertHasTableActionErrors(['default_category']);

        $this->assertSame(0, $event->participations()->count());
    }

    // ------------------------------------------------------ sampai cetak

    /**
     * Jabatan hasil import itulah yang tercetak di elemen Peran sertifikat.
     */
    public function test_the_imported_title_is_what_the_certificate_prints(): void
    {
        $event = CertificateEvent::factory()->published()->create();

        app(ParticipantImporter::class)->import($event, $this->parse(['Sheet1' => [
            self::HEADER,
            ['Rina Wulandari', 'rina@jgu.ac.id', 'Ketua Pelaksana', '', '', ''],
        ]]));

        $peserta = $event->participations()->sole();
        $peserta->update(['eligible_at' => now(), 'attendance_status' => 'attended']);

        $certificate = app(CertificateIssuer::class)->issue($peserta->fresh());

        $this->assertSame(ParticipantRole::COMMITTEE->value, $certificate->recipient_role);
        $this->assertSame('Ketua Pelaksana', app(CertificateRenderService::class)->variables($certificate->fresh('event'))['recipient_role']);
    }
}
