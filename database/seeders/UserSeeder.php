<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@msc.jgu.ac.id'],
            [
                'name' => 'Admin MSC',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole('admin');

        $head = User::firstOrCreate(
            ['email' => 'head@msc.jgu.ac.id'],
            [
                'name' => 'Head MSC',
                'password' => Hash::make('password'),
            ]
        );
        $head->assignRole('head_msc');

        $staff = User::firstOrCreate(
            ['email' => 'staff@msc.jgu.ac.id'],
            [
                'name' => 'Staff MSC',
                'password' => Hash::make('password'),
            ]
        );
        $staff->assignRole('staff_msc');
    }
}
