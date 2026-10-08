<?php

namespace Tests\Feature;

use App\Enums\ParticipantRole;
use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\CertificateEvent;
use App\Models\Participant;
use App\Models\User;
use App\Services\Certificates\CertificateBatchMailer;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\Import\ParticipantImporter;
use App\Services\Certificates\Import\ParticipantImportParser;
use App\Services\Certificates\Import\ParticipantImportPreview;
use App\Services\Certificates\Import\ParticipantImportSession;
use App\Services\Certificates\ParticipantRegistry;
use App\Services\Certificates\RecipientCorrection;
use App\Support\CertificateStage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Daftar panitia dan peserta PKKMB.
 *
 * Diturunkan dari berkas PKKMB 2026 yang sungguhan, dengan nama dan alamat
 * rekaan. Berkas itu memuat semua yang membuat impor lama tersandung:
 * mahasiswa baru yang belum punya email kampus dan memakai Gmail, puluhan
 * yang belum punya email sama sekali, alamat yang terbawa koma, domain
 * kampus yang salah ketik, dan panitia serta peserta di lembar terpisah
 * dalam satu dokumen Google Sheets.
 */
class ParticipantImportPkkmbTest extends TestCase
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
     * Satu berkas, satu lembar per daftar. NIM ditulis sebagai teks, seperti
     * di Google Sheets, supaya nol di depannya tidak hilang.
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

        $path = tempnam(sys_get_temp_dir(), 'pkkmb').'.xlsx';
        IOFactory::createWriter($buku, 'Xlsx')->save($path);

        return $this->berkas[] = $path;
    }

    private function parse(string $path): ParticipantImportPreview
    {
        return app(ParticipantImportParser::class)->parse($path);
    }

    private function import(CertificateEvent $event, string $path): \App\Services\Certificates\Import\ParticipantImportResult
    {
        return app(ParticipantImporter::class)->import($event, $this->parse($path));
    }

    private function warnings(ParticipantImportPreview $preview): string
    {
        return implode(' | ', $preview->warnings);
    }

    // ------------------------------------------------------ email pribadi

    /**
     * Yang ditanyakan: mahasiswa baru yang belum punya email kampus memakai
     * Gmail. Alamat itu diterima sama seperti alamat kampus, untuk panitia
     * maupun peserta.
     */
    public function test_personal_addresses_are_accepted_for_committee_and_participants(): void
    {
        $event = CertificateEvent::factory()->create();

        $hasil = $this->import($event, $this->workbook([
            'Panitia' => [self::HEADER, ['Rina Wulandari', 'rina.panitia.pkkmb@gmail.com', 'Panitia', '', 'BEM', '']],
            'Peserta' => [self::HEADER, ['Bagas Pratama', 'bagas.pratama.baru@gmail.com', 'Peserta', '2611030099', 'S1 Teknik Elektro', '']],
        ]));

        $this->assertSame(2, $hasil->created);
        $this->assertSame('guest', Participant::where('email', 'bagas.pratama.baru@gmail.com')->value('type'));
        $this->assertSame('rina.panitia.pkkmb@gmail.com', Participant::where('name', 'Rina Wulandari')->value('email'));
    }

    /**
     * Daftar yang disusun dengan menyalin alamat dari pesan berantai
     * meninggalkan koma di ujungnya. Koma itu tidak pernah bagian dari alamat.
     */
    public function test_a_trailing_comma_is_dropped_from_the_address(): void
    {
        $preview = $this->parse($this->workbook([
            'Peserta' => [self::HEADER, ['Bagas Pratama', '2611050099@student.jgu.ac.id,', 'Peserta', '2611050099', 'S1 Teknik Industri', '']],
        ]));

        $this->assertSame([], $preview->problems);
        $this->assertSame('2611050099@student.jgu.ac.id', $preview->validRows[0]->email);
    }

    public function test_other_leftovers_around_an_address_are_dropped_too(): void
    {
        $this->assertSame('bagas@gmail.com', ParticipantRegistry::normaliseEmail('mailto:Bagas@Gmail.com'));
        $this->assertSame('bagas@gmail.com', ParticipantRegistry::normaliseEmail(' <bagas@gmail.com>; '));
        $this->assertSame('bagas@gmail.com', ParticipantRegistry::normaliseEmail('bagas@gmail.com.'));
    }

    /**
     * Tanda kutip termasuk bagian alamat yang sah, jadi tidak ikut dibuang.
     */
    public function test_a_quoted_address_is_left_whole(): void
    {
        $this->assertSame('"bagas pratama"@contoh.com', ParticipantRegistry::normaliseEmail('"Bagas Pratama"@contoh.com'));
    }

    // -------------------------------------------------- domain salah ketik

    /**
     * Dua domain yang muncul di berkas aslinya. Pesertanya tetap masuk,
     * tetapi admin perlu tahu emailnya tidak akan sampai.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mistypedDomains(): array
    {
        return [
            'jgu.ic.id' => ['2611040099@student.jgu.ic.id', 'student.jgu.ac.id'],
            'studen' => ['2611090099@studen.jgu.ac.id', 'student.jgu.ac.id'],
            'staf' => ['dosen.contoh@jgu.ac.od', 'jgu.ac.id'],
            'gmial' => ['bagas.pratama@gmial.com', 'gmail.com'],
            'gmail.co' => ['bagas.pratama@gmail.co', 'gmail.com'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mistypedDomains')]
    public function test_a_mistyped_domain_is_flagged_but_still_imported(string $email, string $saran): void
    {
        $preview = $this->parse($this->workbook([
            'Peserta' => [self::HEADER, ['Bagas Pratama', $email, 'Peserta', '', '', '']],
        ]));

        $this->assertSame(1, $preview->validCount());
        $this->assertStringContainsString("Baris 2: domain {$this->domain($email)} mirip {$saran}", $this->warnings($preview));
    }

    /**
     * Kampus lain dan penyedia lain bukan salah ketik. Pada selisih dua huruf,
     * student.ugj.ac.id ikut dicurigai; pada ukuran kemiripan biasa, mail.com
     * dan ymail.com dikira salah ketik gmail.com.
     *
     * @return array<string, array{0: string}>
     */
    public static function legitimateDomains(): array
    {
        return [
            'kampus JGU' => ['bagas@student.jgu.ac.id'],
            'staf JGU' => ['bagas@jgu.ac.id'],
            'Gmail' => ['bagas@gmail.com'],
            'kampus lain' => ['bagas@student.ugj.ac.id'],
            'mail.com' => ['bagas@mail.com'],
            'ymail.com' => ['bagas@ymail.com'],
            'Yahoo Indonesia' => ['bagas@yahoo.co.id'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('legitimateDomains')]
    public function test_a_legitimate_domain_raises_no_warning(string $email): void
    {
        $this->assertNull(ParticipantRegistry::suggestDomain($email));
    }

    private function domain(string $email): string
    {
        return substr($email, strrpos($email, '@') + 1);
    }

    // ------------------------------------------------------- tanpa email

    /**
     * Yang diminta: tetap dibuatkan sertifikat walau tidak ada emailnya.
     * Sertifikatnya terbit dengan status "Terbit, tanpa email", tidak ikut
     * antrean kirim, dan bisa dikirim begitu alamatnya ditambahkan.
     */
    public function test_someone_without_an_email_still_gets_a_certificate(): void
    {
        $event = CertificateEvent::factory()->published()->create();

        $this->import($event, $this->workbook([
            'Peserta' => [
                self::HEADER,
                ['Bagas Pratama', 'bagas.pratama.baru@gmail.com', 'Peserta', '', 'S1 Farmasi', ''],
                ['Citra Lestari', '', 'Peserta', '', 'S1 Farmasi', ''],
            ],
        ]));

        $citra = $event->participations()->whereRelation('participant', 'name', 'Citra Lestari')->sole();
        $sertifikat = app(CertificateIssuer::class)->issue($citra);

        $this->assertNull($sertifikat->recipient_email);
        $this->assertSame(CertificateStage::ISSUED_WITHOUT_EMAIL, CertificateStage::for($citra->fresh()));
        $this->assertTrue($sertifikat->isValid(), 'Sertifikat tanpa email tidak sah.');

        app(CertificateIssuer::class)->issue($event->participations()->whereRelation('participant', 'name', 'Bagas Pratama')->sole());
        $this->assertSame(1, app(CertificateBatchMailer::class)->pendingCountFor($event),
            'Sertifikat tanpa email ikut dihitung menunggu kirim.');

        // Begitu alamatnya ada, sertifikatnya ikut siap dikirim.
        app(RecipientCorrection::class)->apply($citra->participant, 'Citra Lestari', 'citra.lestari.baru@gmail.com');

        $this->assertTrue($sertifikat->fresh()->awaitsEmail());
        $this->assertSame(2, app(CertificateBatchMailer::class)->pendingCountFor($event));
    }

    /**
     * Satu peringatan ringkas, bukan satu per baris: daftar aslinya memuat
     * empat puluh satu orang tanpa email.
     */
    public function test_people_without_an_email_are_summed_up_in_one_warning(): void
    {
        $rows = [self::HEADER];

        foreach (range(1, 12) as $i) {
            $rows[] = ["Peserta Tanpa Email {$i}", '', 'Peserta', '', 'S1 Manajemen', ''];
        }

        $preview = $this->parse($this->workbook(['Peserta' => $rows]));

        $this->assertSame(12, $preview->validCount());
        $this->assertCount(1, $preview->warnings);
        $this->assertStringStartsWith('12 orang tanpa email', $preview->warnings[0]);
        $this->assertStringContainsString('dan 2 lainnya', $preview->warnings[0]);
    }

    /**
     * Tanpa email tidak ada kunci dedup. Mengimpor ulang berkas yang sama
     * tetap tidak boleh menggandakan siapa pun.
     */
    public function test_importing_again_does_not_duplicate_people_without_an_email(): void
    {
        $event = CertificateEvent::factory()->create();
        $berkas = $this->workbook([
            'Peserta' => [
                self::HEADER,
                ['Citra Lestari', '', 'Peserta', '', 'S1 Farmasi', ''],
                ['Dimas Saputra', '', 'Peserta', '2611070099', 'S1 Farmasi', ''],
            ],
        ]);

        $this->assertSame(2, $this->import($event, $berkas)->created);

        $ulang = $this->import($event, $berkas);

        $this->assertSame(0, $ulang->created);
        $this->assertSame(2, $ulang->skipped);
        $this->assertSame(2, Participant::count());
    }

    /**
     * Dua orang bernama sama di kegiatan lain bukan orang yang sama.
     * Menyatukan mereka berarti sertifikat yang satu tercetak atas data
     * yang lain.
     */
    public function test_people_without_an_email_are_not_matched_across_events(): void
    {
        $berkas = $this->workbook(['Peserta' => [self::HEADER, ['Muhammad Rizki', '', 'Peserta', '', '', '']]]);

        $this->import(CertificateEvent::factory()->create(), $berkas);
        $this->import(CertificateEvent::factory()->create(), $berkas);

        $this->assertSame(2, Participant::where('name', 'Muhammad Rizki')->count());
    }

    public function test_the_same_name_with_a_different_nim_is_a_different_person(): void
    {
        $event = CertificateEvent::factory()->create();

        $hasil = $this->import($event, $this->workbook([
            'Peserta' => [
                self::HEADER,
                ['Muhammad Rizki', '', 'Peserta', '2611010091', '', ''],
                ['Muhammad Rizki', '', 'Peserta', '2611010092', '', ''],
            ],
        ]));

        $this->assertSame(2, $hasil->created);
    }

    /**
     * Baris yang sama persis dua kali di berkas yang sama — nama dan NIM —
     * akan menjadi satu orang saat ditulis, jadi yang kedua disebut sejak
     * pratinjau, bukan diam-diam hilang.
     */
    public function test_an_exact_repeat_without_an_email_is_reported(): void
    {
        $preview = $this->parse($this->workbook([
            'Peserta' => [
                self::HEADER,
                ['Citra Lestari', '', 'Peserta', '', 'S1 Farmasi', ''],
                ['Citra Lestari', '', 'Peserta', '', 'S1 Farmasi', ''],
            ],
        ]));

        $this->assertSame(1, $preview->validCount());
        $this->assertStringContainsString('sudah tercantum di baris 2', $preview->problems[0]['message']);
    }

    // ------------------------------------------------- panitia dan peserta

    /**
     * Dokumen Google Sheets "Panitia-Peserta" diunduh sebagai satu berkas
     * dengan dua lembar. Dulu hanya lembar pertama yang dibaca, dan panitia
     * yang kolom perannya kosong tercatat sebagai peserta — peran itulah
     * yang tercetak di sertifikat.
     */
    public function test_committee_and_participant_sheets_are_both_imported_with_their_roles(): void
    {
        $event = CertificateEvent::factory()->create();

        $preview = $this->parse($this->workbook([
            'Panitia' => [
                self::HEADER,
                ['Rina Wulandari', 'rina.panitia.pkkmb@gmail.com', '', '', 'BEM', ''],
                ['Yoga Pratama', 'yoga.panitia@jgu.ac.id', 'Moderator', '', 'BEM', ''],
            ],
            'Peserta' => [
                self::HEADER,
                ['Bagas Pratama', 'bagas.pratama.baru@gmail.com', '', '2611030099', 'S1 Teknik Elektro', ''],
            ],
        ]));

        $peran = array_map(fn ($row) => [$row->name, $row->role, $row->sheet], $preview->validRows);

        $this->assertSame([
            ['Rina Wulandari', ParticipantRole::COMMITTEE, 'Panitia'],
            // Peran yang ditulis selalu menang atas nama lembarnya.
            ['Yoga Pratama', ParticipantRole::MODERATOR, 'Panitia'],
            ['Bagas Pratama', ParticipantRole::PARTICIPANT, 'Peserta'],
        ], $peran);

        $this->assertStringContainsString('Lembar "Panitia": 1 baris tanpa peran dicatat sebagai Panitia', $this->warnings($preview));

        $this->assertSame(3, app(ParticipantImporter::class)->import($event, $preview)->created);
        $this->assertSame(1, $event->participations()->where('role', ParticipantRole::COMMITTEE->value)->count());
    }

    /**
     * @return array<string, array{0: string, 1: ParticipantRole}>
     */
    public static function sheetTitles(): array
    {
        return [
            'nama peran saja' => ['Panitia', ParticipantRole::COMMITTEE],
            'dengan nama kegiatan' => ['Panitia PKKMB 2026', ParticipantRole::COMMITTEE],
            'huruf kecil' => ['pembicara', ParticipantRole::SPEAKER],
            // Menyebut dua peran berarti tidak menyiratkan satu pun.
            'dua peran' => ['Panitia-Peserta', ParticipantRole::PARTICIPANT],
            'tanpa peran' => ['Sheet1', ParticipantRole::PARTICIPANT],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sheetTitles')]
    public function test_an_empty_role_follows_the_sheet_title(string $judul, ParticipantRole $harapan): void
    {
        $preview = $this->parse($this->workbook([
            $judul => [self::HEADER, ['Rina Wulandari', 'rina.panitia.pkkmb@gmail.com', '', '', '', '']],
        ]));

        $this->assertSame($harapan, $preview->validRows[0]->role);
    }

    /**
     * Dengan dua lembar, "baris 5" bisa berarti dua orang. Letaknya disebut
     * lengkap dengan nama lembarnya.
     */
    public function test_problems_name_their_sheet_when_there_are_several(): void
    {
        $preview = $this->parse($this->workbook([
            'Panitia' => [self::HEADER, ['Rina Wulandari', 'rina.panitia.pkkmb@gmail.com', '', '', '', '']],
            'Peserta' => [
                self::HEADER,
                ['Bagas Pratama', 'bukan-email', '', '', '', ''],
                ['Rina Wulandari', 'rina.panitia.pkkmb@gmail.com', '', '', '', ''],
            ],
        ]));

        $this->assertSame('Peserta', $preview->problems[0]['sheet']);
        $this->assertSame('Baris 2 (Peserta)', ParticipantImportPreview::location($preview->problems[0]['sheet'], $preview->problems[0]['line']));

        // Orang yang tercantum di dua lembar: yang kedua menunjuk lembar pertamanya.
        $this->assertStringContainsString('duplikat dengan baris 2 (Panitia)', $preview->problems[1]['message']);
    }

    /**
     * Kolom peran kerap diisi sampai ratusan baris ke bawah lebih dulu, lewat
     * pilihan di template. Baris tanpa nama, email, maupun NIM itu tidak
     * memuat siapa pun: tidak dilaporkan bermasalah, dan tidak dihitung ke
     * batas 500 baris yang bisa membuat seluruh berkas ditolak.
     */
    public function test_a_role_column_filled_far_down_ahead_of_time_is_ignored(): void
    {
        $rows = [
            self::HEADER,
            ['Bagas Pratama', 'bagas.pratama.baru@gmail.com', 'Peserta', '', '', ''],
            ...array_fill(0, ParticipantImportParser::MAX_ROWS, ['', '', 'Peserta', '', 'S1 Manajemen', '']),
        ];

        $preview = $this->parse($this->workbook(['Peserta' => $rows]));

        $this->assertSame(1, $preview->validCount());
        $this->assertSame([], $preview->problems);
    }

    /**
     * Baris yang punya NIM tetapi tanpa nama memang memuat seseorang, jadi
     * tetap dilaporkan supaya namanya dilengkapi.
     */
    public function test_a_row_with_only_a_nim_is_still_reported(): void
    {
        $preview = $this->parse($this->workbook([
            'Peserta' => [self::HEADER, ['', '', 'Peserta', '2611070099', 'S1 Farmasi', '']],
        ]));

        $this->assertStringContainsString('nama_sertifikat wajib diisi', $preview->problems[0]['message']);
    }

    // ---------------------------------------------------------- dari modal

    /**
     * Berkas dua lembar ditempuh lewat modal yang benar-benar diklik admin.
     */
    public function test_a_two_sheet_file_goes_through_the_modal(): void
    {
        Storage::fake(ParticipantImportSession::TEMP_DISK);

        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $event = CertificateEvent::factory()->create();

        $path = $this->workbook([
            'Panitia' => [self::HEADER, ['Rina Wulandari', 'rina.panitia.pkkmb@gmail.com', '', '', 'BEM', '']],
            'Peserta' => [
                self::HEADER,
                ['Bagas Pratama', 'bagas.pratama.baru@gmail.com', 'Peserta', '', 'S1 Teknik Elektro', ''],
                ['Citra Lestari', '', 'Peserta', '', 'S1 Farmasi', ''],
            ],
        ]);

        Livewire::actingAs($admin->fresh())
            ->test(ParticipationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditCertificateEvent::class])
            ->callTableAction('importParticipants', data: [
                'file' => UploadedFile::fake()->createWithContent('Panitia-Peserta-PKKMB.xlsx', file_get_contents($path)),
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Import selesai');

        $this->assertSame(3, $event->participations()->count());
        $this->assertSame(1, $event->participations()->where('role', ParticipantRole::COMMITTEE->value)->count());
    }
}
