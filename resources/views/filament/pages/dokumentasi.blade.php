<x-filament-panels::page>
    <div class="space-y-6">
        
        {{-- Quick Start Card --}}
        <x-filament::section>
            <x-slot name="heading">
                <span class="text-xl">🚀 Quick Start</span>
            </x-slot>
            <x-slot name="description">Langkah cepat mulai mengarsip konten</x-slot>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <a href="{{ route('filament.admin.resources.projects.create') }}" 
                   class="block p-4 bg-primary-50 dark:bg-primary-950 border border-primary-200 dark:border-primary-800 rounded-lg text-center hover:bg-primary-100 dark:hover:bg-primary-900 transition">
                    <div class="text-2xl mb-1">📁</div>
                    <div class="font-medium text-sm text-gray-900 dark:text-white">1. Buat Project</div>
                    <div class="text-xs text-gray-500">Event/Kegiatan</div>
                </a>
                <a href="{{ route('filament.admin.resources.assets.create') }}" 
                   class="block p-4 bg-success-50 dark:bg-success-950 border border-success-200 dark:border-success-800 rounded-lg text-center hover:bg-success-100 dark:hover:bg-success-900 transition">
                    <div class="text-2xl mb-1">📦</div>
                    <div class="font-medium text-sm text-gray-900 dark:text-white">2. Tambah Asset</div>
                    <div class="text-xs text-gray-500">Foto/Video/Desain</div>
                </a>
                <div class="p-4 bg-warning-50 dark:bg-warning-950 border border-warning-200 dark:border-warning-800 rounded-lg text-center">
                    <div class="text-2xl mb-1">🔗</div>
                    <div class="font-medium text-sm text-gray-900 dark:text-white">3. Isi Link</div>
                    <div class="text-xs text-gray-500">Source & Output</div>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-center">
                    <div class="text-2xl mb-1">✅</div>
                    <div class="font-medium text-sm text-gray-900 dark:text-white">4. Selesai!</div>
                    <div class="text-xs text-gray-500">Arsip tersimpan</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Menu Utama --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Projects --}}
            <x-filament::section>
                <x-slot name="heading">
                    <span class="flex items-center gap-2">
                        <span class="text-lg">📁</span>
                        Projects
                    </span>
                </x-slot>
                <div class="space-y-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Kelompokkan aset berdasarkan event atau kegiatan.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-1">
                        <li>• Grup aset per event</li>
                        <li>• Info: judul, unit, tanggal</li>
                        <li>• Tambahkan tags</li>
                    </ul>
                    <div class="pt-2">
                        <x-filament::link href="{{ route('filament.admin.resources.projects.index') }}">
                            Buka Projects →
                        </x-filament::link>
                    </div>
                </div>
            </x-filament::section>

            {{-- Assets --}}
            <x-filament::section>
                <x-slot name="heading">
                    <span class="flex items-center gap-2">
                        <span class="text-lg">📦</span>
                        Assets
                    </span>
                </x-slot>
                <div class="space-y-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Arsip foto, video, desain, dan konten lainnya.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-1">
                        <li>• Link Drive/Figma/Canva</li>
                        <li>• Link output IG/YT/Web</li>
                        <li>• Filter tipe, platform, tahun</li>
                    </ul>
                    <div class="pt-2">
                        <x-filament::link href="{{ route('filament.admin.resources.assets.index') }}" color="success">
                            Buka Assets →
                        </x-filament::link>
                    </div>
                </div>
            </x-filament::section>

            {{-- Tags --}}
            <x-filament::section>
                <x-slot name="heading">
                    <span class="flex items-center gap-2">
                        <span class="text-lg">🏷️</span>
                        Tags
                    </span>
                </x-slot>
                <div class="space-y-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Kategorikan aset dan project dengan label.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-1">
                        <li>• Tag event rutin</li>
                        <li>• Tag fakultas/unit</li>
                        <li>• Buat saat input data</li>
                    </ul>
                    <div class="pt-2">
                        <x-filament::link href="{{ route('filament.admin.resources.tags.index') }}" color="warning">
                            Buka Tags →
                        </x-filament::link>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Workflow --}}
        <x-filament::section>
            <x-slot name="heading">📋 Workflow Arsip Konten</x-slot>
            
            <div class="flex flex-wrap items-center justify-center gap-2 text-sm">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 rounded-full">
                    <span class="font-bold">1</span> Buat Project
                </span>
                <span class="text-gray-400">→</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 rounded-full">
                    <span class="font-bold">2</span> Upload ke Cloud
                </span>
                <span class="text-gray-400">→</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 rounded-full">
                    <span class="font-bold">3</span> Input Asset + Link Source
                </span>
                <span class="text-gray-400">→</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 rounded-full">
                    <span class="font-bold">4</span> Publish Konten
                </span>
                <span class="text-gray-400">→</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-success-100 dark:bg-success-900 text-success-700 dark:text-success-300 rounded-full">
                    <span class="font-bold">5</span> Update Link Output
                </span>
            </div>
        </x-filament::section>

        {{-- Two Column --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Tips --}}
            <x-filament::section>
                <x-slot name="heading">💡 Tips & Shortcuts</x-slot>
                
                <div class="space-y-3">
                    <div class="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-800 rounded">
                        <code class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-xs">Ctrl+K</code>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Pencarian global</span>
                    </div>
                    <div class="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-800 rounded">
                        <code class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-xs">☑️</code>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Bulk actions - ubah banyak sekaligus</span>
                    </div>
                    <div class="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-800 rounded">
                        <code class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-xs">⋮</code>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Menu aksi - duplikat, feature</span>
                    </div>
                    <div class="flex items-center gap-3 p-2 bg-warning-50 dark:bg-warning-900/30 rounded">
                        <code class="px-2 py-1 bg-warning-200 dark:bg-warning-800 rounded text-xs">⭐</code>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Featured - tandai karya terbaik</span>
                    </div>
                </div>
            </x-filament::section>

            {{-- Tipe & Platform --}}
            <x-filament::section>
                <x-slot name="heading">📊 Tipe & Platform</x-slot>
                
                <div class="space-y-4">
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Tipe Asset</div>
                        <div class="flex flex-wrap gap-1.5">
                            <x-filament::badge color="success">Foto</x-filament::badge>
                            <x-filament::badge color="danger">Video</x-filament::badge>
                            <x-filament::badge color="warning">Desain</x-filament::badge>
                            <x-filament::badge color="info">Banner</x-filament::badge>
                            <x-filament::badge color="gray">Dokumen</x-filament::badge>
                            <x-filament::badge color="primary">Post</x-filament::badge>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Platform</div>
                        <div class="flex flex-wrap gap-1.5">
                            <x-filament::badge color="gray">Instagram</x-filament::badge>
                            <x-filament::badge color="gray">YouTube</x-filament::badge>
                            <x-filament::badge color="gray">TikTok</x-filament::badge>
                            <x-filament::badge color="gray">Drive</x-filament::badge>
                            <x-filament::badge color="gray">Figma</x-filament::badge>
                            <x-filament::badge color="gray">Canva</x-filament::badge>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Hak Akses --}}
        <x-filament::section>
            <x-slot name="heading">🔐 Hak Akses</x-slot>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2 px-3 font-medium text-gray-900 dark:text-white">Role</th>
                            <th class="text-center py-2 px-3 font-medium text-gray-900 dark:text-white">Lihat</th>
                            <th class="text-center py-2 px-3 font-medium text-gray-900 dark:text-white">Tambah</th>
                            <th class="text-center py-2 px-3 font-medium text-gray-900 dark:text-white">Edit</th>
                            <th class="text-center py-2 px-3 font-medium text-gray-900 dark:text-white">Hapus</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 dark:text-gray-400">
                        <tr class="border-b dark:border-gray-700/50">
                            <td class="py-2 px-3">🔵 Staff MSC</td>
                            <td class="text-center py-2 px-3">✅</td>
                            <td class="text-center py-2 px-3">✅</td>
                            <td class="text-center py-2 px-3">✅</td>
                            <td class="text-center py-2 px-3">❌</td>
                        </tr>
                        <tr class="border-b dark:border-gray-700/50">
                            <td class="py-2 px-3">🟣 Head MSC</td>
                            <td class="text-center py-2 px-3">✅</td>
                            <td class="text-center py-2 px-3">✅</td>
                            <td class="text-center py-2 px-3">✅</td>
                            <td class="text-center py-2 px-3">❌</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3">🔴 Admin</td>
                            <td class="text-center py-2 px-3">✅</td>
                            <td class="text-center py-2 px-3">✅</td>
                            <td class="text-center py-2 px-3">✅</td>
                            <td class="text-center py-2 px-3">✅</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Footer --}}
        <div class="text-center text-sm text-gray-400 py-2">
            Asset Vault v1.0 — Media & Strategic Communications JGU
        </div>
    </div>
</x-filament-panels::page>
