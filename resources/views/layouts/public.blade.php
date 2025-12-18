<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">
    <title>@yield('title', 'Content Request') - MSC JGU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col">
    {{-- Header --}}
    <header class="bg-white shadow-sm border-b" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-5xl mx-auto px-4">
            {{-- Top Bar --}}
            <div class="flex items-center justify-between py-3">
                {{-- Logo --}}
                <a href="{{ route('request.content') }}" class="flex items-center gap-2">
                    <img src="{{ asset('img/jgu.png') }}" alt="JGU Logo" class="h-8 w-auto">
                    <div class="hidden sm:block">
                        <div class="font-semibold text-gray-900 text-sm">MSC Hub</div>
                        <div class="text-xs text-gray-500">Content Request</div>
                    </div>
                </a>

                {{-- Desktop Nav --}}
                <nav class="hidden md:flex items-center gap-4">
                    <a href="{{ route('landing') }}" class="text-sm text-gray-500 hover:text-gray-900 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Beranda
                    </a>
                    <span class="text-gray-300">|</span>
                    <a href="{{ route('request.content') }}" class="text-sm {{ request()->routeIs('request.content') ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-gray-900' }}">
                        Request
                    </a>
                    <a href="{{ route('request.status') }}" class="text-sm {{ request()->routeIs('request.status*') ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-gray-900' }}">
                        Cek Status
                    </a>
                    
                    @if(session('requester'))
                        <div class="flex items-center gap-2 pl-4 border-l">
                            @if(session('requester.avatar'))
                                <img src="{{ session('requester.avatar') }}" alt="" class="w-8 h-8 rounded-full">
                            @else
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-blue-600 text-sm font-medium">{{ substr(session('requester.name'), 0, 1) }}</span>
                                </div>
                            @endif
                            <span class="text-sm text-gray-700">{{ session('requester.name') }}</span>
                            <form action="{{ route('auth.google.logout') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-500">Logout</button>
                            </form>
                        </div>
                    @endif
                </nav>

                {{-- Mobile: User + Hamburger --}}
                <div class="flex items-center gap-3 md:hidden">
                    @if(session('requester'))
                        @if(session('requester.avatar'))
                            <img src="{{ session('requester.avatar') }}" alt="" class="w-8 h-8 rounded-full">
                        @else
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 text-sm font-medium">{{ substr(session('requester.name'), 0, 1) }}</span>
                            </div>
                        @endif
                    @endif
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="p-2 text-gray-600 hover:text-gray-900">
                        <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Mobile Menu --}}
            <div x-show="mobileMenuOpen" x-cloak x-transition class="md:hidden border-t py-3 space-y-1">
                <a href="{{ route('landing') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Kembali ke Beranda
                </a>
                <div class="border-t my-2"></div>
                @if(session('requester'))
                    <div class="px-3 py-2 text-sm text-gray-700 font-medium border-b pb-3 mb-2">
                        {{ session('requester.name') }}
                        <div class="text-xs text-gray-500 font-normal">{{ session('requester.email') }}</div>
                    </div>
                @endif
                <a href="{{ route('request.content') }}" class="block px-3 py-2 rounded-lg text-sm {{ request()->routeIs('request.content') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                    📝 Request Konten
                </a>
                <a href="{{ route('request.status') }}" class="block px-3 py-2 rounded-lg text-sm {{ request()->routeIs('request.status*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                    🔍 Cek Status
                </a>
                @if(session('requester'))
                    <form action="{{ route('auth.google.logout') }}" method="POST" class="pt-2 border-t mt-2">
                        @csrf
                        <button type="submit" class="block w-full text-left px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50">
                            🚪 Logout
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="max-w-5xl mx-auto px-4 mt-4">
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        </div>
    @endif
    
    @if(session('error'))
        <div class="max-w-5xl mx-auto px-4 mt-4">
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        </div>
    @endif

    {{-- Main Content --}}
    <main class="max-w-5xl mx-auto px-4 py-6 flex-grow w-full">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="border-t bg-white mt-auto">
        <div class="max-w-5xl mx-auto px-4 py-6">
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
                    <p class="text-xs mt-1 hidden sm:block">Jl. Boulevard Grand Depok City, Depok</p>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
