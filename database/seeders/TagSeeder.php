<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'Wisuda',
            'Dies Natalis',
            'Orientation',
            'Seminar',
            'Workshop',
            'Lomba',
            'Pengabdian',
            'Penelitian',
            'Berita',
            'Pengumuman',
            'Event',
            'Akademik',
            'Kemahasiswaan',
            'Kerjasama',
            'Prestasi',
            'Fakultas Teknik',
            'Fakultas Ekonomi',
            'Fakultas Hukum',
            'Fakultas MIPA',
            'Rektorat',
        ];

        foreach ($tags as $tagName) {
            Tag::firstOrCreate(['name' => $tagName]);
        }
    }
}
