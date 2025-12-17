<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pengumuman - MSC Hub JGU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col">
    {{-- Header --}}
    <header class="bg-white shadow-sm border-b sticky top-0 z-50" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex items-center justify-between py-3">
                <a href="{{ route('landing') }}" class="flex items-center gap-2">
                    <img src="{{ asset('img/jgu.png') }}" alt="JGU Logo" class="h-8 w-auto">
                    <div class="hidden sm:block">
                        <div class="font-bold text-gray-900">JGU &bull; MSC</div>
                        <div class="text-xs text-gray-500">Hub</div>
                    </div>
                </a>

                <nav class="hidden md:flex items-center gap-6">
                    <a href="{{ route('landing') }}" class="text-sm text-gray-600 hover:text-blue-600 transition">Beranda</a>
                    <a href="{{ route('request.content') }}" class="text-sm text-gray-600 hover:text-blue-600 transition">Ajukan Konten</a>
                    <a href="{{ route('booking.inventory') }}" class="text-sm text-gray-600 hover:text-blue-600 transition">Pinjam Inventaris</a>
                    <a href="{{ route('booking.room') }}" class="text-sm text-gray-600 hover:text-blue-600 transition">Booking Ruang</a>
                </nav>

                <div class="hidden md:flex items-center gap-3">
                    @if($requester)
                        <div class="flex items-center gap-2">
                            @if($requester['avatar'] ?? null)
                                <img src="{{ $requester['avatar'] }}" alt="" class="w-8 h-8 rounded-full">
                            @else
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-blue-600 text-sm font-medium">{{ substr($requester['name'], 0, 1) }}</span>
                                </div>
                            @endif
                            <span class="text-sm text-gray-700">{{ $requester['name'] }}</span>
                            <form action="{{ route('auth.google.logout') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-500">Logout</button>
                            </form>
                        </div>
                    @else
                        <a href="{{ route('auth.google.redirect') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Masuk Google
                        </a>
                    @endif
                </div>

                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-gray-600 hover:text-gray-900">
                    <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div x-show="mobileMenuOpen" x-cloak x-transition class="md:hidden border-t py-3 space-y-1">
                <a href="{{ route('landing') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Beranda</a>
                <a href="{{ route('request.content') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Ajukan Konten</a>
                <a href="{{ route('booking.inventory') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Pinjam Inventaris</a>
                <a href="{{ route('booking.room') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Booking Ruang</a>
            </div>
        </div>
    </header>

    <main class="flex-grow py-8">
        <div class="max-w-4xl mx-auto px-4">
            {{-- Breadcrumb --}}
            <nav class="mb-6">
                <ol class="flex items-center gap-2 text-sm text-gray-500">
                    <li><a href="{{ route('landing') }}" class="hover:text-blue-600">Beranda</a></li>
                    <li><span class="text-gray-300">/</span></li>
                    <li class="text-gray-900 font-medium">Pengumuman</li>
                </ol>
            </nav>

            <h1 class="text-3xl font-bold text-gray-900 mb-8">Pengumuman</h1>

            {{-- Pinned Announcements --}}
            @if($pinned->isNotEmpty())
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/></svg>
                        Disematkan
                    </h2>
                    <div class="space-y-4">
                        @foreach($pinned as $announcement)
                            <article class="bg-amber-50 rounded-xl p-5 border border-amber-200 hover:shadow-md transition">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/></svg>
                                                Pinned
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                                @switch($announcement->category->value)
                                                    @case('announcement') bg-blue-100 text-blue-700 @break
                                                    @case('event') bg-green-100 text-green-700 @break
                                                    @case('sop') bg-amber-100 text-amber-700 @break
                                                    @case('maintenance') bg-red-100 text-red-700 @break
                                                @endswitch
                                            ">
                                                {{ $announcement->category->getLabel() }}
                                            </span>
                                        </div>
                                        <h3 class="font-semibold text-gray-900 mb-1">
                                            <a href="{{ route('announcements.show', $announcement->slug) }}" class="hover:text-blue-600">
                                                {{ $announcement->title }}
                                            </a>
                                        </h3>
                                        <p class="text-sm text-gray-600 line-clamp-2">{{ $announcement->summary }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-xs text-gray-400">{{ $announcement->published_at->format('d M Y') }}</span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- All Announcements --}}
            <div class="space-y-4">
                @forelse($announcements as $announcement)
                    <article class="bg-white rounded-xl p-5 border hover:shadow-md transition">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                        @switch($announcement->category->value)
                                            @case('announcement') bg-blue-100 text-blue-700 @break
                                            @case('event') bg-green-100 text-green-700 @break
                                            @case('sop') bg-amber-100 text-amber-700 @break
                                            @case('maintenance') bg-red-100 text-red-700 @break
                                        @endswitch
                                    ">
                                        {{ $announcement->category->getLabel() }}
                                    </span>
                                </div>
                                <h3 class="font-semibold text-gray-900 mb-1">
                                    <a href="{{ route('announcements.show', $announcement->slug) }}" class="hover:text-blue-600">
                                        {{ $announcement->title }}
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-600 line-clamp-2">{{ $announcement->summary }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-xs text-gray-400">{{ $announcement->published_at->format('d M Y') }}</span>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                        <p class="text-gray-500">Belum ada pengumuman.</p>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            @if($announcements->hasPages())
                <div class="mt-8">
                    {{ $announcements->links() }}
                </div>
            @endif
        </div>
    </main>

    {{-- Footer --}}
    <footer class="border-t bg-white mt-auto">
        <div class="max-w-6xl mx-auto px-4 py-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('img/jgu.png') }}" alt="JGU Logo" class="h-8 w-auto">
                    <div class="text-sm">
                        <div class="font-semibold text-gray-900">Jakarta Global University</div>
                        <div class="text-gray-500 text-xs">Media & Strategic Communications</div>
                    </div>
                </div>
                <div class="text-sm text-gray-500 text-center sm:text-right">
                    <p>&copy; {{ date('Y') }} MSC Hub</p>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
