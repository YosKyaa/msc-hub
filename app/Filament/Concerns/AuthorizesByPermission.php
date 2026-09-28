<?php

namespace App\Filament\Concerns;

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
 * tidak diberikan berarti aksinya benar-benar tertutup. Modul sertifikat
 * memakai pola yang sama lewat [AuthorizesCertificateModule].
 */
trait AuthorizesByPermission
{
    /**
     * Awalan izin resource ini, misalnya `users` untuk `users.view`.
     */
    abstract protected static function permissionPrefix(): string;

    protected static function allowsAction(string $action): bool
    {
        return auth()->user()?->can(static::permissionPrefix().'.'.$action) ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::allowsAction('view');
    }

    public static function canView(Model $record): bool
    {
        return static::allowsAction('view');
    }

    public static function canCreate(): bool
    {
        return static::allowsAction('create');
    }

    /**
     * Menggandakan berarti membuat catatan baru, jadi izinnya izin membuat —
     * bukan izin menyunting yang kebetulan dipakai untuk membukanya.
     */
    public static function canReplicate(Model $record): bool
    {
        return static::allowsAction('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowsAction('edit');
    }

    public static function canReorder(): bool
    {
        return static::allowsAction('edit');
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowsAction('delete');
    }

    /**
     * Hapus massal paling mudah terlewat: tombolnya tersembunyi di menu aksi
     * terpilih, dan tanpa penjaga tersendiri ia lolos meski tombol hapus per
     * baris sudah tertutup.
     */
    public static function canDeleteAny(): bool
    {
        return static::allowsAction('delete');
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::allowsAction('delete');
    }

    public static function canForceDeleteAny(): bool
    {
        return static::allowsAction('delete');
    }

    public static function canRestore(Model $record): bool
    {
        return static::allowsAction('delete');
    }

    public static function canRestoreAny(): bool
    {
        return static::allowsAction('delete');
    }
}
