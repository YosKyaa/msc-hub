<?php

namespace App\Filament\Concerns;

use App\Support\PanelAbility;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Otorisasi granular untuk resource panel, dari satu awalan izin.
 *
 * Filament **mengizinkan** ketika sebuah aksi tidak punya policy: lihat
 * `Filament\get_authorization_response()`, yang mengembalikan `Response::allow()`
 * bila model tidak punya policy atau policy-nya tidak punya metode aksi itu.
 * Akibatnya resource yang hanya menjaga `canAccess()` membiarkan siapa pun yang
 * boleh *melihat* daftarnya ikut membuat, menyunting, dan menghapus isinya.
 *
 * Itu bukan kemungkinan teoretis di proyek ini. Peran `head_msc` hanya memegang
 * `roles.view`, sehingga sebelum perbaikan ini ia dapat menyunting peran mana
 * pun — termasuk memberi dirinya sendiri seluruh izin. Peran `department` hanya
 * memegang `announcements.view`, tetapi dapat menyunting dan menghapus
 * pengumuman yang tampil di halaman publik.
 *
 * Trait ini memetakan tiap aksi ke izin `{awalan}.{aksi}`, sehingga izin yang
 * tidak diberikan berarti aksinya benar-benar tertutup.
 *
 * Pemetaannya ditaruh di getAuthorizationResponse(), satu-satunya jalur yang
 * dilalui halaman maupun tombol aksi bawaan Filament. Trait ini dulu hanya
 * menimpa canDelete() dan kawan-kawannya. Halaman Ubah dan Buat memang
 * memanggilnya, tetapi tombol Hapus di tabel, hapus massal, dan tombol Hapus
 * di halaman Ubah langsung meminta getDeleteAuthorizationResponse(). head_msc
 * karena itu tetap bisa menghapus peran dari tabelnya, dan staf MSC menghapus
 * pengumuman publik. Modul sertifikat memakai pola yang sama lewat
 * [AuthorizesCertificateModule].
 */
trait AuthorizesByPermission
{
    /**
     * Awalan izin resource ini, misalnya `users` untuk `users.view`.
     */
    abstract protected static function permissionPrefix(): string;

    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        $kemampuan = PanelAbility::forAction($action);

        return $kemampuan !== null && (auth()->user()?->can(static::permissionPrefix().'.'.$kemampuan) ?? false)
            ? Response::allow()
            : Response::deny();
    }
}
