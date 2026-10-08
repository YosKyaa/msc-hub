<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin melihat jejak audit, untuk server yang sudah berjalan.
 *
 * RoleSeeder memakai syncPermissions, yang menimpa izin setiap peran dengan
 * bawaannya: menjalankannya di server membatalkan setiap perubahan izin yang
 * pernah dibuat admin lewat panel. Migrasi ini hanya menambah — izin yang
 * sudah ada pada setiap peran tidak disentuh.
 */
return new class extends Migration
{
    private const IZIN = 'activity_log.view';

    private const PERAN = ['admin', 'head_msc'];

    public function up(): void
    {
        $izin = Permission::firstOrCreate(['name' => self::IZIN, 'guard_name' => 'web']);

        foreach (Role::whereIn('name', self::PERAN)->where('guard_name', 'web')->get() as $peran) {
            $peran->givePermissionTo($izin);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::IZIN)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
