{{--
    Daftar twibbon yang sedang dibuka. Kebanyakan orang langsung datang ke
    halaman satu twibbon lewat tautan yang dibagikan; halaman ini untuk yang
    datang dari bio atau beranda.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">

    <x-organisms.seo
        title="Twibbon Kegiatan JGU"
        description="Pasang twibbon kegiatan Jakarta Global University di foto Anda, langsung dari ponsel. Foto diproses di perangkat Anda dan tidak diunggah ke server." />

    <x-organisms.design-system />
</head>

<body class="min-h-screen bg-paper text-ink antialiased">
    <div class="mx-auto w-full max-w-5xl px-5 py-10 sm:py-14">
        <header class="text-center">
            <a href="{{ route('landing') }}" class="inline-block">
                <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="mx-auto h-14 w-auto">
            </a>
            <h1 class="mt-5 text-3xl font-bold tracking-tight">Twibbon Kegiatan</h1>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-ink/65">
                Pilih twibbon, pasang di foto Anda, lalu bagikan. Foto diproses di perangkat Anda sendiri dan tidak pernah diunggah.
            </p>
        </header>

        @if ($twibbons->isEmpty())
            <div class="kaca-terang mx-auto mt-10 max-w-md rounded-2xl p-8 text-center">
                <p class="font-semibold">Belum ada twibbon yang sedang dibuka.</p>
                <p class="mt-1 text-sm text-ink/60">Twibbon kegiatan akan muncul di sini saat dibuka panitia.</p>
            </div>
        @else
            <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($twibbons as $twibbon)
                    <li>
                        <a href="{{ $twibbon->publicUrl() }}"
                           class="kartu-angkat block overflow-hidden rounded-2xl border border-ink/10 bg-white focus:outline-none focus-visible:ring-4 focus-visible:ring-sun">
                            <div class="bg-sun-soft">
                                <img src="{{ $twibbon->frameRelativeUrl() }}"
                                     alt="Bingkai {{ $twibbon->title }}"
                                     class="h-auto w-full"
                                     loading="lazy"
                                     width="{{ $twibbon->frame_width ?: 1080 }}"
                                     height="{{ $twibbon->frame_height ?: 1080 }}">
                            </div>
                            <div class="p-5">
                                <h2 class="font-bold leading-snug">{{ $twibbon->title }}</h2>
                                @if ($twibbon->description)
                                    <p class="mt-1 line-clamp-2 text-sm text-ink/60">{{ $twibbon->description }}</p>
                                @endif
                                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-sun-ink">
                                    Pasang twibbon
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M6 12h12"/></svg>
                                </span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</body>
</html>
