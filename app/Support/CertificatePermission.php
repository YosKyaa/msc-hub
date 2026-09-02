<?php

namespace App\Support;

/**
 * Satu tempat pemetaan aksi modul sertifikat ke permission Spatie.
 *
 * Nama permission mengikuti yang terdaftar di RoleSeeder:
 * certificates.view / create / edit / delete / publish.
 */
class CertificatePermission
{
    /** Aksi yang mengubah status penerbitan: terbitkan, cabut, publikasi, kirim email. */
    public const ISSUE = 'publish';

    public static function allows(string $ability): bool
    {
        return auth()->user()?->can("certificates.{$ability}") ?? false;
    }

    public static function allowsIssuing(): bool
    {
        return static::allows(self::ISSUE);
    }
}
