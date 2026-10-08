<?php

namespace Tests\Feature;

use App\Filament\Actions\ImportParticipantsAction;
use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\CertificateEvent;
use App\Models\User;
use App\Services\Certificates\Import\ParticipantImporter;
use App\Services\Certificates\Import\ParticipantImportSession;
use Database\Seeders\RoleSeeder;
use Filament\Schemas\Components\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Impor peserta, ditempuh lewat modal yang benar-benar diklik admin.
 *
 * Test lain menguji layanan impornya langsung. Yang tidak terjangkau dari
 * sana adalah jahitan di antara langkah wizard: tombol Lanjut Filament hanya
 * memvalidasi, tidak menyimpan berkas, sehingga langkah Pratinjau menerima
 * unggahan sementara alih-alih path tersimpan.
 */
class ParticipantImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['nama_sertifikat', 'email', 'peran', 'nim_nip', 'unit_prodi', 'nomor_sertifikat'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ParticipantImportSession::TEMP_DISK);
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function table(CertificateEvent $event): Testable
    {
        return Livewire::actingAs($this->admin())->test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function xlsx(array $rows, string $name = 'peserta.xlsx', string $format = 'Xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'impor').'.'.strtolower($format);
        IOFactory::createWriter($spreadsheet, $format)->save($path);

        $isi = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent($name, $isi);
    }

    /**
     * Bagian pratinjau yang tampil, beserta warnanya.
     *
     * @return array<int, array{color: ?string, text: string}>
     */
    private function visibleSections(Testable $modal): array
    {
        $skema = $modal->instance()->mountedActionSchema0;

        return collect($skema->getFlatComponents())
            ->filter(fn ($komponen) => $komponen instanceof Text && $komponen->isVisible())
            ->map(fn (Text $komponen) => [
                'color' => is_string($komponen->getColor()) ? $komponen->getColor() : null,
                'text' => strip_tags((string) $komponen->getContent()),
            ])
            ->values()
            ->all();
    }

    private function sectionText(Testable $modal, string $color): string
    {
        return collect($this->visibleSections($modal))
            ->where('color', $color)
            ->pluck('text')
            ->implode(' ');
    }

    /**
     * Inti alur dua langkahnya: setelah berkas diunggah, pratinjau harus
     * memperlihatkan isinya, bukan meminta berkas diunggah lagi.
     */
    public function test_the_preview_step_reads_the_uploaded_file(): void
    {
        $event = CertificateEvent::factory()->create();

        $modal = $this->table($event)
            ->mountTableAction('importParticipants')
            ->setTableActionData(['file' => $this->xlsx([
                self::HEADER,
                ['Budi Hartono', 'budi.hartono@student.jgu.ac.id', 'Peserta', '', '', ''],
                ['Rina Wulandari', 'rina.w@student.jgu.ac.id', 'Panitia', '', '', ''],
            ])]);

        $pratinjau = $this->previewText($modal);

        $this->assertStringContainsString('2 baris valid', $pratinjau);
        $this->assertStringNotContainsString('Unggah berkas terlebih dahulu', $pratinjau);
    }

    /**
     * Isi langkah Pratinjau sebagaimana dilihat admin.
     *
     * Modal tindakan tidak ikut ter-render di HTML komponen yang diuji, jadi
     * isinya dibaca dari skema modal yang sedang terbuka.
     */
    private function previewText(Testable $modal): string
    {
        $skema = $modal->instance()->mountedActionSchema0;

        $teks = collect($skema->getFlatComponents(withHidden: true))
            ->filter(fn ($komponen) => $komponen instanceof \Filament\Schemas\Components\Text)
            ->map(fn ($komponen) => (string) $komponen->getContent())
            ->implode(PHP_EOL);

        return $teks;
    }

    /**
     * Baris bermasalah tampil dalam warna galat dan peringatan dalam warna
     * peringatan. Kelas Tailwind yang dulu dipakai tidak ikut terkompilasi di
     * panel ini, sehingga keduanya tampil kelabu seperti teks biasa.
     */
    public function test_problems_and_warnings_are_shown_in_their_own_colours(): void
    {
        $event = CertificateEvent::factory()->create();

        $modal = $this->table($event)
            ->mountTableAction('importParticipants')
            ->setTableActionData(['file' => $this->xlsx([
                [...self::HEADER, 'catatan'],
                ['Budi Hartono', 'budi.hartono@student.jgu.ac.id', 'Peserta', '', '', '', ''],
                ['Rina Wulandari', 'bukan-email', 'Panitia', '', '', '', ''],
            ])]);

        $this->assertStringContainsString('Baris 3: Format email tidak valid', $this->sectionText($modal, 'danger'));
        $this->assertStringContainsString('tidak dikenal dan diabaikan: catatan', $this->sectionText($modal, 'warning'));
    }

    /**
     * Berkas yang keliru tidak boleh merusak modalnya. Admin diberi tahu dan
     * bisa kembali memilih berkas lain.
     */
    public function test_a_broken_upload_is_explained_in_the_preview(): void
    {
        $event = CertificateEvent::factory()->create();

        $modal = $this->table($event)
            ->mountTableAction('importParticipants')
            ->setTableActionData(['file' => UploadedFile::fake()->createWithContent(
                'peserta.xlsx',
                "\x89PNG\r\n\x1a\n".str_repeat("\x00\xFF", 200),
            )]);

        $this->assertStringContainsString('tidak dapat dibaca sebagai lembar kerja', $this->sectionText($modal, 'danger'));
    }

    /**
     * Validasi Filament memeriksa jenis berkas dari isinya. CSV dikenali
     * sebagai text/plain atau text/csv.
     */
    public function test_a_csv_upload_passes_through_the_modal(): void
    {
        $event = CertificateEvent::factory()->create();

        $this->table($event)
            ->callTableAction('importParticipants', data: ['file' => UploadedFile::fake()->createWithContent(
                'peserta.csv',
                "nama_sertifikat;email\r\nBudi Hartono;budi.hartono@student.jgu.ac.id\r\n",
            )])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $event->participations()->count());
    }

    /**
     * Di server, jenis berkas dikenali dari isinya: berkas LibreOffice, dan
     * .xlsx pada libmagic lama, terbaca sebagai application/zip, dan dulu
     * ditolak sebelum sempat dibaca.
     *
     * Unggahan palsu Livewire di test memakai jenis yang ditebak dari
     * ekstensi, jadi aturannya diuji terhadap berkas sungguhan di sini.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function realFiles(): array
    {
        return [
            'LibreOffice' => ['Ods', 'ods'],
            'Excel' => ['Xlsx', 'xlsx'],
            'Excel 97-2003' => ['Xls', 'xls'],
            'CSV' => ['Csv', 'csv'],
        ];
    }

    #[DataProvider('realFiles')]
    public function test_real_files_pass_the_type_check_by_their_content(string $penulis, string $ekstensi): void
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray([['nama_sertifikat', 'email'], ['Budi Hartono', 'budi.h@student.jgu.ac.id']]);

        $path = tempnam(sys_get_temp_dir(), 'jenis').'.'.$ekstensi;
        IOFactory::createWriter($buku, $penulis)->save($path);

        $berkas = new UploadedFile($path, 'peserta.'.$ekstensi, null, null, true);

        try {
            $lolos = Validator::make(
                ['berkas' => $berkas],
                ['berkas' => 'mimetypes:'.implode(',', ImportParticipantsAction::ACCEPTED_FILE_TYPES)],
            )->passes();

            $this->assertTrue($lolos, "Berkas .{$ekstensi} dikenali sebagai {$berkas->getMimeType()} dan ditolak.");
        } finally {
            unlink($path);
        }
    }

    public function test_a_libreoffice_upload_passes_through_the_modal(): void
    {
        $event = CertificateEvent::factory()->create();

        $this->table($event)
            ->callTableAction('importParticipants', data: ['file' => $this->xlsx([
                ['nama_sertifikat', 'email'],
                ['Budi Hartono', 'budi.hartono@student.jgu.ac.id'],
            ], 'peserta.ods', 'Ods')])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $event->participations()->count());
    }

    /**
     * Galat yang tidak diduga dulu menjadi layar galat. Kini admin diberi
     * tahu bahwa tidak ada yang tersimpan, sehingga berkas yang sama aman
     * diunggah ulang.
     */
    public function test_an_unexpected_failure_while_saving_is_explained(): void
    {
        $this->mock(ParticipantImporter::class)
            ->shouldReceive('import')
            ->andThrow(new RuntimeException('Koneksi basis data terputus'));

        $event = CertificateEvent::factory()->create();

        $this->table($event)
            ->callTableAction('importParticipants', data: ['file' => $this->xlsx([
                self::HEADER,
                ['Budi Hartono', 'budi.hartono@student.jgu.ac.id', 'Peserta', '', '', ''],
            ])])
            ->assertNotified('Import gagal');

        $this->assertSame(0, $event->participations()->count());
        $this->assertSame([], Storage::disk(ParticipantImportSession::TEMP_DISK)->files(ParticipantImportSession::TEMP_DIRECTORY),
            'Berkas peserta tertinggal di server setelah impornya gagal.');
    }

    public function test_confirming_imports_the_rows(): void
    {
        $event = CertificateEvent::factory()->create();

        $this->table($event)
            ->callTableAction('importParticipants', data: ['file' => $this->xlsx([
                self::HEADER,
                ['Budi Hartono', 'budi.hartono@student.jgu.ac.id', 'Peserta', '', '', ''],
                ['Rina Wulandari', 'rina.w@student.jgu.ac.id', 'Panitia', '', '', ''],
            ])])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Import selesai');

        $this->assertSame(2, $event->participations()->count());
    }
}
