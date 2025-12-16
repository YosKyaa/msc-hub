<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        Room::firstOrCreate(
            ['name' => 'Ruang Multimedia MSC'],
            [
                'location' => 'Studio Multimedia Lt. 2',
                'open_time' => '08:00',
                'close_time' => '16:00',
                'capacity' => 10,
                'facilities' => 'Proyektor, Sound System, Whiteboard, AC',
                'is_active' => true,
            ]
        );
    }
}
