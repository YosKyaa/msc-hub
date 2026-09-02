<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">
    <title>QR Absensi — {{ $event->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-white flex flex-col items-center justify-center px-6 py-10 text-center">
    <img src="{{ asset('img/jgu.png') }}" alt="Logo JGU" class="h-12 w-auto mb-6">

    <p class="text-sm uppercase tracking-[0.2em] text-gray-500">Absensi Kegiatan</p>
    <h1 class="text-3xl sm:text-5xl font-bold text-gray-900 mt-2 max-w-3xl leading-tight">{{ $event->name }}</h1>
    <p class="text-lg text-gray-600 mt-3">{{ $event->event_date->translatedFormat('l, d F Y') }}</p>

    <img src="{{ $qrDataUri }}" alt="QR absensi" class="w-64 h-64 sm:w-96 sm:h-96 my-8">

    <p class="text-base sm:text-lg text-gray-700">Pindai QR di atas, lalu masuk dengan akun Google JGU Anda.</p>
    <p class="mt-2 text-sm sm:text-base font-mono text-gray-500 break-all max-w-2xl">{{ $attendanceUrl }}</p>

    <p class="mt-10 text-xs text-gray-400">Media &amp; Strategic Communications — Jakarta Global University</p>
</body>
</html>
