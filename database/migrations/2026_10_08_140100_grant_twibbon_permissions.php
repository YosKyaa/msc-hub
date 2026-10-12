<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin twibbon untuk server yang sudah berjalan, hanya menambah.
 *
 * RoleSeeder memakai syncPermissions, yang menimpa setiap perubahan izin yang
 * pernah dibuat admin lewat panel, jadi izin baru masuk lewat sini.
 */
return new class extends Migration
{
    private const PERAN = [
        'admin' => ['twibbons.view', 'twibbons.create', 'twibbons.edit', 'twibbons.delete'],
        'head_msc' => ['twibbons.view', 'twibbons.create', 'twibbons.edit', 'twibbons.delete'],
        'staff_msc' => ['twibbons.view', 'twibbons.create', 'twibbons.edit'],
    ];

    public function up(): void
    {
        foreach (['twibbons.view', 'twibbons.create', 'twibbons.edit', 'twibbons.delete'] as $nama) {
            Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }

        foreach (self::PERAN as $peran => $izin) {
            Role::where('name', $peran)->where('guard_name', 'web')->first()?->givePermissionTo($izin);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'twibbons.%')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
