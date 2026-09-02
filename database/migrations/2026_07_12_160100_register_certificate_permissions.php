<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    private array $permissions = ['certificates.view', 'certificates.create', 'certificates.edit', 'certificates.delete', 'certificates.publish'];

    public function up(): void
    {
        foreach ($this->permissions as $name) Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        Role::where('name', 'admin')->first()?->givePermissionTo($this->permissions);
        Role::where('name', 'head_msc')->first()?->givePermissionTo($this->permissions);
        Role::where('name', 'staff_msc')->first()?->givePermissionTo(['certificates.view', 'certificates.create', 'certificates.edit', 'certificates.publish']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
