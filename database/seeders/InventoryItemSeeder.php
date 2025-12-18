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
            // Kamera
            ['code' => 'CAM-001', 'name' => 'Canon EOS R5', 'category' => InventoryCategory::CAMERA, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'CAM-002', 'name' => 'Sony A7 IV', 'category' => InventoryCategory::CAMERA, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'CAM-003', 'name' => 'Canon EOS 90D', 'category' => InventoryCategory::CAMERA, 'condition_status' => InventoryCondition::GOOD],
            
            // Lensa
            ['code' => 'LNS-001', 'name' => 'Canon RF 24-70mm f/2.8', 'category' => InventoryCategory::LENS, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'LNS-002', 'name' => 'Canon RF 70-200mm f/2.8', 'category' => InventoryCategory::LENS, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'LNS-003', 'name' => 'Sony FE 50mm f/1.4', 'category' => InventoryCategory::LENS, 'condition_status' => InventoryCondition::GOOD],
            
            // Mikrofon
            ['code' => 'MIC-001', 'name' => 'Rode VideoMic Pro+', 'category' => InventoryCategory::MICROPHONE, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'MIC-002', 'name' => 'Rode Wireless GO II', 'category' => InventoryCategory::MICROPHONE, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'MIC-003', 'name' => 'Shure SM7B', 'category' => InventoryCategory::MICROPHONE, 'condition_status' => InventoryCondition::GOOD],
            
            // Tripod
            ['code' => 'TRI-001', 'name' => 'Manfrotto MT055CXPRO4', 'category' => InventoryCategory::TRIPOD, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'TRI-002', 'name' => 'Benro S8 Video Head', 'category' => InventoryCategory::TRIPOD, 'condition_status' => InventoryCondition::GOOD],
            
            // Lighting
            ['code' => 'LGT-001', 'name' => 'Godox SL-60W', 'category' => InventoryCategory::LIGHTING, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'LGT-002', 'name' => 'Aputure 120D II', 'category' => InventoryCategory::LIGHTING, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'LGT-003', 'name' => 'Godox TL60 RGB Tube', 'category' => InventoryCategory::LIGHTING, 'condition_status' => InventoryCondition::GOOD],
            
            // Audio
            ['code' => 'AUD-001', 'name' => 'Zoom H6 Recorder', 'category' => InventoryCategory::AUDIO, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'AUD-002', 'name' => 'Behringer UMC404HD', 'category' => InventoryCategory::AUDIO, 'condition_status' => InventoryCondition::GOOD],
            
            // Video
            ['code' => 'VID-001', 'name' => 'Blackmagic ATEM Mini Pro', 'category' => InventoryCategory::VIDEO, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'VID-002', 'name' => 'Elgato Cam Link 4K', 'category' => InventoryCategory::VIDEO, 'condition_status' => InventoryCondition::GOOD],
            
            // Proyektor
            ['code' => 'PRJ-001', 'name' => 'Epson EB-X51', 'category' => InventoryCategory::PROJECTOR, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'PRJ-002', 'name' => 'BenQ MH733', 'category' => InventoryCategory::PROJECTOR, 'condition_status' => InventoryCondition::GOOD],
            
            // Komputer
            ['code' => 'PC-001', 'name' => 'MacBook Pro 16"', 'category' => InventoryCategory::COMPUTER, 'condition_status' => InventoryCondition::GOOD],
            ['code' => 'PC-002', 'name' => 'iMac 27" 5K', 'category' => InventoryCategory::COMPUTER, 'condition_status' => InventoryCondition::GOOD],
        ];

        foreach ($items as $item) {
            InventoryItem::firstOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'condition_status' => $item['condition_status'],
                    'is_active' => true,
                ]
            );
        }
    }
}
