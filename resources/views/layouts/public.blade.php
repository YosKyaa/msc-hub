<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">
    <title>@yield('title', 'Layanan MSC') - MSC JGU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col">
    <x-organisms.site-header tone="blue" />

    {{-- Organism: reusable shadcn-style feedback notifications --}}
    <x-organisms.flash-messages />

    {{-- Main Content --}}
    <main class="mx-auto w-full max-w-6xl flex-grow px-4 py-6">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="border-t bg-white mt-auto">
        <div class="mx-auto max-w-6xl px-4 py-6">
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
