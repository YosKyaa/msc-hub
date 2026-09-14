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
<body class="min-h-screen bg-gray-50">
    {{-- Navigasi menetap di sisi kiri; di ponsel ia menjadi laci yang
         dibuka dari bilah atas. --}}
    <div class="lg:flex" x-data="{ menuOpen: false }" @keydown.escape.window="menuOpen = false">
        <x-organisms.site-sidebar tone="blue" />

        <div class="flex min-h-screen w-full min-w-0 flex-col">
            <x-organisms.top-bar tone="blue" />

            <x-organisms.flash-messages />

            <main class="mx-auto w-full max-w-5xl flex-grow px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                @yield('content')
            </main>

            <x-organisms.site-footer />
        </div>
    </div>

    @stack('scripts')
</body>
</html>
