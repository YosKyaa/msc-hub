<?php

namespace App\Filament\Resources\CertificateEventResource\Widgets;

use App\Models\CertificateEvent;
use App\Support\CertificateProgress;
use Filament\Widgets\Widget;

/**
 * Empat langkah membuat sertifikat, terbaca sekilas.
 *
 * Urutannya selama ini hanya ada di kepala orang yang sudah terbiasa. Yang
 * baru memakainya harus menyimpulkannya sendiri dari tombol yang kebetulan
 * tersedia, dan sering berhenti di langkah kedua tanpa tahu ada langkah
 * ketiga.
 *
 * Diletakkan di dalam resource-nya, bukan di App\Filament\Widgets: yang di
 * sana ikut terpasang di dasbor, dan di dasbor tidak ada kegiatan untuk
 * dihitung.
 */
class CertificateProgressWidget extends Widget
{
    protected string $view = 'filament.widgets.certificate-progress';

    protected int|string|array $columnSpan = 'full';

    /**
     * Digambar bersama halamannya, bukan menyusul.
     *
     * Bawaan Filament menunda widget sampai permintaan kedua — masuk akal
     * untuk grafik berat, tetapi ini hanya empat angka dari satu kueri
     * agregat. Menundanya justru membuat bagian paling atas halaman berkedip
     * kosong tepat di tempat mata orang mencari arah.
     */
    protected static bool $isLazy = false;

    public ?CertificateEvent $record = null;

    protected function getViewData(): array
    {
        $progress = CertificateProgress::for($this->record);

        return [
            'steps' => $progress->steps(),
            'nextStep' => $progress->nextStep(),
            'warning' => $progress->publicationWarning(),
            'done' => $progress->isDone(),
        ];
    }
}
