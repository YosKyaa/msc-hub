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
                'password' => Hash::make('Admin@msc123'),
            ]
        );
        $admin->assignRole('admin');

        $head = User::firstOrCreate(
            ['email' => 'hadi@jgu.ac.id'],
            [
                'name' => 'Head MSC',
                'password' => Hash::make('Head@msc123'),
            ]
        );
        $head->assignRole('head_msc');

        $staff = User::firstOrCreate(
            ['email' => 'chika@jgu.ac.id'],
            [
                'name' => 'Staff MSC',
                'password' => Hash::make('Staff@msc123'),
            ]
        );
        $staff->assignRole('staff_msc');

             $staff = User::firstOrCreate(
            ['email' => 'yosua@jgu.ac.id'],
            [
                'name' => 'Staff MSC',
                'password' => Hash::make('Staff@msc123'),
            ]
        );
        $staff->assignRole('staff_msc');
    }
}
