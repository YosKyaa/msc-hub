<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">
    <title>@yield('title', 'Peminjaman') - MSC JGU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col">
    <x-organisms.site-header tone="indigo" />

    {{-- Organism: reusable shadcn-style feedback notifications --}}
    <x-organisms.flash-messages />

    {{-- Main Content --}}
    <main class="mx-auto w-full max-w-6xl flex-grow px-4 py-6">
        @yield('content')
    </main>

    <x-organisms.site-footer />

    @stack('scripts')
</body>
</html>
