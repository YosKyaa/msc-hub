<?php

namespace App\Listeners;

use App\Support\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Mencatat perubahan peran dan izin ke jejak audit.
 *
 * Peran dan izin disimpan di tabel penghubung, bukan di kolom model, jadi
 * perubahannya tidak tertangkap pencatat kolom biasa. Padahal inilah
 * perubahan yang paling perlu dipertanggungjawabkan: siapa yang memberi
 * seseorang hak menerbitkan sertifikat, atau mencabutnya.
 */
class RecordAccessChange
{
    public function handle(RoleAttached|RoleDetached|PermissionAttached|PermissionDetached $event): void
    {
        $peran = $event instanceof RoleAttached || $event instanceof RoleDetached;
        $diberikan = $event instanceof RoleAttached || $event instanceof PermissionAttached;

        $nama = $peran
            ? $this->names($event->rolesOrIds, Role::class)
            : $this->names($event->permissionsOrIds, Permission::class);

        if ($nama === []) {
            return;
        }

        $jenis = $peran ? 'peran' : 'izin';

        activity(AuditLog::NAME)
            ->performedOn($event->model)
            ->event($diberikan ? "{$jenis}_diberikan" : "{$jenis}_dicabut")
            ->withProperties([$diberikan ? 'diberikan' : 'dicabut' => $nama])
            ->log(($diberikan ? ucfirst($jenis).' diberikan: ' : ucfirst($jenis).' dicabut: ').implode(', ', $nama));
    }

    /**
     * Event menerima id, model, atau koleksi, tergantung jalur pemanggilnya.
     *
     * @param  class-string<Model>  $kelas
     * @return list<string>
     */
    private function names(mixed $daftar, string $kelas): array
    {
        $daftar = $daftar instanceof Collection ? $daftar->all() : (is_array($daftar) ? $daftar : [$daftar]);

        $nama = [];
        $id = [];

        foreach ($daftar as $item) {
            if ($item instanceof Model) {
                $nama[] = (string) $item->getAttribute('name');
            } elseif (is_scalar($item)) {
                $id[] = $item;
            }
        }

        if ($id !== []) {
            $nama = [...$nama, ...$kelas::query()->whereKey($id)->pluck('name')->all()];
        }

        return array_values(array_unique(array_filter($nama)));
    }
}
