<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">
    <title>@yield('title', 'Masuk') - MSC Hub JGU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
{{-- Halaman masuk sengaja tanpa navigasi: tidak ada yang perlu dikerjakan di
     sini selain memilih cara masuk. --}}
<body class="flex min-h-screen flex-col bg-gray-50">
    <x-organisms.flash-messages />

    <main class="flex flex-grow items-center justify-center px-4 py-10 sm:py-16">
        <div class="w-full max-w-3xl">
            @yield('content')
        </div>
    </main>

    <x-organisms.site-footer />

    @stack('scripts')
</body>
</html>
