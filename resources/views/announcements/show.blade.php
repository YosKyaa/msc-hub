<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $announcement->title }} - MSC Hub JGU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .prose { max-width: none; }
        .prose h2 { font-size: 1.5rem; font-weight: 600; margin-top: 2rem; margin-bottom: 1rem; }
        .prose h3 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.75rem; }
        .prose p { margin-bottom: 1rem; line-height: 1.75; }
        .prose ul, .prose ol { margin-bottom: 1rem; padding-left: 1.5rem; }
        .prose li { margin-bottom: 0.5rem; }
        .prose ul { list-style-type: disc; }
        .prose ol { list-style-type: decimal; }
        .prose a { color: #2563eb; text-decoration: underline; }
        .prose blockquote { border-left: 4px solid #e5e7eb; padding-left: 1rem; margin: 1rem 0; color: #6b7280; font-style: italic; }
        .prose strong { font-weight: 600; }
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
                    <a href="{{ route('announcements.index') }}" class="text-sm text-blue-600 font-medium">Pengumuman</a>
                    <a href="{{ route('request.content') }}" class="text-sm text-gray-600 hover:text-blue-600 transition">Ajukan Konten</a>
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
                <a href="{{ route('announcements.index') }}" class="block px-3 py-2 rounded-lg text-sm text-blue-600 bg-blue-50">Pengumuman</a>
                <a href="{{ route('request.content') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Ajukan Konten</a>
                <a href="{{ route('booking.room') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Booking Ruang</a>
            </div>
        </div>
    </header>

    <main class="flex-grow py-8">
        <div class="max-w-3xl mx-auto px-4">
            {{-- Breadcrumb --}}
            <nav class="mb-6">
                <ol class="flex items-center gap-2 text-sm text-gray-500">
                    <li><a href="{{ route('landing') }}" class="hover:text-blue-600">Beranda</a></li>
                    <li><span class="text-gray-300">/</span></li>
                    <li><a href="{{ route('announcements.index') }}" class="hover:text-blue-600">Pengumuman</a></li>
                    <li><span class="text-gray-300">/</span></li>
                    <li class="text-gray-900 font-medium truncate max-w-[200px]">{{ $announcement->title }}</li>
                </ol>
            </nav>

            {{-- Article --}}
            <article class="bg-white rounded-xl p-6 md:p-8 shadow-sm border">
                {{-- Header --}}
                <header class="mb-6 pb-6 border-b">
                    <div class="flex items-center gap-2 mb-4">
                        @if($announcement->is_pinned)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/></svg>
                                Pinned
                            </span>
                        @endif
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
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">{{ $announcement->title }}</h1>
                    <div class="flex items-center gap-4 text-sm text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ $announcement->published_at->format('d F Y') }}
                        </span>
                        @if($announcement->creator)
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $announcement->creator->name }}
                            </span>
                        @endif
                    </div>
                </header>

                {{-- Summary --}}
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <p class="text-gray-700 font-medium">{{ $announcement->summary }}</p>
                </div>

                {{-- Content --}}
                @if($announcement->content)
                    <div class="prose text-gray-700">
                        {!! $announcement->content !!}
                    </div>
                @endif
            </article>

            {{-- Related Announcements --}}
            @if($relatedAnnouncements->isNotEmpty())
                <div class="mt-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Pengumuman Terkait</h2>
                    <div class="grid sm:grid-cols-3 gap-4">
                        @foreach($relatedAnnouncements as $related)
                            <article class="bg-white rounded-xl p-4 border hover:shadow-md transition">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                        @switch($related->category->value)
                                            @case('announcement') bg-blue-100 text-blue-700 @break
                                            @case('event') bg-green-100 text-green-700 @break
                                            @case('sop') bg-amber-100 text-amber-700 @break
                                            @case('maintenance') bg-red-100 text-red-700 @break
                                        @endswitch
                                    ">
                                        {{ $related->category->getLabel() }}
                                    </span>
                                </div>
                                <h3 class="font-medium text-gray-900 text-sm mb-1 line-clamp-2">
                                    <a href="{{ route('announcements.show', $related->slug) }}" class="hover:text-blue-600">
                                        {{ $related->title }}
                                    </a>
                                </h3>
                                <span class="text-xs text-gray-400">{{ $related->published_at->format('d M Y') }}</span>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Back Link --}}
            <div class="mt-8">
                <a href="{{ route('announcements.index') }}" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Kembali ke Daftar Pengumuman
                </a>
            </div>
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
