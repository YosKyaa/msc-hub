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

    <x-organisms.design-system />

    <style>
        /* Latar lembut yang tidak ikut menggulung bersama isinya. */
        .bio-latar {
            background:
                radial-gradient(60rem 40rem at 50% -10%, rgb(255 239 181) 0%, transparent 60%),
                radial-gradient(40rem 30rem at 90% 10%, rgb(255 247 220) 0%, transparent 55%),
                rgb(251 250 247);
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

<body class="bio-latar min-h-screen text-ink antialiased">
    {{-- max-w-md: selebar ponsel. Di layar lebar ia tetap satu kolom di
         tengah, karena menyebarnya tombol justru membuat halaman ini terbaca
         seperti menu biasa. --}}
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-5 py-10 sm:py-14">

        {{-- Kepala --}}
        <header class="text-center">
            <img src="{{ asset('img/jgu.png') }}"
                 alt="Jakarta Global University"
                 class="mx-auto h-16 w-auto">

            <h1 class="mt-5 text-2xl font-bold tracking-tight text-ink">MSC Hub</h1>
            <p class="mt-1 text-sm font-semibold text-sun-ink">Media &amp; Strategic Communications</p>
            <p class="mt-3 text-sm leading-relaxed text-ink/65">
                {{ config('msc.bio.tagline') }}
            </p>
        </header>

        {{-- Tautan --}}
        <main class="mt-9 flex-1 space-y-7">
            @foreach ($kelompok as $grup)
                <section>
                    <h2 class="px-1 text-xs font-semibold uppercase tracking-wider text-ink/60">
                        {{ $grup['judul'] }}
                    </h2>

                    @if ($grup['catatan'])
                        <p class="mt-1 px-1 text-xs text-ink/60">{{ $grup['catatan'] }}</p>
                    @endif

                    <div class="mt-3 space-y-3">
                        @foreach ($grup['tautan'] as $tautan)
                            <a href="{{ $tautan['url'] }}"
                               @class([
                                   'bio-tombol group flex items-center gap-4 rounded-2xl border px-4 py-4 shadow-sm',
                                   'border-sun-deep bg-sun text-ink hover:bg-sun-deep hover:shadow-lg hover:shadow-sun/30' => $tautan['utama'],
                                   'border-ink/10 bg-white text-ink hover:border-sun-deep/50 hover:shadow-md' => ! $tautan['utama'],
                               ])>
                                <span @class([
                                    'flex size-11 shrink-0 items-center justify-center rounded-xl',
                                    'bg-ink/10 text-ink' => $tautan['utama'],
                                    'bg-sun-tint text-sun-ink' => ! $tautan['utama'],
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
                                        'text-ink/70' => $tautan['utama'],
                                        'text-ink/60' => ! $tautan['utama'],
                                    ])>{{ $tautan['keterangan'] }}</span>
                                </span>

                                <svg @class([
                                        'size-5 shrink-0',
                                        'text-ink/50' => $tautan['utama'],
                                        'text-ink/25 group-hover:text-sun-ink' => ! $tautan['utama'],
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

            {{-- Kontak admin.
                 Orang yang bingung mengisi formulir lebih cepat tertolong
                 dengan bertanya kepada orang daripada membaca satu paragraf
                 lagi, jadi ketiganya disebut dengan nama. --}}
            @if (! empty($admin))
                <section>
                    <h2 class="px-1 text-xs font-semibold uppercase tracking-wider text-ink/60">
                        Butuh bantuan?
                    </h2>
                    <p class="mt-1 px-1 text-xs text-ink/60">Tanya langsung ke admin MSC.</p>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach ($admin as $orang)
                            <a href="{{ $orang['url'] }}"
                               @if (! str_starts_with($orang['url'], 'mailto:')) target="_blank" rel="noopener noreferrer" @endif
                               class="bio-tombol flex items-center gap-3 rounded-2xl border border-ink/10 bg-white px-4 py-3 shadow-sm hover:border-sun-deep/50 hover:shadow-md sm:flex-col sm:gap-2 sm:py-4 sm:text-center">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-sun-soft text-sm font-bold text-sun-ink">
                                    {{ $orang['inisial'] }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold leading-tight text-ink">{{ $orang['nama'] }}</span>
                                    <span class="mt-0.5 block text-xs text-ink/60">{{ $orang['via'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </main>

        {{-- Kaki --}}
        <footer class="mt-10 text-center">
            @if (filled(config('msc.bio.website')))
                <a href="{{ config('msc.bio.website') }}" target="_blank" rel="noopener noreferrer"
                   class="text-xs font-medium text-ink/65 hover:text-sun-ink hover:underline">
                    jgu.ac.id
                </a>
                <span class="px-1.5 text-xs text-gray-300">·</span>
            @endif
            <a href="{{ route('landing') }}" class="text-xs font-medium text-sun-ink hover:underline">
                Situs lengkap MSC Hub
            </a>

            <p class="mt-4 text-xs text-ink/60">
                &copy; {{ date('Y') }} Jakarta Global University
            </p>
        </footer>
    </div>
</body>
</html>
