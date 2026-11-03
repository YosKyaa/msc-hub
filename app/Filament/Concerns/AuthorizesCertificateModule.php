<?php

namespace App\Filament\Concerns;

use App\Support\CertificatePermission;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Otorisasi granular untuk resource modul sertifikat di panel Filament.
 * Pemetaan aksi ke permission ada di App\Support\CertificatePermission.
 *
 * Pemetaannya ditaruh di getAuthorizationResponse(), satu-satunya jalur yang
 * dilalui halaman maupun tombol aksi bawaan Filament. Menimpa canDelete() dan
 * kawan-kawannya saja tidak cukup: halaman Ubah dan Buat memang memanggilnya,
 * tetapi tombol Hapus di tabel, hapus massal, dan tombol Hapus di halaman Ubah
 * langsung meminta getDeleteAuthorizationResponse(), yang tanpa policy selalu
 * mengizinkan. Dulu staf tanpa certificates.delete karenanya tetap bisa
 * menghapus kegiatan beserta seluruh pesertanya, template, penerbit, dan
 * peserta.
 */
trait AuthorizesCertificateModule
{
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        $kemampuan = match ($action) {
            'viewAny', 'view' => 'view',
            // Menggandakan berarti membuat catatan baru.
            'create', 'replicate' => 'create',
            'update', 'reorder' => 'edit',
            'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny' => 'delete',
            // Aksi yang tidak dikenal ditutup, bukan dibuka.
            default => null,
        };

        return $kemampuan !== null && CertificatePermission::allows($kemampuan)
            ? Response::allow()
            : Response::deny();
    }
}
