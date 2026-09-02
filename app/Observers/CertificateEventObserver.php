<?php

namespace App\Observers;

use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEvent;

/**
 * Sertifikat baru sah setelah kegiatannya dipublikasikan, sehingga email yang
 * tertunda menyusul tepat pada saat publikasi.
 */
class CertificateEventObserver
{
    public function updated(CertificateEvent $event): void
    {
        if (! $event->wasChanged('status') || ! $event->isPublished()) {
            return;
        }

        $event->certificates()
            ->notYetEmailed()
            ->each(fn (Certificate $certificate) => SendCertificateEmailJob::dispatch($certificate));
    }
}
