<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions by module
        $permissions = [
            // Panel Access
            'panel.access',

            // Dashboard
            'dashboard.view',

            // Users Management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Roles & Permissions Management
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',

            // Assets Management
            'assets.view',
            'assets.create',
            'assets.edit',
            'assets.delete',

            // Projects Management
            'projects.view',
            'projects.create',
            'projects.edit',
            'projects.delete',

            // Content Requests Management
            'content_requests.view',
            'content_requests.create',
            'content_requests.edit',
            'content_requests.delete',
            'content_requests.approve_staff',
            'content_requests.approve_head',

            // Inventory Management
            'inventory.view',
            'inventory.create',
            'inventory.edit',
            'inventory.delete',

            // Inventory Bookings
            'inventory_bookings.view',
            'inventory_bookings.create',
            'inventory_bookings.edit',
            'inventory_bookings.delete',
            'inventory_bookings.approve',

            // Room Management
            'rooms.view',
            'rooms.create',
            'rooms.edit',
            'rooms.delete',

            // Room Bookings
            'room_bookings.view',
            'room_bookings.create',
            'room_bookings.edit',
            'room_bookings.delete',
            'room_bookings.approve',

            // Announcements Management
            'announcements.view',
            'announcements.create',
            'announcements.edit',
            'announcements.delete',

            // Featured Works Management
            'featured_works.view',
            'featured_works.create',
            'featured_works.edit',
            'featured_works.delete',

            // Tags Management
            'tags.view',
            'tags.create',
            'tags.edit',
            'tags.delete',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        // Define roles with their permissions
        $roles = [
            'admin' => $permissions, // Admin gets all permissions
            'head_msc' => [
                'panel.access',
                'dashboard.view',
                'users.view',
                'roles.view',
                'assets.view', 'assets.create', 'assets.edit', 'assets.delete',
                'projects.view', 'projects.create', 'projects.edit', 'projects.delete',
                'content_requests.view', 'content_requests.edit', 'content_requests.approve_staff', 'content_requests.approve_head',
                'inventory.view', 'inventory.create', 'inventory.edit',
                'inventory_bookings.view', 'inventory_bookings.edit', 'inventory_bookings.approve',
                'rooms.view', 'rooms.create', 'rooms.edit',
                'room_bookings.view', 'room_bookings.edit', 'room_bookings.approve',
                'announcements.view', 'announcements.create', 'announcements.edit', 'announcements.delete',
                'featured_works.view', 'featured_works.create', 'featured_works.edit', 'featured_works.delete',
                'tags.view', 'tags.create', 'tags.edit',
            ],
            'staff_msc' => [
                'panel.access',
                'dashboard.view',
                'assets.view', 'assets.create', 'assets.edit',
                'projects.view', 'projects.create', 'projects.edit',
                'content_requests.view', 'content_requests.edit', 'content_requests.approve_staff',
                'inventory.view',
                'inventory_bookings.view', 'inventory_bookings.edit',
                'rooms.view',
                'room_bookings.view', 'room_bookings.edit',
                'announcements.view', 'announcements.create', 'announcements.edit',
                'featured_works.view', 'featured_works.create', 'featured_works.edit',
                'tags.view', 'tags.create',
            ],
            'department' => [
                'panel.access',
                'dashboard.view',
                'content_requests.view', 'content_requests.create',
                'inventory_bookings.view',
                'room_bookings.view',
                'announcements.view',
            ],
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web']
            );
            $role->syncPermissions($rolePermissions);
        }
    }
}
