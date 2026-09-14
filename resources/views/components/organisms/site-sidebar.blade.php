@props([
    'tone' => 'indigo',
])

@php
    $requester = session('requester');

    // Satu daftar navigasi untuk seluruh portal peminjam, dipakai sisi kiri
    // maupun laci di ponsel. Tiap menu membawa satu kalimat penjelas, karena
    // namanya saja belum tentu dimengerti orang yang baru pertama ke sini.
    $ringkasan = [
        'route' => 'requester.dashboard',
        'pattern' => 'requester.dashboard',
        'label' => 'Ringkasan',
        'description' => 'Semua pengajuan Anda dalam satu halaman.',
        'icon' => 'M4 6a2 2 0 0 1 2-2h4v7H4V6Zm0 9h6v5H6a2 2 0 0 1-2-2v-3Zm10 5h4a2 2 0 0 0 2-2v-6h-6v8Zm0-10h6V6a2 2 0 0 0-2-2h-4v4Z',
    ];

    $ajukan = [
        [
            'route' => 'request.content',
            'pattern' => 'request.content',
            'label' => 'Ajukan Konten',
            'description' => 'Minta dibuatkan foto, video, atau desain.',
            'icon' => 'M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586Z',
        ],
        [
            'route' => 'booking.room',
            'pattern' => 'booking.room*',
            'label' => 'Booking Ruangan',
            'description' => 'Pinjam studio atau ruang rapat MSC.',
            'icon' => 'M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4',
        ],
        [
            'route' => 'booking.inventory',
            'pattern' => 'booking.inventory*',
            'label' => 'Pinjam Alat',
            'description' => 'Kamera, lighting, audio, dan lainnya.',
            'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        ],
    ];

    // Hanya berisi setelah masuk, jadi disembunyikan selama belum masuk
    // ketimbang diam-diam melempar peminjam ke halaman login begitu diklik.
    $pantau = [
        [
            'route' => 'request.status',
            'pattern' => 'request.status*',
            'label' => 'Konten Saya',
            'description' => 'Sudah sampai mana permintaan konten Anda.',
            'icon' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4',
        ],
        [
            'route' => 'my.bookings',
            'pattern' => 'my.bookings*',
            'label' => 'Riwayat Booking',
            'description' => 'Ruangan dan alat yang pernah Anda pinjam.',
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        ],
    ];
@endphp

{{-- Isi sidebar dirender dua kali: menetap di kiri pada layar lebar, dan
     sebagai laci yang bisa ditarik di ponsel. Datanya satu, wadahnya dua —
     memaksakan satu elemen untuk keduanya membuat kelas transform saling
     menimpa dan hasilnya tidak bisa diandalkan. --}}
<aside class="hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-72 lg:shrink-0 lg:flex-col lg:border-r lg:border-gray-200 lg:bg-white">
    <a href="{{ route('landing') }}" class="flex items-center gap-2.5 border-b border-gray-100 px-5 py-4">
        <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="h-9 w-auto">
        <span>
            <span class="block text-sm font-semibold text-gray-900">MSC Hub</span>
            <span class="block text-xs text-gray-500">Layanan Media &amp; Peminjaman</span>
        </span>
    </a>

    <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Navigasi utama">
        @if ($requester)
            <x-molecules.nav-link :tone="$tone" :route="$ringkasan['route']" :pattern="$ringkasan['pattern']"
                :label="$ringkasan['label']" :description="$ringkasan['description']" :icon="$ringkasan['icon']" />
            <div class="my-3 border-t border-gray-100"></div>
        @endif

        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Buat Pengajuan</p>
        @foreach ($ajukan as $item)
            <x-molecules.nav-link :tone="$tone" :route="$item['route']" :pattern="$item['pattern']"
                :label="$item['label']" :description="$item['description']" :icon="$item['icon']" />
        @endforeach

        @if ($requester)
            <p class="mt-4 px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Pantau Pengajuan</p>
            @foreach ($pantau as $item)
                <x-molecules.nav-link :tone="$tone" :route="$item['route']" :pattern="$item['pattern']"
                    :label="$item['label']" :description="$item['description']" :icon="$item['icon']" />
            @endforeach
        @endif
    </nav>

    <div class="border-t border-gray-100 p-3">
        @if ($requester)
            <x-molecules.account-menu :requester="$requester" :tone="$tone" />
        @else
            <p class="px-2 pb-3 text-xs leading-relaxed text-gray-500">
                Masuk dengan akun kampus untuk mengajukan dan memantau permintaan Anda.
            </p>
            <x-molecules.sign-in-button :tone="$tone" class="w-full justify-center" />
        @endif
    </div>
</aside>

{{-- Laci untuk layar sempit --}}
<div x-show="menuOpen" x-cloak class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden"
     x-transition.opacity @click="menuOpen = false" aria-hidden="true"></div>

<div x-show="menuOpen" x-cloak id="menu-utama"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="-translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="-translate-x-full"
     class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col bg-white shadow-xl lg:hidden"
     role="dialog" aria-modal="true" aria-label="Menu">
    <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3">
        <a href="{{ route('landing') }}" class="flex min-w-0 items-center gap-2.5">
            <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="h-8 w-auto">
            <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-gray-900">MSC Hub</span>
                <span class="block truncate text-xs text-gray-500">Layanan Media &amp; Peminjaman</span>
            </span>
        </a>
        <button type="button" @click="menuOpen = false" aria-label="Tutup menu"
            class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-50 hover:text-gray-900">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Navigasi utama">
        @if ($requester)
            <div class="mb-3 flex items-center gap-3 rounded-xl bg-gray-50 p-3">
                <x-atoms.avatar :name="$requester['name']" :tone="$tone" />
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900">{{ $requester['name'] }}</p>
                    <p class="truncate text-xs text-gray-500">{{ $requester['email'] }}</p>
                </div>
            </div>

            <x-molecules.nav-link :tone="$tone" :route="$ringkasan['route']" :pattern="$ringkasan['pattern']"
                :label="$ringkasan['label']" :description="$ringkasan['description']" :icon="$ringkasan['icon']" />
            <div class="my-3 border-t border-gray-100"></div>
        @endif

        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Buat Pengajuan</p>
        @foreach ($ajukan as $item)
            <x-molecules.nav-link :tone="$tone" :route="$item['route']" :pattern="$item['pattern']"
                :label="$item['label']" :description="$item['description']" :icon="$item['icon']" />
        @endforeach

        @if ($requester)
            <p class="mt-4 px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Pantau Pengajuan</p>
            @foreach ($pantau as $item)
                <x-molecules.nav-link :tone="$tone" :route="$item['route']" :pattern="$item['pattern']"
                    :label="$item['label']" :description="$item['description']" :icon="$item['icon']" />
            @endforeach

            <form action="{{ route('auth.google.logout') }}" method="POST" class="mt-4 border-t border-gray-100 pt-3">
                @csrf
                <input type="hidden" name="redirect" value="{{ route('landing') }}">
                <button type="submit" class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5m5 5H3"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/></svg>
                    Keluar dari akun
                </button>
            </form>
        @else
            <div class="mt-4 border-t border-gray-100 pt-4">
                <p class="px-2 pb-3 text-xs leading-relaxed text-gray-500">
                    Masuk dengan akun kampus untuk mengajukan dan memantau permintaan Anda.
                </p>
                <x-molecules.sign-in-button :tone="$tone" class="w-full justify-center" />
            </div>
        @endif
    </nav>
</div>
