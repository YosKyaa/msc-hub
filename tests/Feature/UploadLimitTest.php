<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateTemplateResource;
use App\Filament\Resources\CertificateTemplateResource\Pages\CreateCertificateTemplate;
use App\Models\User;
use App\Support\UploadLimit;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batas ukuran unggahan.
 *
 * Batas sesungguhnya ditentukan PHP, dan bawaannya sering hanya 2 MB. Berkas
 * yang melewatinya ditolak sebelum Laravel sempat melihatnya, sehingga yang
 * muncul hanyalah "failed to upload" tanpa menyebut sebab maupun angkanya.
 * Desain latar sertifikat adalah satu-satunya isian yang dulu tidak memasang
 * batas sendiri, jadi persis di situlah pesan itu selalu muncul.
 */
class UploadLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Notasi singkat PHP diterjemahkan lewat metode privat; mengujinya
     * langsung lebih jujur daripada menebak dari luar.
     */
    private function toKilobytes(string $nilai): int
    {
        $metode = new ReflectionMethod(UploadLimit::class, 'toKilobytes');

        return $metode->invoke(null, $nilai);
    }

    public function test_it_understands_php_shorthand_sizes(): void
    {
        $this->assertSame(2048, $this->toKilobytes('2M'));
        $this->assertSame(8192, $this->toKilobytes('8M'));
        $this->assertSame(512, $this->toKilobytes('512K'));
        $this->assertSame(1048576, $this->toKilobytes('1G'));

        // Angka tanpa satuan berarti bita.
        $this->assertSame(1, $this->toKilobytes('1024'));
    }

    public function test_no_limit_reads_as_no_limit(): void
    {
        foreach (['', '0', '-1'] as $kosong) {
            $this->assertSame(0, $this->toKilobytes($kosong));
        }
    }

    /**
     * Inti gunanya: isian tidak pernah menjanjikan lebih besar daripada yang
     * sanggup diterima server.
     */
    public function test_a_field_never_promises_more_than_the_server_accepts(): void
    {
        $server = UploadLimit::maxKilobytes();

        $this->assertLessThanOrEqual($server, UploadLimit::forField(4096));
        $this->assertLessThanOrEqual($server, UploadLimit::forField(PHP_INT_MAX));
        $this->assertGreaterThanOrEqual(1, UploadLimit::forField(0));
    }

    public function test_a_field_may_ask_for_less_than_the_server_allows(): void
    {
        // Batas server di lingkungan tes jauh di atas ini, jadi yang
        // diinginkan itulah yang berlaku.
        if (UploadLimit::maxKilobytes() < 1024) {
            $this->markTestSkipped('Batas PHP di mesin ini terlalu kecil untuk menguji hal ini.');
        }

        $this->assertSame(1024, UploadLimit::forField(1024));
    }

    public function test_the_size_is_written_the_way_people_read_it(): void
    {
        $this->assertSame('4 MB', UploadLimit::describe(4096));
        $this->assertSame('1,5 MB', UploadLimit::describe(1536));
        $this->assertSame('512 KB', UploadLimit::describe(512));
    }

    // --------------------------------------------------------------- panel

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /**
     * Isian ini dulu satu-satunya yang tidak memasang batas, sehingga berkas
     * kebesaran hanya menghasilkan pesan Livewire yang tidak menjelaskan apa pun.
     */
    public function test_the_certificate_background_now_states_its_limit(): void
    {
        $this->actingAs($this->staff());

        $response = $this->get(CertificateTemplateResource::getUrl('create'))->assertOk();

        $response->assertSee('maksimal', false);
        $response->assertSee('ditanam ke dalam setiap PDF sertifikat', false);
    }

    /**
     * Seluruh isi app/, bukan hanya panel: unggahan bisa saja lahir di
     * RelationManager atau Livewire, dan guard yang berhenti menyapu diam-diam
     * lebih menyesatkan daripada tidak ada guard sama sekali.
     *
     * @return list<string>
     */
    private function phpFiles(): array
    {
        $berkas = [];

        $isi = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($isi as $file) {
            if ($file->getExtension() === 'php') {
                $berkas[] = $file->getPathname();
            }
        }

        return $berkas;
    }

    public function test_the_sweep_actually_reaches_the_uploads(): void
    {
        $dengarUnggahan = array_filter(
            $this->phpFiles(),
            fn (string $f): bool => str_contains(file_get_contents($f), 'FileUpload::make(')
        );

        // Penjaga bagi penjaga: bila penyusurannya rusak, test di bawah lulus
        // tanpa memeriksa apa pun.
        $this->assertGreaterThanOrEqual(5, count($dengarUnggahan));
    }

    public function test_every_upload_in_the_panel_carries_a_limit(): void
    {
        $tanpaBatas = [];

        foreach ($this->phpFiles() as $berkas) {
            $isi = file_get_contents($berkas);

            // Tiap FileUpload harus diikuti maxSize sebelum penutupnya.
            foreach (explode('FileUpload::make(', $isi) as $i => $potongan) {
                if ($i === 0) {
                    continue;
                }

                if (! str_contains(substr($potongan, 0, 1200), 'maxSize')) {
                    $tanpaBatas[] = basename($berkas);
                }
            }
        }

        $this->assertSame([], $tanpaBatas,
            'Unggahan tanpa maxSize hanya menghasilkan pesan "failed to upload": '.implode(', ', $tanpaBatas));
    }

    public function test_the_create_page_still_opens(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CreateCertificateTemplate::class)->assertSuccessful();
    }
}
