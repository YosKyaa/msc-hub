@php
    use App\Enums\AttendanceAction;

    $isCheckIn = $action === AttendanceAction::CHECK_IN;
    $accent = $isCheckIn ? 'blue' : 'emerald';
    $opensAt = $event->{$action->openColumn()};
    $closesAt = $event->{$action->closeColumn()};
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">
    <title>QR {{ $action->getLabel() }} — {{ $event->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Tombolnya alat bantu layar, bukan bagian poster: ia tidak boleh
           ikut tercetak ketika halaman ini dicetak langsung dari peramban. */
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="min-h-screen bg-white flex flex-col items-center justify-center px-6 py-10 text-center">
    <img src="{{ asset('img/jgu.png') }}" alt="Logo JGU" class="h-12 w-auto mb-6">

    {{-- Label besar dan warna kontras: panitia harus bisa memastikan sekilas
         bahwa QR yang terpasang adalah aksi yang benar. --}}
    <p class="text-2xl sm:text-4xl font-bold tracking-[0.2em] text-{{ $accent }}-600 uppercase">
        {{ $action->getLabel() }}
    </p>
    <h1 class="text-2xl sm:text-4xl font-bold text-gray-900 mt-3 max-w-3xl leading-tight">{{ $event->name }}</h1>
    <p class="text-lg text-gray-600 mt-2">{{ $event->event_date->translatedFormat('l, d F Y') }}</p>

    @if($opensAt || $closesAt)
        <p class="text-base sm:text-lg text-gray-500 mt-2">
            {{ $opensAt ? 'Dibuka '.$opensAt->translatedFormat('H:i') : 'Tanpa batas awal' }}
            &ndash;
            {{ $closesAt ? 'ditutup '.$closesAt->translatedFormat('H:i').' WIB' : 'tanpa batas akhir' }}
        </p>
    @endif

    <img src="{{ $qrDataUri }}" alt="QR {{ $action->getLabel() }}"
         class="w-64 h-64 sm:w-96 sm:h-96 my-8 border-8 border-{{ $accent }}-600 rounded-2xl">

    <p class="text-base sm:text-lg text-gray-700">Pindai QR di atas, lalu masuk dengan akun Google JGU Anda.</p>
    <p class="mt-2 text-sm sm:text-base font-mono text-gray-500 break-all max-w-2xl">{{ $attendanceUrl }}</p>

    <a href="{{ request()->fullUrlWithQuery(['unduh' => 1]) }}"
       class="no-print mt-8 inline-flex items-center gap-2 rounded-lg bg-{{ $accent }}-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-{{ $accent }}-700">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
        </svg>
        Unduh PDF
    </a>
    <p class="no-print mt-2 text-xs text-gray-400">Lembar A4 siap cetak untuk ditempel di lokasi acara.</p>

    <p class="mt-10 text-xs text-gray-400">Media &amp; Strategic Communications — Jakarta Global University</p>
</body>
</html>
