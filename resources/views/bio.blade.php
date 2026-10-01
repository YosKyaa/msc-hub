{{--
    Halaman tautan ringkas untuk bio Instagram.

    Sengaja berdiri sendiri, tanpa sidebar dan bilah atas: yang membukanya
    datang dari ponsel, sekali, dan belum tentu tahu MSC melayani apa saja.
    Menu portal justru menambah pilihan yang tidak ia butuhkan.

    Daftar tautannya disusun App\Http\Controllers\BioController.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">

    <x-organisms.seo
        title="Semua Layanan MSC"
        description="Ajukan konten, pinjam ruangan dan alat multimedia, atau cek status pengajuan Anda di Media & Strategic Communications Jakarta Global University." />

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }

        /* Latar lembut yang tidak ikut menggulung bersama isinya. */
        .bio-latar {
            background:
                radial-gradient(60rem 40rem at 50% -10%, rgb(219 234 254) 0%, transparent 60%),
                radial-gradient(40rem 30rem at 90% 10%, rgb(254 243 199) 0%, transparent 55%),
                rgb(249 250 251);
            background-attachment: fixed;
        }

        /* Tombolnya ditekan dengan jempol, bukan diklik dengan tetikus:
           umpan baliknya dibuat terasa saat disentuh, bukan saat dilayangi. */
        .bio-tombol { transition: transform .12s ease, box-shadow .2s ease, border-color .2s ease; }
        .bio-tombol:active { transform: scale(.985); }

        @media (prefers-reduced-motion: reduce) {
            .bio-tombol { transition: none; }
            .bio-tombol:active { transform: none; }
        }
    </style>
</head>

<body class="bio-latar min-h-screen antialiased">
    {{-- max-w-md: selebar ponsel. Di layar lebar ia tetap satu kolom di
         tengah, karena menyebarnya tombol justru membuat halaman ini terbaca
         seperti menu biasa. --}}
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-5 py-10 sm:py-14">

        {{-- Kepala --}}
        <header class="text-center">
            <img src="{{ asset('img/jgu.png') }}"
                 alt="Jakarta Global University"
                 class="mx-auto h-16 w-auto">

            <h1 class="mt-5 text-2xl font-bold tracking-tight text-gray-900">MSC Hub</h1>
            <p class="mt-1 text-sm font-medium text-blue-700">Media &amp; Strategic Communications</p>
            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                {{ config('msc.bio.tagline') }}
            </p>
        </header>

        {{-- Tautan --}}
        <main class="mt-9 flex-1 space-y-7">
            @foreach ($kelompok as $grup)
                <section>
                    <h2 class="px-1 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        {{ $grup['judul'] }}
                    </h2>

                    @if ($grup['catatan'])
                        <p class="mt-1 px-1 text-xs text-gray-500">{{ $grup['catatan'] }}</p>
                    @endif

                    <div class="mt-3 space-y-3">
                        @foreach ($grup['tautan'] as $tautan)
                            <a href="{{ $tautan['url'] }}"
                               @class([
                                   'bio-tombol group flex items-center gap-4 rounded-2xl border px-4 py-4 shadow-sm',
                                   'border-blue-600 bg-blue-600 text-white hover:bg-blue-700 hover:shadow-lg' => $tautan['utama'],
                                   'border-gray-200 bg-white text-gray-900 hover:border-blue-300 hover:shadow-md' => ! $tautan['utama'],
                               ])>
                                <span @class([
                                    'flex size-11 shrink-0 items-center justify-center rounded-xl',
                                    'bg-white/15' => $tautan['utama'],
                                    'bg-blue-50 text-blue-700' => ! $tautan['utama'],
                                ])>
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="{{ $tautan['icon'] }}" />
                                    </svg>
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-base font-semibold leading-tight">{{ $tautan['label'] }}</span>
                                    <span @class([
                                        'mt-0.5 block text-xs leading-snug',
                                        'text-blue-100' => $tautan['utama'],
                                        'text-gray-500' => ! $tautan['utama'],
                                    ])>{{ $tautan['keterangan'] }}</span>
                                </span>

                                <svg @class([
                                        'size-5 shrink-0',
                                        'text-blue-200' => $tautan['utama'],
                                        'text-gray-300 group-hover:text-blue-500' => ! $tautan['utama'],
                                     ])
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m9 5 7 7-7 7" />
                                </svg>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </main>

        {{-- Kaki --}}
        <footer class="mt-10 text-center">
            @if (! empty($sosial))
                <div class="flex items-center justify-center gap-3">
                    @foreach ($sosial as $tautan)
                        <a href="{{ $tautan['url'] }}"
                           @if (! str_starts_with($tautan['url'], 'mailto:')) target="_blank" rel="noopener noreferrer" @endif
                           title="{{ $tautan['label'] }}"
                           class="bio-tombol flex size-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm hover:border-blue-300 hover:text-blue-700">
                            <span class="sr-only">{{ $tautan['label'] }}</span>
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="{{ $tautan['icon'] }}" />
                            </svg>
                        </a>
                    @endforeach
                </div>
            @endif

            <p class="mt-6 text-xs text-gray-500">
                &copy; {{ date('Y') }} Jakarta Global University
            </p>
            <a href="{{ route('landing') }}" class="mt-1 inline-block text-xs font-medium text-blue-700 hover:underline">
                Buka situs lengkapnya
            </a>
        </footer>
    </div>
</body>
</html>
