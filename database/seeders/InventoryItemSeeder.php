<?php

namespace Database\Seeders;

use App\Enums\InventoryCategory;
use App\Enums\InventoryCondition;
use App\Models\InventoryItem;
use Illuminate\Database\Seeder;

class InventoryItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Cameras
            ['code' => 'CAM-001', 'name' => 'Canon EOS 80D', 'category' => InventoryCategory::CAMERA, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'CAM-002', 'name' => 'Sony A7III', 'category' => InventoryCategory::CAMERA, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'CAM-003', 'name' => 'Canon EOS R', 'category' => InventoryCategory::CAMERA, 'condition_status' => InventoryCondition::MINOR_ISSUE, 'notes' => 'Grip sedikit aus'],

            // Lenses
            ['code' => 'LEN-001', 'name' => 'Canon 50mm f/1.8', 'category' => InventoryCategory::LENS, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'LEN-002', 'name' => 'Canon 24-70mm f/2.8', 'category' => InventoryCategory::LENS, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'LEN-003', 'name' => 'Sony 85mm f/1.4', 'category' => InventoryCategory::LENS, 'condition_status' => InventoryCondition::GOOD],

            // Tripods
            ['code' => 'TRI-001', 'name' => 'Manfrotto MT055', 'category' => InventoryCategory::TRIPOD, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'TRI-002', 'name' => 'Sirui T-2205X', 'category' => InventoryCategory::TRIPOD, 'condition_status' => InventoryCondition::GOOD],

            // Microphones
            ['code' => 'MIC-001', 'name' => 'Rode VideoMic Pro+', 'category' => InventoryCategory::MICROPHONE, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'MIC-002', 'name' => 'Sennheiser MKE 400', 'category' => InventoryCategory::MICROPHONE, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'MIC-003', 'name' => 'Blue Yeti USB', 'category' => InventoryCategory::MICROPHONE, 'condition_status' => InventoryCondition::GOOD],

            // Lighting
            ['code' => 'LGT-001', 'name' => 'Godox SL-60W', 'category' => InventoryCategory::LIGHTING, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'LGT-002', 'name' => 'Aputure AL-MC', 'category' => InventoryCategory::LIGHTING, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'LGT-003', 'name' => 'Ring Light 18"', 'category' => InventoryCategory::LIGHTING, 'condition_status' => InventoryCondition::MAINTENANCE, 'notes' => 'Dimmer perlu diperbaiki'],

            // Video
            ['code' => 'VID-001', 'name' => 'DJI Pocket 2', 'category' => InventoryCategory::VIDEO, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'VID-002', 'name' => 'GoPro Hero 10', 'category' => InventoryCategory::VIDEO, 'condition_status' => InventoryCondition::GOOD],

            // Audio
            ['code' => 'AUD-001', 'name' => 'Zoom H6 Recorder', 'category' => InventoryCategory::AUDIO, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'AUD-002', 'name' => 'Tascam DR-40X', 'category' => InventoryCategory::AUDIO, 'condition_status' => InventoryCondition::GOOD],

            // Projector
            ['code' => 'PRO-001', 'name' => 'Epson EB-X51', 'category' => InventoryCategory::PROJECTOR, 'condition_status' => InventoryCondition::GOOD],

            // Computer
            ['code' => 'COM-001', 'name' => 'MacBook Pro 14" M1', 'category' => InventoryCategory::COMPUTER, 'condition_status' => InventoryCondition::GOOD],
        ];

        foreach ($items as $item) {
            InventoryItem::firstOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'condition_status' => $item['condition_status'],
                    'notes' => $item['notes'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
