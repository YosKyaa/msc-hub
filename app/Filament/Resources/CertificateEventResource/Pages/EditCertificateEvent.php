<?php

namespace App\Filament\Resources\CertificateEventResource\Pages;

use App\Filament\Resources\CertificateEventResource;
use App\Filament\Resources\CertificateEventResource\Widgets\CertificateProgressWidget;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCertificateEvent extends EditRecord
{
    protected static string $resource = CertificateEventResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * Alur empat langkahnya di paling atas, sebelum formulir. Inilah yang
     * dicari orang begitu membuka kegiatan: sudah sampai mana, dan apa
     * berikutnya.
     */
    protected function getHeaderWidgets(): array
    {
        return [CertificateProgressWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
