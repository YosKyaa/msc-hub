@props([
    // Aksen mengikuti warna halaman yang memakainya.
    'tone' => 'indigo',
])

@php
    $requester = session('requester');

    // Satu definisi navigasi untuk seluruh halaman peminjam. Sebelumnya tiap
    // layout punya daftarnya sendiri, sehingga menunya berubah susunan ketika
    // peminjam berpindah antara pengajuan konten dan peminjaman.
    $layanan = [
        ['route' => 'request.content', 'pattern' => 'request.content', 'label' => 'Ajukan Konten'],
        ['route' => 'booking.inventory', 'pattern' => 'booking.inventory*', 'label' => 'Pinjam Alat'],
        ['route' => 'booking.room', 'pattern' => 'booking.room*', 'label' => 'Booking Ruangan'],
    ];

    // Hanya berguna setelah masuk, jadi disembunyikan selama belum masuk
    // ketimbang diam-diam melempar peminjam ke Google begitu diklik.
    $riwayat = [
        ['route' => 'request.status', 'pattern' => 'request.status*', 'label' => 'Konten Saya'],
        ['route' => 'my.bookings', 'pattern' => 'my.bookings*', 'label' => 'Riwayat Booking'],
    ];

    $aktif = fn (array $item) => request()->routeIs($item['pattern']);

    // Ditulis utuh, bukan dirangkai dari $tone: kelas yang dibentuk saat
    // berjalan hanya selamat selama halaman memakai Tailwind Play CDN.
    $sorot = $tone === 'blue'
        ? 'bg-blue-50 text-blue-700 font-medium'
        : 'bg-indigo-50 text-indigo-700 font-medium';

    $tautan = fn (array $item) => $aktif($item)
        ? $sorot
        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900';
@endphp

<header class="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur" x-data="{ mobileMenuOpen: false }">
    <div class="mx-auto max-w-6xl px-4">
        <div class="flex items-center justify-between gap-4 py-3">
            {{-- Logo mengarah ke beranda, menggantikan tautan "Beranda" yang
                 dulu memakai panah mundur dan terbaca seperti tombol browser. --}}
            <a href="{{ route('landing') }}" class="flex shrink-0 items-center gap-2.5 rounded-lg p-1 focus:outline-none focus:ring-2 focus:ring-gray-300">
                <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="h-8 w-auto">
                <span class="hidden sm:block">
                    <span class="block text-sm font-semibold text-gray-900">MSC Hub</span>
                    <span class="block text-xs text-gray-500">Layanan Media &amp; Peminjaman</span>
                </span>
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
                @foreach ($layanan as $item)
                    <a href="{{ route($item['route']) }}"
                       @if ($aktif($item)) aria-current="page" @endif
                       class="rounded-lg px-3 py-2 text-sm transition {{ $tautan($item) }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach

                @if ($requester)
                    <span class="mx-2 h-5 w-px bg-gray-200" aria-hidden="true"></span>

                    @foreach ($riwayat as $item)
                        <a href="{{ route($item['route']) }}"
                           @if ($aktif($item)) aria-current="page" @endif
                           class="rounded-lg px-3 py-2 text-sm transition {{ $tautan($item) }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach

                    <span class="ml-2 border-l border-gray-200 pl-2">
                        <x-molecules.account-menu :requester="$requester" :tone="$tone" />
                    </span>
                @else
                    <span class="ml-3">
                        <x-molecules.sign-in-button :tone="$tone" />
                    </span>
                @endif
            </nav>

            <div class="flex items-center gap-2 lg:hidden">
                @unless ($requester)
                    <x-molecules.sign-in-button :tone="$tone" compact />
                @endunless

                <button type="button" @click="mobileMenuOpen = ! mobileMenuOpen"
                    :aria-expanded="mobileMenuOpen" aria-controls="menu-utama"
                    class="rounded-lg p-2 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                    aria-label="Buka menu">
                    <svg x-show="! mobileMenuOpen" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileMenuOpen" x-cloak class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <div id="menu-utama" x-show="mobileMenuOpen" x-cloak x-transition class="border-t border-gray-100 py-3 lg:hidden">
            @if ($requester)
                <div class="mb-2 flex items-center gap-3 rounded-xl bg-gray-50 p-3">
                    <x-atoms.avatar :name="$requester['name']" :tone="$tone" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $requester['name'] }}</p>
                        <p class="truncate text-xs text-gray-500">{{ $requester['email'] }}</p>
                    </div>
                </div>
            @endif

            <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Ajukan</p>
            @foreach ($layanan as $item)
                <a href="{{ route($item['route']) }}"
                   @if ($aktif($item)) aria-current="page" @endif
                   class="block rounded-lg px-3 py-2 text-sm {{ $tautan($item) }}">
                    {{ $item['label'] }}
                </a>
            @endforeach

            @if ($requester)
                <p class="px-3 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Milik Saya</p>
                @foreach ($riwayat as $item)
                    <a href="{{ route($item['route']) }}"
                       @if ($aktif($item)) aria-current="page" @endif
                       class="block rounded-lg px-3 py-2 text-sm {{ $tautan($item) }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach

                <form action="{{ route('auth.google.logout') }}" method="POST" class="mt-3 border-t border-gray-100 pt-3">
                    @csrf
                    <input type="hidden" name="redirect" value="{{ route('landing') }}">
                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/></svg>
                        Keluar dari akun
                    </button>
                </form>
            @endif
        </div>
    </div>
</header>
