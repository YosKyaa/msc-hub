<?php

namespace App\Support;

/**
 * Satu tempat pemetaan aksi bawaan Filament ke kemampuan izin panel: view,
 * create, edit, atau delete.
 *
 * Dipakai AuthorizesByPermission dan AuthorizesCertificateModule. Aksi yang
 * tidak dikenal dijawab null, dan pemanggilnya menutup aksi itu, bukan
 * membukanya.
 */
final class PanelAbility
{
    public static function forAction(string $action): ?string
    {
        return match ($action) {
            'viewAny', 'view' => 'view',
            // Menggandakan berarti membuat catatan baru.
            'create', 'replicate' => 'create',
            'update', 'reorder' => 'edit',
            'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny' => 'delete',
            default => null,
        };
    }
}
