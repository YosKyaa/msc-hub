<?php

namespace App\Filament\Concerns;

use App\Support\CertificatePermission;
use Illuminate\Database\Eloquent\Model;

/**
 * Otorisasi granular untuk resource modul sertifikat di panel Filament.
 * Pemetaan aksi ke permission ada di App\Support\CertificatePermission.
 */
trait AuthorizesCertificateModule
{
    public static function canAccess(): bool
    {
        return CertificatePermission::allows('view');
    }

    public static function canViewAny(): bool
    {
        return CertificatePermission::allows('view');
    }

    public static function canView(Model $record): bool
    {
        return CertificatePermission::allows('view');
    }

    public static function canCreate(): bool
    {
        return CertificatePermission::allows('create');
    }

    public static function canEdit(Model $record): bool
    {
        return CertificatePermission::allows('edit');
    }

    public static function canDelete(Model $record): bool
    {
        return CertificatePermission::allows('delete');
    }

    public static function canDeleteAny(): bool
    {
        return CertificatePermission::allows('delete');
    }
}
