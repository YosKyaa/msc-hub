<?php

namespace App\Filament\Pages;

use App\Support\PanduanPanel;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Rincian satu topik panduan.
 *
 * Panduannya dulu satu halaman panjang berisi semua modul sekaligus, sehingga
 * yang dicari harus digulir dulu. Sekarang halaman depannya berupa kartu, dan
 * tiap kartu membuka halaman ini.
 */
class DokumentasiTopik extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'dokumentasi/{topik}';

    protected string $view = 'filament.pages.dokumentasi-topik';

    public string $topik = '';

    /** @var array<string, mixed> */
    public array $isi = [];

    public function mount(string $topik): void
    {
        $isi = PanduanPanel::topik($topik);

        if ($isi === null) {
            throw new NotFoundHttpException('Topik panduan tidak ditemukan.');
        }

        $this->topik = $topik;
        $this->isi = $isi;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->isi['judul'] ?? 'Dokumentasi';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->isi['ringkas'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            Dokumentasi::getUrl() => 'Dokumentasi',
            $this->isi['judul'] ?? 'Topik',
        ];
    }
}
