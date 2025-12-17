<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">
    <title>MSC Hub - Portal Layanan Media JGU</title>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                        'playfair': ['Playfair Display', 'serif'],
                    },
                    colors: {
                        'dark': '#0a0a0a',
                        'dark-light': '#141414',
                        'accent': '#3b82f6',
                        'accent-light': '#60a5fa',
                    }
                }
            }
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
        .font-playfair { font-family: 'Playfair Display', serif; }

        /* Smooth scroll */
        html { scroll-behavior: smooth; }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Card hover effects */
        .service-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .service-card:hover {
            transform: translateY(-8px);
        }

        /* Team card hover */
        .team-card .team-img {
            transition: all 0.4s ease;
        }
        .team-card:hover .team-img {
            transform: scale(1.05);
        }

        /* Announcement card */
        .announcement-card {
            transition: all 0.3s ease;
        }
        .announcement-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15);
        }

        /* Feature work card */
        .work-card {
            transition: all 0.4s ease;
        }
        .work-card:hover {
            transform: translateY(-6px);
        }
        .work-card .work-overlay {
            opacity: 0;
            transition: all 0.3s ease;
        }
        .work-card:hover .work-overlay {
            opacity: 1;
        }

        /* Stats counter animation */
        .stat-number {
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* FAQ accordion */
        .faq-item {
            transition: all 0.3s ease;
        }
        .faq-item:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }
    </style>
</head>
<body class="font-inter bg-white text-gray-900 antialiased">
    {{-- Header/Navbar --}}
    <header class="fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-b border-gray-100" x-data="{ mobileMenuOpen: false, scrolled: false }" @scroll.window="scrolled = (window.pageYOffset > 20)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16">
                {{-- Logo --}}
                <a href="{{ route('landing') }}" class="flex items-center gap-3 group">
                    <img src="{{ asset('img/jgu.png') }}" alt="JGU Logo" class="h-9 w-auto">
                    <div class="hidden sm:block">
                        <div class="font-bold text-lg text-gray-900 group-hover:text-accent transition">JGU <span class="text-accent">&bull;</span> MSC</div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Hub Portal</div>
                    </div>
                </a>

                {{-- Desktop Nav --}}
                <nav class="hidden lg:flex items-center gap-8">
                    <a href="{{ route('request.content') }}" class="text-sm text-gray-600 hover:text-accent transition font-medium">Ajukan Konten</a>
                    <a href="{{ route('request.status') }}" class="text-sm text-gray-600 hover:text-accent transition font-medium">Cek Status</a>
                    <a href="{{ route('booking.inventory') }}" class="text-sm text-gray-600 hover:text-accent transition font-medium">Pinjam Inventaris</a>
                    <a href="{{ route('booking.room') }}" class="text-sm text-gray-600 hover:text-accent transition font-medium">Booking Ruang</a>
                </nav>

                {{-- Right Section --}}
                <div class="hidden lg:flex items-center gap-4">
                    @if($requester)
                        <div class="flex items-center gap-3">
                            @if($requester['avatar'] ?? null)
                                <img src="{{ $requester['avatar'] }}" alt="" class="w-9 h-9 rounded-full ring-2 ring-accent/20">
                            @else
                                <div class="w-9 h-9 bg-accent/10 rounded-full flex items-center justify-center">
                                    <span class="text-accent font-semibold">{{ substr($requester['name'], 0, 1) }}</span>
                                </div>
                            @endif
                            <div class="text-sm">
                                <div class="text-gray-800 font-medium">{{ Str::limit($requester['name'], 15) }}</div>
                                <a href="{{ route('my.bookings') }}" class="text-xs text-accent hover:underline">My Bookings</a>
                            </div>
                            <form action="{{ route('auth.google.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-500 ml-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                </button>
                            </form>
                        </div>
                    @else
                        <a href="{{ route('auth.google.redirect') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-dark text-white rounded-full text-sm font-medium hover:bg-gray-800 transition">
                            <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/></svg>
                            Masuk
                        </a>
                    @endif
                    <a href="/admin" class="text-sm text-gray-500 hover:text-accent transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </a>
                </div>

                {{-- Mobile Menu Button --}}
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden p-2 text-gray-600 hover:text-accent rounded-lg hover:bg-gray-100 transition">
                    <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Mobile Menu --}}
            <div x-show="mobileMenuOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="lg:hidden border-t py-4 space-y-2">
                @if($requester)
                    <div class="px-3 py-3 bg-gray-50 rounded-xl mb-3">
                        <div class="flex items-center gap-3">
                            @if($requester['avatar'] ?? null)
                                <img src="{{ $requester['avatar'] }}" alt="" class="w-10 h-10 rounded-full">
                            @else
                                <div class="w-10 h-10 bg-accent/10 rounded-full flex items-center justify-center">
                                    <span class="text-accent font-semibold">{{ substr($requester['name'], 0, 1) }}</span>
                                </div>
                            @endif
                            <div>
                                <div class="font-medium text-gray-900">{{ $requester['name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $requester['email'] }}</div>
                            </div>
                        </div>
                    </div>
                @endif
                <a href="{{ route('request.content') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-700 hover:bg-gray-50 transition">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Ajukan Konten
                </a>
                <a href="{{ route('request.status') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-700 hover:bg-gray-50 transition">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    Cek Status
                </a>
                <a href="{{ route('booking.inventory') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-700 hover:bg-gray-50 transition">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Pinjam Inventaris
                </a>
                <a href="{{ route('booking.room') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-700 hover:bg-gray-50 transition">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Booking Ruang
                </a>
                @if($requester)
                    <a href="{{ route('my.bookings') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-accent bg-accent/5 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        My Bookings
                    </a>
                    <form action="{{ route('auth.google.logout') }}" method="POST" class="pt-2 border-t mt-2">
                        @csrf
                        <button type="submit" class="flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-red-600 hover:bg-red-50 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('auth.google.redirect') }}" class="flex items-center justify-center gap-2 mx-3 mt-3 px-4 py-3 bg-dark text-white rounded-xl font-medium hover:bg-gray-800 transition">
                        <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/></svg>
                        Masuk dengan Google
                    </a>
                @endif
            </div>
        </div>
    </header>

    <main>
        {{-- Hero Section --}}
        <section class="relative min-h-screen flex items-center pt-16 overflow-hidden bg-gradient-to-br from-gray-50 via-white to-blue-50">
            {{-- Background Pattern --}}
            <div class="absolute inset-0 opacity-40">
                <div class="absolute top-20 left-10 w-72 h-72 bg-blue-200 rounded-full mix-blend-multiply filter blur-3xl animate-pulse"></div>
                <div class="absolute top-40 right-10 w-72 h-72 bg-purple-200 rounded-full mix-blend-multiply filter blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
                <div class="absolute bottom-20 left-1/2 w-72 h-72 bg-pink-200 rounded-full mix-blend-multiply filter blur-3xl animate-pulse" style="animation-delay: 4s;"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 py-20 lg:py-32">
                <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-white rounded-full shadow-sm border mb-6">
                            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                            <span class="text-sm text-gray-600">Portal Layanan Aktif</span>
                        </div>

                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-900 leading-tight mb-6">
                            <span class="font-playfair italic">MSC</span> Hub
                            <span class="block text-2xl sm:text-3xl lg:text-4xl font-normal text-gray-600 mt-2">Portal Layanan Media JGU</span>
                        </h1>

                        <p class="text-lg text-gray-600 mb-8 max-w-lg">
                            Platform terpadu untuk pengajuan konten, peminjaman inventaris, dan booking ruang di Media & Strategic Communications.
                        </p>

                        <div class="flex flex-wrap gap-4">
                            <a href="{{ route('request.content') }}" class="inline-flex items-center gap-2 px-6 py-3.5 bg-dark text-white rounded-full font-semibold hover:bg-gray-800 transition shadow-lg shadow-gray-900/10">
                                <span>Ajukan Konten</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>
                            <a href="{{ route('booking.room') }}" class="inline-flex items-center gap-2 px-6 py-3.5 bg-white text-gray-900 rounded-full font-semibold hover:bg-gray-50 transition border border-gray-200">
                                <span>Booking Ruang</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </a>
                        </div>

                        {{-- Quick Stats --}}
                        <div class="grid grid-cols-3 gap-6 mt-12 pt-8 border-t border-gray-200">
                            <div>
                                <div class="text-3xl font-bold text-gray-900">24/7</div>
                                <div class="text-sm text-gray-500">Akses Portal</div>
                            </div>
                            <div>
                                <div class="text-3xl font-bold text-gray-900">JGU</div>
                                <div class="text-sm text-gray-500">Email Domain</div>
                            </div>
                            <div>
                                <div class="text-3xl font-bold text-gray-900">2x</div>
                                <div class="text-sm text-gray-500">Tahap Approval</div>
                            </div>
                        </div>
                    </div>

                    <div class="relative hidden lg:block">
                        <div class="relative">
                            {{-- Decorative cards --}}
                            <div class="absolute -top-6 -left-6 w-64 h-40 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl shadow-2xl transform -rotate-6"></div>
                            <div class="absolute -bottom-6 -right-6 w-64 h-40 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl shadow-2xl transform rotate-6"></div>

                            {{-- Main card --}}
                            <div class="relative bg-white rounded-2xl shadow-2xl p-8 border">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900">Media Services</div>
                                        <div class="text-sm text-gray-500">Content Production</div>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <span class="text-sm text-gray-700">Foto & Video Production</span>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <span class="text-sm text-gray-700">Graphic Design</span>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <span class="text-sm text-gray-700">Social Media Content</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Announcements Section --}}
        @if($announcementsPinned->isNotEmpty() || $announcementsLatest->isNotEmpty())
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-12">
                    <div>
                        <span class="text-sm font-medium text-accent uppercase tracking-wider">Informasi Terbaru</span>
                        <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mt-2">Pengumuman</h2>
                    </div>
                    <a href="{{ route('announcements.index') }}" class="inline-flex items-center gap-2 text-gray-600 hover:text-accent transition font-medium">
                        <span>Lihat semua</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($announcementsPinned as $announcement)
                        <x-landing.announcement-card :announcement="$announcement" :pinned="true" />
                    @endforeach
                    @foreach($announcementsLatest as $announcement)
                        <x-landing.announcement-card :announcement="$announcement" />
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- Services Section --}}
        <section class="py-20 bg-dark text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="grid lg:grid-cols-2 gap-12 mb-16">
                    <div>
                        <span class="text-sm font-medium text-accent uppercase tracking-wider">Layanan Kami</span>
                        <h2 class="text-3xl sm:text-4xl font-bold mt-2">
                            Platform terpadu untuk semua kebutuhan <span class="font-playfair italic text-accent-light">media kampus</span>
                        </h2>
                    </div>
                    <div class="flex items-center">
                        <p class="text-gray-400 text-lg">
                            Kami menyediakan berbagai layanan untuk mendukung kegiatan komunikasi dan branding di lingkungan Jakarta Global University.
                        </p>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    {{-- Pengajuan Konten --}}
                    <div class="service-card group bg-dark-light rounded-2xl p-6 border border-gray-800 hover:border-accent/50">
                        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Pengajuan Konten</h3>
                        <p class="text-gray-400 text-sm mb-6">Request pembuatan foto, video, desain, atau publikasi untuk kegiatan kampus.</p>
                        <a href="{{ route('request.content') }}" class="inline-flex items-center gap-2 text-accent font-medium text-sm group-hover:gap-3 transition-all">
                            <span>Ajukan sekarang</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>

                    {{-- Tracking Status --}}
                    <div class="service-card group bg-dark-light rounded-2xl p-6 border border-gray-800 hover:border-green-500/50">
                        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Tracking Status</h3>
                        <p class="text-gray-400 text-sm mb-6">Pantau progress pengajuan konten secara real-time dengan kode request.</p>
                        <a href="{{ route('request.status') }}" class="inline-flex items-center gap-2 text-green-400 font-medium text-sm group-hover:gap-3 transition-all">
                            <span>Cek status</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>

                    {{-- Peminjaman Inventaris --}}
                    <div class="service-card group bg-dark-light rounded-2xl p-6 border border-gray-800 hover:border-amber-500/50">
                        <div class="w-14 h-14 bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Pinjam Inventaris</h3>
                        <p class="text-gray-400 text-sm mb-6">Pinjam kamera, tripod, lighting, dan peralatan produksi lainnya.</p>
                        <a href="{{ route('booking.inventory') }}" class="inline-flex items-center gap-2 text-amber-400 font-medium text-sm group-hover:gap-3 transition-all">
                            <span>Pinjam sekarang</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>

                    {{-- Booking Ruang --}}
                    <div class="service-card group bg-dark-light rounded-2xl p-6 border border-gray-800 hover:border-purple-500/50">
                        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Booking Ruang</h3>
                        <p class="text-gray-400 text-sm mb-6">Reservasi ruang meeting atau ruang produksi MSC.</p>
                        <a href="{{ route('booking.room') }}" class="inline-flex items-center gap-2 text-purple-400 font-medium text-sm group-hover:gap-3 transition-all">
                            <span>Booking sekarang</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Bulletin Studio Coming Soon --}}
                {{-- <div class="mt-8 bg-gradient-to-r from-gray-800/50 to-gray-900/50 rounded-2xl p-6 border border-dashed border-gray-700">
                    <div class="flex flex-col sm:flex-row items-center gap-6">
                        <div class="w-16 h-16 bg-gray-800 rounded-xl flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </div>
                        <div class="flex-1 text-center sm:text-left">
                            <h3 class="text-xl font-semibold text-gray-400">Bulletin Studio</h3>
                            <p class="text-gray-500 text-sm mt-1">Fitur booking studio untuk rekaman podcast dan video akan segera hadir.</p>
                        </div>
                        <span class="px-4 py-2 bg-gray-800 text-gray-500 text-sm font-medium rounded-full">Coming Soon</span>
                    </div>
                </div>
            </div> --}}
        </section>

        {{-- Process/How It Works Section --}}
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="text-center mb-16">
                    <span class="text-sm font-medium text-accent uppercase tracking-wider">Cara Kerja</span>
                    <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mt-2">Alur Proses Layanan</h2>
                </div>

                <div class="grid lg:grid-cols-2 gap-12">
                    {{-- Alur Pengajuan Konten --}}
                    <div class="bg-white rounded-2xl p-8 shadow-sm border">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">Pengajuan Konten</h3>
                        </div>
                        <div class="space-y-6">
                            @foreach([
                                ['Login & Isi Form', 'Login dengan email JGU dan lengkapi form pengajuan'],
                                ['Review Staff MSC', 'Tim MSC mereview kelengkapan dan kelayakan request'],
                                ['Approval Head MSC', 'Kepala MSC memberikan persetujuan final'],
                                ['Produksi & Delivery', 'Konten diproduksi dan dikirim sesuai timeline']
                            ] as $index => $step)
                            <div class="flex gap-4">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-sm">{{ $index + 1 }}</div>
                                    @if($index < 3)
                                    <div class="absolute top-10 left-1/2 w-0.5 h-6 bg-blue-200 -translate-x-1/2"></div>
                                    @endif
                                </div>
                                <div class="flex-1 pb-4">
                                    <div class="font-semibold text-gray-900">{{ $step[0] }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ $step[1] }}</div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Alur Booking --}}
                    <div class="bg-white rounded-2xl p-8 shadow-sm border">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">Booking & Peminjaman</h3>
                        </div>
                        <div class="space-y-6">
                            @foreach([
                                ['Login & Pilih Layanan', 'Login dengan email JGU, pilih booking ruang atau pinjam inventaris'],
                                ['Pilih Tanggal & Item', 'Pilih tanggal, waktu, dan item yang dibutuhkan'],
                                ['Tunggu Konfirmasi', 'Admin MSC mengkonfirmasi ketersediaan dan approval'],
                                ['Gunakan & Kembalikan', 'Ambil item sesuai jadwal dan kembalikan tepat waktu']
                            ] as $index => $step)
                            <div class="flex gap-4">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold text-sm">{{ $index + 1 }}</div>
                                    @if($index < 3)
                                    <div class="absolute top-10 left-1/2 w-0.5 h-6 bg-purple-200 -translate-x-1/2"></div>
                                    @endif
                                </div>
                                <div class="flex-1 pb-4">
                                    <div class="font-semibold text-gray-900">{{ $step[0] }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ $step[1] }}</div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Info Box --}}
                <div class="mt-12 bg-gradient-to-r from-amber-50 to-orange-50 rounded-2xl p-8 border border-amber-200">
                    <div class="flex items-start gap-4 mb-6">
                        <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900 text-lg">Informasi Penting</h4>
                            <p class="text-gray-600 text-sm mt-1">Pastikan Anda memahami ketentuan layanan MSC</p>
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-6">
                        <div class="flex items-center gap-4 bg-white rounded-xl p-4">
                            <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">Jam Layanan</div>
                                <div class="text-sm text-gray-500">08:00 - 16:00 WIB</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 bg-white rounded-xl p-4">
                            <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">Email Wajib</div>
                                <div class="text-sm text-gray-500">@jgu.ac.id</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 bg-white rounded-xl p-4">
                            <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">Approval</div>
                                <div class="text-sm text-gray-500">2 Tahap Review</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Team Section - Modern Portfolio Style (Horizontal Layout) --}}
        <section class="py-20 bg-dark text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="grid lg:grid-cols-2 gap-12 mb-16">
                    <div>
                        <span class="text-sm font-medium text-accent uppercase tracking-wider">Tim Kami</span>
                        <h2 class="text-3xl sm:text-4xl font-bold mt-2">
                            <span class="font-playfair italic">Tim</span> MSC
                        </h2>
                    </div>
                    <div class="flex items-center">
                        <p class="text-gray-400 text-lg">
                            Tim profesional yang siap membantu kebutuhan media dan komunikasi Anda di Jakarta Global University.
                        </p>
                    </div>
                </div>

                {{-- Horizontal Team Cards --}}
                <div class="space-y-4">
                    {{-- Hadi Wijaya - Ketua Departement MSC --}}
                    <div class="group bg-dark-light rounded-2xl overflow-hidden transition-all duration-300 hover:bg-accent">
                        <div class="flex flex-col md:flex-row items-center">
                            <div class="w-full md:w-48 h-40 md:h-32 overflow-hidden shrink-0">
                                <div class="w-full h-full bg-gradient-to-br from-accent to-blue-700 flex items-center justify-center">
                                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-1 p-6 text-center md:text-left">
                                <h4 class="text-xl font-semibold group-hover:text-dark transition-colors">Hadi Wijaya, S.ST, M.IT.</h4>
                                <p class="text-gray-400 text-sm mt-1 group-hover:text-dark/70 transition-colors">Ketua Departement MSC</p>
                            </div>
                            <div class="flex gap-2 p-6">
                                <a href="#" class="w-10 h-10 border border-gray-700 rounded-full flex items-center justify-center hover:bg-white hover:text-dark transition group-hover:border-dark/30">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                </a>
                                <a href="#" class="w-10 h-10 border border-gray-700 rounded-full flex items-center justify-center hover:bg-white hover:text-dark transition group-hover:border-dark/30">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Chika Arzhika - Staff MSC --}}
                    <div class="group bg-dark-light rounded-2xl overflow-hidden transition-all duration-300 hover:bg-accent">
                        <div class="flex flex-col md:flex-row items-center">
                            <div class="w-full md:w-48 h-40 md:h-32 overflow-hidden shrink-0">
                                <div class="w-full h-full bg-gradient-to-br from-purple-600 to-purple-800 flex items-center justify-center">
                                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-1 p-6 text-center md:text-left">
                                <h4 class="text-xl font-semibold group-hover:text-dark transition-colors">Chika Arzhika</h4>
                                <p class="text-gray-400 text-sm mt-1 group-hover:text-dark/70 transition-colors">Staff MSC</p>
                            </div>
                            <div class="flex gap-2 p-6">
                                <a href="#" class="w-10 h-10 border border-gray-700 rounded-full flex items-center justify-center hover:bg-white hover:text-dark transition group-hover:border-dark/30">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Yosua Immanuel - Staff MSC --}}
                    <div class="group bg-dark-light rounded-2xl overflow-hidden transition-all duration-300 hover:bg-accent">
                        <div class="flex flex-col md:flex-row items-center">
                            <div class="w-full md:w-48 h-40 md:h-32 overflow-hidden shrink-0">
                                <div class="w-full h-full bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-1 p-6 text-center md:text-left">
                                <h4 class="text-xl font-semibold group-hover:text-dark transition-colors">Yosua Immanuel</h4>
                                <p class="text-gray-400 text-sm mt-1 group-hover:text-dark/70 transition-colors">Staff MSC</p>
                            </div>
                            <div class="flex gap-2 p-6">
                                <a href="#" class="w-10 h-10 border border-gray-700 rounded-full flex items-center justify-center hover:bg-white hover:text-dark transition group-hover:border-dark/30">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Featured Works Section - Digital Agency Style --}}
        @if($featuredWorks->isNotEmpty())
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                {{-- Centered Header - Digital Agency Style --}}
                <div class="text-center mb-16">
                    <p class="text-gray-500 mb-4 max-w-xl mx-auto">Pencapaian dan karya terbaik yang telah kami produksi untuk berbagai kegiatan di kampus.</p>
                    <h2 class="text-4xl sm:text-5xl font-bold text-gray-900 uppercase tracking-tight">
                        Featured <span class="font-playfair italic normal-case">Works</span>
                    </h2>
                    <p class="text-gray-400 text-sm mt-4 uppercase tracking-widest">Take a look at our projects</p>
                </div>

                {{-- Works Grid - 2 Column Layout --}}
                <div class="grid lg:grid-cols-2 gap-8">
                    @foreach($featuredWorks as $work)
                    <div class="group">
                        <div class="relative overflow-hidden rounded-2xl bg-gray-100 aspect-[16/10]">
                            @if($work->image)
                                <img src="{{ asset('storage/' . $work->image) }}" alt="{{ $work->title }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                            @else
                                <div class="absolute inset-0 bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center">
                                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-lg">
                                        <svg class="w-10 h-10 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                </div>
                            @endif
                            @if($work->url)
                                <a href="{{ $work->url }}" target="_blank" class="absolute inset-0 z-10"></a>
                            @endif
                        </div>
                        <div class="flex items-start justify-between mt-6">
                            <div>
                                <h5 class="text-lg font-semibold text-gray-900">{{ $work->title }}</h5>
                                @if($work->client)
                                    <span class="text-gray-500 text-sm">{{ $work->client }}</span>
                                @endif
                            </div>
                            @if($work->category)
                                <span class="text-gray-400 text-sm uppercase tracking-wider">( {{ $work->category }} )</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- FAQ Section - Modern Agency 2 Style --}}
        <section class="py-20 bg-dark text-white overflow-hidden" x-data="{ openFaq: 0 }">
            {{-- Frequency Marquee --}}
            <div class="mb-20 overflow-hidden py-6 border-y border-gray-800">
                <div class="flex animate-marquee whitespace-nowrap">
                    @for($i = 0; $i < 8; $i++)
                        <span class="text-4xl sm:text-5xl font-light text-white/10 mx-12 uppercase tracking-widest"> Asked Questions</span>
                    @endfor
                </div>
            </div>

            <div class="max-w-3xl mx-auto px-4 sm:px-6">
                {{-- Header - Modern Agency 2 Style --}}
                <div class="text-center mb-16">
                    <h2 class="text-5xl sm:text-6xl font-bold uppercase tracking-tight leading-tight">
                        <span class="font-playfair italic normal-case">A</span>sked<br>Questions
                    </h2>
                </div>

                <p class="text-gray-500 text-center mb-12 max-w-md mx-auto">Berikut adalah beberapa pertanyaan yang sering diajukan tentang layanan MSC Hub.</p>

                {{-- Accordion --}}
                <div class="accordion">
                    @foreach([
                        ['Siapa yang bisa menggunakan layanan MSC Hub?', 'Layanan MSC Hub tersedia untuk seluruh civitas akademika JGU (dosen, mahasiswa, dan staf) yang memiliki email resmi @jgu.ac.id atau @student.jgu.ac.id.'],
                        ['Berapa lama proses pengajuan konten?', 'Proses review biasanya memakan waktu 1-3 hari kerja. Waktu produksi tergantung kompleksitas konten yang diminta. Pastikan mengajukan minimal 7 hari sebelum tanggal kebutuhan.'],
                        ['Apa saja yang bisa dipinjam dari inventaris MSC?', 'MSC menyediakan berbagai peralatan seperti kamera DSLR/mirrorless, tripod, gimbal, lighting kit, microphone, dan backdrop.'],
                        ['Apakah ada biaya untuk menggunakan layanan MSC?', 'Layanan MSC Hub gratis untuk civitas akademika JGU. Namun, peminjam bertanggung jawab atas kerusakan atau kehilangan peralatan yang dipinjam.']
                    ] as $index => $faq)
                    <div class="accordion-item border-b border-gray-800" :class="{ 'active': openFaq === {{ $index }} }">
                        <button @click="openFaq = openFaq === {{ $index }} ? null : {{ $index }}" class="w-full flex items-center gap-5 py-7 text-left group">
                            {{-- Face Icon --}}
                            <div class="w-9 h-9 shrink-0 opacity-60 group-hover:opacity-100 transition-opacity">
                                <svg viewBox="0 0 24 24" fill="none" class="w-full h-full" stroke="currentColor" stroke-width="1">
                                    <circle cx="12" cy="12" r="10"/>
                                    <circle cx="9" cy="10" r="1.5" fill="currentColor" stroke="none"/>
                                    <circle cx="15" cy="10" r="1.5" fill="currentColor" stroke="none"/>
                                    <path d="M8 15C8.5 16.5 10 17.5 12 17.5C14 17.5 15.5 16.5 16 15" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <span class="flex-1 text-lg font-medium" :class="{ 'text-accent': openFaq === {{ $index }} }">{{ $faq[0] }}</span>
                            {{-- Arrow Icon --}}
                            <div class="w-5 h-3 shrink-0 transition-transform duration-300" :class="{ 'rotate-90': openFaq === {{ $index }} }">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 19 8" fill="none" class="w-full h-full">
                                    <path d="M0.5 1L17.5 1C18.3 1 18.8 2 18.2 2.6L14 6.8L12 5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                        </button>
                        <div x-show="openFaq === {{ $index }}" x-collapse x-cloak>
                            <div class="pb-7 pl-14">
                                <p class="text-gray-400 leading-relaxed">{{ $faq[1] }}</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <style>
            @keyframes marquee {
                0% { transform: translateX(0); }
                100% { transform: translateX(-50%); }
            }
            .animate-marquee {
                animation: marquee 25s linear infinite;
            }
            .accordion-item.active {
                background: rgba(59, 130, 246, 0.05);
            }
        </style>
    </main>

    {{-- Footer --}}
    <footer class="bg-dark text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">
                {{-- Brand --}}
                <div class="lg:col-span-2">
                    <div class="flex items-center gap-3 mb-6">
                        <img src="{{ asset('img/jgucover.png') }}" alt="JGU Logo" class="h-12 w-auto">
                        <div>
                            <div class="font-bold text-xl">JGU &bull; MSC Hub</div>
                            <div class="text-gray-400 text-sm">Media & Strategic Communications</div>
                        </div>
                    </div>
                    <p class="text-gray-400 mb-6 max-w-md">Platform terpadu untuk layanan media dan komunikasi strategis Jakarta Global University.</p>
                    <div class="text-gray-500 text-sm">
                        <p>Jl. Boulevard Grand Depok City</p>
                        <p>Depok, Jawa Barat 16412</p>
                    </div>
                </div>

                {{-- Quick Links --}}
                <div>
                    <h4 class="font-semibold text-lg mb-4">Layanan</h4>
                    <ul class="space-y-3 text-gray-400">
                        <li><a href="{{ route('request.content') }}" class="hover:text-white transition">Ajukan Konten</a></li>
                        <li><a href="{{ route('request.status') }}" class="hover:text-white transition">Cek Status</a></li>
                        <li><a href="{{ route('booking.inventory') }}" class="hover:text-white transition">Pinjam Inventaris</a></li>
                        <li><a href="{{ route('booking.room') }}" class="hover:text-white transition">Booking Ruang</a></li>
                    </ul>
                </div>

                {{-- Contact --}}
                <div>
                    <h4 class="font-semibold text-lg mb-4">Kontak</h4>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            08:00 - 16:00 WIB
                        </li>
                        <li class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            media@jgu.ac.id
                        </li>
                    </ul>
                    <div class="flex gap-3 mt-6">
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-xl flex items-center justify-center hover:bg-accent transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-xl flex items-center justify-center hover:bg-accent transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-xl flex items-center justify-center hover:bg-accent transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-800 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-gray-500 text-sm">&copy; {{ date('Y') }} MSC Hub - Jakarta Global University. All rights reserved.</p>
                <div class="flex gap-6 text-sm text-gray-500">
                    <a href="#" class="hover:text-white transition">Privacy Policy</a>
                    <a href="#" class="hover:text-white transition">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    {{-- Alpine.js Collapse Plugin --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.directive('collapse', (el) => {
                el.style.overflow = 'hidden'
                el.style.transition = 'height 0.3s ease-out'

                if (el.style.display === 'none' || el.offsetHeight === 0) {
                    el.style.height = '0px'
                } else {
                    el.style.height = el.scrollHeight + 'px'
                }

                const observer = new MutationObserver(() => {
                    if (el.style.display !== 'none') {
                        el.style.height = el.scrollHeight + 'px'
                    } else {
                        el.style.height = '0px'
                    }
                })

                observer.observe(el, { attributes: true, attributeFilter: ['style'] })
            })
        })
    </script>
</body>
</html>
