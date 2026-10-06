<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">
    <meta name="theme-color" content="#FBFAF7">

    <x-organisms.seo
        description="Ajukan pembuatan konten, pinjam ruangan dan alat multimedia, serta verifikasi sertifikat kegiatan di Jakarta Global University. Satu portal untuk seluruh layanan Media & Strategic Communications."
        :canonical="route('landing')" />

    {{-- Data terstruktur: memberi tahu mesin pencari bahwa halaman ini milik
         sebuah unit di dalam Jakarta Global University, sehingga hasil
         pencariannya tidak hanya berupa satu baris tautan. --}}
    @php
        $org = config('msc.seo.organization');

        $dataTerstruktur = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/').'#organisasi',
                    'name' => $org['name'],
                    'alternateName' => $org['short_name'],
                    'url' => url('/'),
                    'logo' => asset('img/jgu.png'),
                    'email' => config('msc.contact_email'),
                    'parentOrganization' => [
                        '@type' => 'CollegeOrUniversity',
                        'name' => 'Jakarta Global University',
                    ],
                    'address' => [
                        '@type' => 'PostalAddress',
                        'streetAddress' => $org['street'],
                        'addressLocality' => $org['city'],
                        'addressRegion' => $org['region'],
                        'postalCode' => $org['postal_code'],
                        'addressCountry' => $org['country'],
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/').'#situs',
                    'name' => config('msc.seo.site_name'),
                    'url' => url('/'),
                    'inLanguage' => 'id-ID',
                    'publisher' => ['@id' => url('/').'#organisasi'],
                ],
            ],
        ];
    @endphp

    <script type="application/ld+json">{!! json_encode($dataTerstruktur, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        inter: ['Inter', 'sans-serif'],
                        playfair: ['Playfair Display', 'serif'],
                    },
                    colors: {
                        ink: '#15151A',
                        paper: '#FBFAF7',
                        'paper-2': '#F5F3ED',
                        'paper-3': '#EDEAE1',
                        sun: '#FFD84D',
                        'sun-deep': '#E8BE1F',
                        // Kuning sebagai tulisan tidak terbaca di atas
                        // kertas. Untuk teks dipakai amber gelap ini, yang
                        // memberi rasio 5,4:1 terhadap latar — cukup lapang
                        // untuk label kecil berhuruf kapital, yang paling
                        // dulu menyulitkan mata.
                        'sun-ink': '#8A6000',
                        // Kuning lembut untuk bidang yang luas. Kuning penuh
                        // selebar layar melelahkan mata; yang ini cukup untuk
                        // menandai bagian tanpa menyilaukan.
                        'sun-soft': '#FFF7DC',
                        'sun-tint': '#FFEFB5',
                    },
                },
            },
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; background: #FBFAF7; }
        .font-playfair { font-family: 'Playfair Display', serif; }
        html { scroll-behavior: smooth; }

        /* Kaca.
           Di latar terang kaca hanya terbaca bila ada warna di belakangnya:
           putih di atas putih tidak terlihat buram, hanya terlihat putih.
           Karena itu tiap panel kaca berdiri di atas noda kuning yang sengaja
           ditaruh di belakangnya. */
        .kaca {
            background: rgba(255, 255, 255, .62);
            border: 1px solid rgba(21, 21, 26, .07);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            box-shadow: 0 1px 2px rgba(21, 21, 26, .04);
        }
        .kaca-terang {
            background: rgba(255, 255, 255, .78);
            border: 1px solid rgba(21, 21, 26, .09);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px -12px rgba(21, 21, 26, .12);
        }

        /* Sorotan kuning yang mengikuti kursor di hero. Hanya hiasan, jadi
           seluruhnya pointer-events:none dan mati di layar sentuh. */
        .sorot {
            background: radial-gradient(30rem 30rem at var(--x, 50%) var(--y, 30%), rgba(255, 216, 77, .38), transparent 68%);
        }

        /* Muncul saat tergulung ke dalam layar. Keadaan awalnya dipasang
           lewat kelas, bukan lewat JavaScript, supaya tidak ada kedipan
           sebelum skripnya sempat berjalan. */
        .reveal { opacity: 0; transform: translateY(18px); transition: opacity .6s ease, transform .6s ease; }
        .reveal.tampil { opacity: 1; transform: none; }

        .kartu-angkat { transition: transform .25s ease, border-color .25s ease, background-color .25s ease; }
        .kartu-angkat:hover { transform: translateY(-4px); }

        /* Garis berjalan di pemisah bagian. */
        @keyframes geser { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        .berjalan { animation: geser 28s linear infinite; }

        /* Seluruh gerak dimatikan bila penggunanya memang memintanya. */
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .reveal { opacity: 1; transform: none; transition: none; }
            .kartu-angkat:hover { transform: none; }
            .berjalan { animation: none; }
        }

        /* Scrollbar gelap, supaya tepi halamannya tidak memutih. */
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #F5F3ED; }
        ::-webkit-scrollbar-thumb { background: #D6D1C4; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #C2BCAB; }
    </style>
</head>

<body class="bg-paper text-ink antialiased selection:bg-sun selection:text-ink">

@php
    // Satu label untuk seluruh ajakan di halaman ini. Sebelumnya tiap tombol
    // memakai nama layanan yang berbeda padahal semuanya menuju pintu yang
    // sama, dan pengunjung mengira ketiganya membawa ke tempat berbeda.
    $ajakan = $requester ? 'Buka Dasbor Saya' : 'Masuk untuk Mengajukan';
    $tujuanAjakan = $requester ? route('requester.dashboard') : route('login.portal');
@endphp

{{-- ───────────────────────── Navigasi ───────────────────────── --}}
<header x-data="{ buka: false, turun: false }"
        @scroll.window="turun = window.pageYOffset > 24"
        class="fixed inset-x-0 top-0 z-50 transition-colors duration-300"
        :class="turun || buka ? 'bg-paper/80 backdrop-blur-xl border-b border-ink/[.07]' : 'bg-transparent'">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="flex h-16 items-center justify-between lg:h-20">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="h-8 w-auto lg:h-9">
                <span class="hidden sm:block">
                    <span class="block text-sm font-semibold leading-tight">MSC Hub</span>
                    <span class="block text-[11px] leading-tight text-ink/60">Jakarta Global University</span>
                </span>
            </a>

            <nav class="hidden items-center gap-8 lg:flex" aria-label="Navigasi beranda">
                @foreach ([
                    '#layanan' => 'Layanan',
                    '#alur' => 'Cara Kerja',
                    '#karya' => 'Karya',
                    '#tanya' => 'Tanya Jawab',
                ] as $anchor => $label)
                    <a href="{{ $anchor }}" class="text-sm text-ink/65 transition hover:text-ink">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-3">
                <a href="{{ $tujuanAjakan }}"
                   class="hidden rounded-full bg-sun px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-sun-deep sm:inline-flex">
                    {{ $ajakan }}
                </a>

                <button @click="buka = !buka" type="button"
                        class="rounded-lg p-2 text-ink/70 transition hover:bg-ink/5 lg:hidden"
                        :aria-expanded="buka" aria-controls="menu-ponsel" aria-label="Buka menu">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path x-show="!buka" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                        <path x-show="buka" x-cloak stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div id="menu-ponsel" x-show="buka" x-cloak x-collapse class="border-t border-ink/[.07] bg-paper/95 backdrop-blur-xl lg:hidden">
        <nav class="space-y-1 px-4 py-4" aria-label="Navigasi ponsel">
            @foreach ([
                '#layanan' => 'Layanan',
                '#alur' => 'Cara Kerja',
                '#karya' => 'Karya',
                '#tanya' => 'Tanya Jawab',
            ] as $anchor => $label)
                <a href="{{ $anchor }}" @click="buka = false"
                   class="block rounded-lg px-3 py-3 text-sm text-ink/70 transition hover:bg-ink/5 hover:text-ink">{{ $label }}</a>
            @endforeach

            <a href="{{ $tujuanAjakan }}"
               class="mt-2 block rounded-full bg-sun px-4 py-3 text-center text-sm font-semibold text-ink">
                {{ $ajakan }}
            </a>
        </nav>
    </div>
</header>

<main>

{{-- ═════════════════ PERHATIAN ═════════════════
     Yang harus terjawab dalam tiga detik: ini apa, untuk siapa, dan apa
     yang saya tekan. Satu ajakan saja, karena seluruh layanan tetap
     melewati pintu masuk yang sama. --}}
<section id="beranda" class="relative overflow-hidden pt-28 lg:pt-36"
         x-data="{ x: '50%', y: '30%' }"
         @pointermove="x = $event.offsetX + 'px'; y = $event.offsetY + 'px'">

    <div class="sorot pointer-events-none absolute inset-0" :style="`--x:${x}; --y:${y}`"></div>
    <div class="pointer-events-none absolute -left-32 top-10 size-96 rounded-full bg-sun/40 blur-[110px]"></div>
    <div class="pointer-events-none absolute -right-20 top-40 size-80 rounded-full bg-amber-200/50 blur-[110px]"></div>

    <div class="relative mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:pb-28">
        <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-16">

            <div class="lg:col-span-7">
                <div class="kaca reveal inline-flex items-center gap-2.5 rounded-full px-4 py-2">
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-sun opacity-60"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-sun"></span>
                    </span>
                    <span class="text-xs font-medium text-ink/70">Portal layanan resmi MSC</span>
                </div>

                <h1 class="reveal mt-7 text-4xl font-bold leading-[1.08] tracking-tight sm:text-5xl lg:text-[4.2rem]">
                    Ajukan sekali.<br>
                    <span class="relative inline-block">
                        <span class="relative z-10">Pantau sampai selesai.</span>
                        <span class="absolute inset-x-0 bottom-1.5 z-0 h-3.5 bg-sun sm:bottom-2 sm:h-5"></span>
                    </span>
                </h1>

                <p class="reveal mt-7 max-w-xl text-base leading-relaxed text-ink/60 sm:text-lg">
                    Minta dibuatkan konten, pinjam ruangan dan alat, atau ambil sertifikat kegiatan Anda.
                    Semuanya lewat satu portal, tanpa perlu mengejar siapa pun lewat chat.
                </p>

                <div class="reveal mt-9 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <a href="{{ $tujuanAjakan }}"
                       class="kartu-angkat group inline-flex items-center justify-center gap-2.5 rounded-full bg-sun px-7 py-4 text-base font-semibold text-ink shadow-[0_10px_40px_-10px_rgba(255,216,77,.55)] hover:bg-sun-deep">
                        {{ $ajakan }}
                        <svg class="size-4 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14m-6-6 6 6-6 6" />
                        </svg>
                    </a>

                    <a href="#alur" class="inline-flex items-center justify-center gap-2 px-2 py-2 text-sm font-medium text-ink/65 transition hover:text-ink">
                        Lihat cara kerjanya
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </a>
                </div>

                @unless ($requester)
                    <p class="reveal mt-5 text-sm text-ink/60">
                        Masuk dengan akun <span class="font-medium text-ink/75">@jgu.ac.id</span> atau
                        <span class="font-medium text-ink/75">@student.jgu.ac.id</span>. Tidak perlu daftar.
                    </p>
                @endunless
            </div>

            {{-- Angka nyata, bukan hiasan. Dihitung dari basis data lewat
                 App\Support\LayananStatistik. --}}
            <div class="reveal lg:col-span-5">
                <div class="kaca-terang rounded-3xl p-6 sm:p-7">
                    <p class="text-xs font-semibold uppercase tracking-wider text-sun-ink">MSC dalam angka</p>
                    <div class="mt-5 grid grid-cols-2 gap-5">
                        @foreach ($statistik as $angka)
                            <div class="border-l-[3px] border-sun pl-4">
                                <div class="text-3xl font-bold leading-none sm:text-4xl">{{ $angka['angka'] }}</div>
                                <div class="mt-2 text-sm font-medium text-ink/80">{{ $angka['label'] }}</div>
                                <div class="mt-0.5 text-xs leading-snug text-ink/60">{{ $angka['catatan'] }}</div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-6 border-t border-ink/[.08] pt-4 text-xs text-ink/60">
                        Dihitung langsung dari data layanan, diperbarui tiap 30 menit.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Pemisah berjalan. Murni hiasan, jadi disembunyikan dari pembaca layar. --}}
    <div class="overflow-hidden border-y border-ink/[.07] bg-paper-2 py-3.5" aria-hidden="true">
        <div class="berjalan flex w-max items-center gap-10 whitespace-nowrap text-sm font-medium text-ink/60">
            @for ($i = 0; $i < 2; $i++)
                @foreach (['Pengajuan Konten', 'Booking Ruangan', 'Peminjaman Alat', 'Sertifikat Digital', 'Absensi QR', 'Verifikasi Keaslian'] as $kata)
                    <span class="flex items-center gap-10">
                        {{ $kata }}
                        <span class="text-sun-deep">✦</span>
                    </span>
                @endforeach
            @endfor
        </div>
    </div>
</section>

{{-- ═════════════════ MINAT ═════════════════
     Pengunjung sudah tahu ini portal apa. Sekarang: saya boleh minta apa
     saja di sini? --}}
<section id="layanan" class="relative py-20 lg:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="reveal max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-wider text-sun-ink">Yang bisa Anda ajukan</p>
            <h2 class="mt-4 text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                Tiga layanan, <span class="font-playfair italic text-sun-ink">satu</span> pintu masuk
            </h2>
            <p class="mt-5 text-base leading-relaxed text-ink/65">
                Semuanya dikerjakan tim MSC dan bisa Anda pantau sendiri. Melayani sivitas JGU adalah tugas kami.
            </p>
        </div>

        <div class="mt-14 grid gap-5 md:grid-cols-3">
            @foreach ([
                [
                    'judul' => 'Pengajuan Konten',
                    'ringkas' => 'Foto, video, desain poster, feed media sosial, dan kebutuhan publikasi lainnya.',
                    'hasil' => 'Anda terima filenya, lengkap dengan riwayat revisinya.',
                    'ikon' => 'M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586Z',
                ],
                [
                    'judul' => 'Booking Ruangan',
                    'ringkas' => 'Studio, ruang rapat, dan ruang produksi milik MSC.',
                    'hasil' => 'Jadwal terkunci atas nama Anda, bentrok dicegah otomatis.',
                    'ikon' => 'M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4',
                ],
                [
                    'judul' => 'Peminjaman Alat',
                    'ringkas' => 'Kamera, lensa, tripod, lighting, audio, dan perangkat produksi lain.',
                    'hasil' => 'Formulir peminjaman resmi siap cetak, tanpa mengetik ulang.',
                    'ikon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                ],
            ] as $layanan)
                <article class="kaca kartu-angkat reveal group rounded-3xl p-7 hover:border-sun-deep/50 hover:shadow-lg hover:shadow-sun/20">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-sun/30 text-sun-ink transition group-hover:bg-sun group-hover:text-ink">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $layanan['ikon'] }}" />
                        </svg>
                    </span>

                    <h3 class="mt-6 text-xl font-semibold">{{ $layanan['judul'] }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-ink/65">{{ $layanan['ringkas'] }}</p>

                    <p class="mt-5 flex items-start gap-2.5 border-t border-ink/[.08] pt-5 text-sm text-ink/75">
                        <svg class="mt-0.5 size-4 shrink-0 text-sun-ink" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                             stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m5 13 4 4L19 7" />
                        </svg>
                        {{ $layanan['hasil'] }}
                    </p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═════════════════ KEINGINAN ═════════════════
     Kekhawatiran terbesar orang yang mengajukan ke unit kampus: "nanti
     diproses tidak ya, saya tahu dari mana?" Alur ini menjawabnya. --}}
<section id="alur" class="relative overflow-hidden border-y border-sun-deep/25 bg-sun-soft py-20 lg:py-28">
    <div class="pointer-events-none absolute -right-20 top-0 size-80 rounded-full bg-sun/55 blur-[100px]"></div>
    <div class="pointer-events-none absolute -left-24 bottom-0 size-72 rounded-full bg-amber-200/60 blur-[100px]"></div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6">
        <div class="reveal max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-wider text-sun-ink">Cara kerjanya</p>
            <h2 class="mt-4 text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                Tiga langkah, dan Anda tahu persis posisinya
            </h2>
            <p class="mt-5 text-base leading-relaxed text-ink/65">
                Tidak ada pengajuan yang hilang di tengah jalan. Setiap perubahan status dikabarkan lewat email.
            </p>
        </div>

        <div class="mt-14 grid gap-5 lg:grid-cols-3">
            @foreach ([
                [
                    'no' => '01',
                    'judul' => 'Masuk dengan akun JGU',
                    'isi' => 'Cukup sekali klik lewat Google. Identitas Anda terbaca otomatis, jadi tidak ada formulir pendaftaran.',
                ],
                [
                    'no' => '02',
                    'judul' => 'Isi pengajuan Anda',
                    'isi' => 'Formulirnya menuntun, dan setiap isian menjelaskan dirinya. Nomor pengajuan langsung Anda terima.',
                ],
                [
                    'no' => '03',
                    'judul' => 'Pantau sampai selesai',
                    'isi' => 'Status terbaca kapan saja di dasbor. Tim MSC meninjau, lalu hasilnya dikabarkan ke email Anda.',
                ],
            ] as $langkah)
                <div class="reveal relative rounded-3xl border border-sun-deep/25 bg-white/80 p-7 shadow-sm backdrop-blur-sm">
                    <span class="font-playfair text-5xl italic leading-none text-sun-ink">{{ $langkah['no'] }}</span>
                    <h3 class="mt-5 text-lg font-semibold">{{ $langkah['judul'] }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-ink/65">{{ $langkah['isi'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="reveal mt-10 flex flex-col items-start gap-5 rounded-3xl border border-sun-deep/25 bg-white/85 p-7 shadow-sm backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-sun text-ink">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold">Ajukan jauh-jauh hari</p>
                    <p class="mt-1 text-sm leading-relaxed text-ink/65">
                        Peninjauan memakan 1–3 hari kerja, dan produksinya menyusul sesudah itu.
                        Untuk kebutuhan bertanggal, ajukan minimal seminggu sebelumnya.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

@if ($prestasi->isNotEmpty())
    {{-- Rekam jejak.
         Ditaruh tepat sesudah "cara kerjanya" karena urutannya memang begitu:
         orang baru saja membaca bagaimana pengajuannya diproses, lalu ingin
         tahu seberapa bisa dipercaya yang memprosesnya. --}}
    <section id="prestasi" class="relative overflow-hidden py-20 lg:py-28">
        <div class="pointer-events-none absolute -left-24 top-24 size-80 rounded-full bg-sun/30 blur-[110px]"></div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6">
            <div class="reveal max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-wider text-sun-ink">Rekam jejak</p>
                <h2 class="mt-4 text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                    Penghargaan yang pernah kami bawa pulang
                </h2>
                <p class="mt-5 text-base leading-relaxed text-ink/65">
                    Tim yang mengerjakan pengajuan Anda adalah tim yang sama yang meraih ini.
                </p>
            </div>

            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($prestasi as $item)
                    <article class="kartu-angkat reveal group overflow-hidden rounded-3xl border border-sun-deep/25 bg-white/80 shadow-sm backdrop-blur-sm hover:border-sun-deep/60 hover:shadow-lg hover:shadow-sun/25">
                        @if ($item->image)
                            <div class="aspect-[4/3] overflow-hidden bg-paper-3">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->image) }}"
                                     alt="{{ $item->title }}" loading="lazy"
                                     class="size-full object-cover transition duration-500 group-hover:scale-105">
                            </div>
                        @else
                            {{-- Prestasi yang belum sempat difoto tetap layak
                                 disebut, jadi lambang jenisnya yang berdiri
                                 di tempat fotonya. --}}
                            <div class="flex aspect-[16/7] items-center justify-center bg-sun-tint">
                                <svg class="size-14 text-sun-ink" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                     stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="{{ $item->category->icon() }}" />
                                </svg>
                            </div>
                        @endif

                        <div class="p-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-sun px-2.5 py-1 text-[11px] font-semibold text-ink">
                                    {{ $item->category->getLabel() }}
                                </span>
                                @if ($item->level)
                                    <span class="rounded-full border border-sun-deep/35 px-2.5 py-1 text-[11px] font-semibold text-sun-ink">
                                        {{ $item->level }}
                                    </span>
                                @endif
                                @if ($item->year())
                                    <span class="text-[11px] font-medium text-ink/60">{{ $item->year() }}</span>
                                @endif
                            </div>

                            <h3 class="mt-3 text-base font-semibold leading-snug">{{ $item->title }}</h3>

                            @if ($item->awarded_by)
                                <p class="mt-2 text-sm text-ink/65">{{ $item->awarded_by }}</p>
                            @endif

                            @if ($item->description)
                                <p class="mt-3 line-clamp-2 text-sm leading-relaxed text-ink/60">{{ $item->description }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if ($featuredWorks->isNotEmpty())
    {{-- Bukti bahwa permintaan di sini memang berujung jadi sesuatu. --}}
    <section id="karya" class="py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="reveal flex flex-wrap items-end justify-between gap-6">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-wider text-sun-ink">Karya terpilih</p>
                    <h2 class="mt-4 text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                        Hasil dari pengajuan seperti milik Anda
                    </h2>
                </div>
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredWorks as $karya)
                    <article class="kaca kartu-angkat reveal group overflow-hidden rounded-3xl hover:border-sun-deep/50 hover:shadow-lg hover:shadow-sun/20">
                        <div class="aspect-[4/3] overflow-hidden bg-paper-3">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($karya->image) }}"
                                 alt="{{ $karya->title }}" loading="lazy"
                                 class="size-full object-cover transition duration-500 group-hover:scale-105">
                        </div>
                        <div class="p-6">
                            @if ($karya->category)
                                <span class="text-xs font-semibold uppercase tracking-wider text-sun-ink">{{ $karya->category }}</span>
                            @endif
                            <h3 class="mt-2 text-lg font-semibold leading-snug">{{ $karya->title }}</h3>
                            @if ($karya->client)
                                <p class="mt-1.5 text-sm text-ink/65">{{ $karya->client }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if ($announcementsPinned->isNotEmpty() || $announcementsLatest->isNotEmpty())
    <section class="border-y border-sun-deep/25 bg-sun-soft py-20 lg:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="reveal flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-sun-ink">Kabar terbaru</p>
                    <h2 class="mt-4 text-3xl font-bold leading-tight sm:text-4xl">Yang perlu Anda tahu</h2>
                </div>
                <a href="{{ route('announcements.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-sun-ink transition hover:gap-3">
                    Semua pengumuman
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14m-6-6 6 6-6 6" />
                    </svg>
                </a>
            </div>

            <div class="mt-12 grid gap-5 lg:grid-cols-3">
                @foreach ($announcementsPinned->concat($announcementsLatest)->take(3) as $pengumuman)
                    <a href="{{ route('announcements.show', $pengumuman->slug) }}"
                       class="kartu-angkat reveal block rounded-3xl border border-sun-deep/25 bg-white/85 p-7 shadow-sm backdrop-blur-sm hover:border-sun-deep/60 hover:shadow-lg hover:shadow-sun/25">
                        @if ($pengumuman->is_pinned)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-sun px-3 py-1 text-xs font-semibold text-ink">
                                Disematkan
                            </span>
                        @endif
                        <h3 class="mt-3 text-lg font-semibold leading-snug">{{ $pengumuman->title }}</h3>
                        <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-ink/65">{{ $pengumuman->summary }}</p>
                        @if ($pengumuman->published_at)
                            <p class="mt-5 text-xs text-ink/60">{{ $pengumuman->published_at->translatedFormat('d F Y') }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- Orang yang akan memproses pengajuannya. Untuk layanan kampus ini bukan
     hiasan: tahu siapa yang menangani membuat orang berani mengajukan. --}}
<section class="py-20 lg:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="reveal max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-wider text-sun-ink">Tim MSC</p>
            <h2 class="mt-4 text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                Pengajuan Anda sampai ke orang-orang ini
            </h2>
        </div>

        <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['nama' => 'Hadi Wijaya, S.ST, M.IT.', 'peran' => 'Ketua Departemen MSC', 'foto' => 'img/staff/hadi.png'],
                ['nama' => 'Chika Arzhika', 'peran' => 'Staf MSC', 'foto' => 'img/staff/chika.png'],
                ['nama' => 'Yosua Immanuel', 'peran' => 'Staf MSC', 'foto' => 'img/staff/yosua.png'],
            ] as $orang)
                <article class="kaca kartu-angkat reveal group overflow-hidden rounded-3xl hover:border-sun-deep/50 hover:shadow-lg hover:shadow-sun/20">
                    <div class="aspect-[4/5] overflow-hidden bg-paper-3">
                        <img src="{{ asset($orang['foto']) }}" alt="{{ $orang['nama'] }}" loading="lazy"
                             class="size-full object-cover transition duration-500 group-hover:scale-[1.03]">
                    </div>
                    <div class="p-6">
                        <h3 class="text-base font-semibold leading-snug">{{ $orang['nama'] }}</h3>
                        <p class="mt-1 text-sm font-medium text-sun-ink">{{ $orang['peran'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═════════════════ TINDAKAN ═════════════════
     Keberatan terakhir dibereskan dulu, baru ajakannya diulang. --}}
<section id="tanya" class="border-y border-ink/[.07] bg-paper-2 py-20 lg:py-28" x-data="{ aktif: 0 }">
    <div class="mx-auto max-w-4xl px-4 sm:px-6">
        <div class="reveal text-center">
            <p class="text-xs font-semibold uppercase tracking-wider text-sun-ink">Tanya jawab</p>
            <h2 class="mt-4 text-3xl font-bold leading-tight sm:text-4xl">Sebelum Anda mengajukan</h2>
        </div>

        <div class="mt-12 space-y-3">
            @foreach ([
                [
                    'Siapa yang boleh memakai layanan ini?',
                    'Seluruh sivitas Jakarta Global University yang punya akun @jgu.ac.id atau @student.jgu.ac.id: dosen, tenaga kependidikan, staf, dan mahasiswa. Tidak perlu mendaftar, cukup masuk dengan akun itu.',
                ],
                [
                    'Berapa lama pengajuan saya diproses?',
                    'Peninjauan memakan 1–3 hari kerja. Waktu produksinya menyesuaikan tingkat kerumitan. Untuk kebutuhan bertanggal, ajukan minimal seminggu sebelumnya agar tidak terburu-buru.',
                ],
                [
                    'Apa yang menjadi tanggung jawab saya?',
                    'Melayani sivitas JGU adalah bagian dari tugas MSC, jadi tidak ada yang perlu Anda urus soal itu. Yang menjadi tanggung jawab Anda hanya alat yang dipinjam: kerusakan dan kehilangan ditanggung peminjam.',
                ],
                [
                    'Alat apa saja yang bisa dipinjam?',
                    'Kamera DSLR dan mirrorless, lensa, tripod, gimbal, lighting kit, mikrofon, serta backdrop. Daftar lengkap beserta ketersediaannya terlihat di formulir peminjaman setelah Anda masuk.',
                ],
                [
                    'Bagaimana saya tahu pengajuan saya diproses?',
                    'Setiap perubahan status dikabarkan ke email Anda, dan posisinya bisa dilihat kapan saja di dasbor. Tidak ada pengajuan yang hilang tanpa kabar.',
                ],
                [
                    'Sertifikat kegiatan saya belum sampai, bagaimana?',
                    'Sertifikat dikirim ke email setelah panitia menerbitkannya. Bila belum sampai, periksa folder spam lebih dulu, lalu hubungi admin MSC lewat halaman bio kami.',
                ],
            ] as $i => $tanya)
                <div class="kaca reveal overflow-hidden rounded-2xl transition-colors" :class="aktif === {{ $i }} && 'border-sun-deep/60'">
                    <button type="button" @click="aktif = aktif === {{ $i }} ? null : {{ $i }}"
                            class="flex w-full items-center justify-between gap-5 px-6 py-5 text-left"
                            :aria-expanded="aktif === {{ $i }}">
                        <span class="text-base font-medium">{{ $tanya[0] }}</span>
                        <svg class="size-5 shrink-0 text-sun-ink transition duration-300"
                             :class="aktif === {{ $i }} && 'rotate-45'"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                    </button>
                    <div x-show="aktif === {{ $i }}" x-collapse x-cloak>
                        <p class="px-6 pb-6 text-sm leading-relaxed text-ink/60">{{ $tanya[1] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Ajakan penutup. Labelnya sama persis dengan yang di hero: pengulangan
     yang sama memperkuat, sedangkan nama yang berbeda membuat orang mengira
     keduanya menuju tempat berbeda. --}}
<section class="relative overflow-hidden bg-sun py-20 lg:py-28">
    {{-- Kuning penuh hanya di sini. Inilah momen paling nyaring halaman ini,
         dan satu-satunya tempat yang pantas menyandangnya. --}}
    <div class="pointer-events-none absolute -left-20 -top-20 size-80 rounded-full bg-white/35 blur-[100px]"></div>
    <div class="pointer-events-none absolute -bottom-24 -right-16 size-96 rounded-full bg-amber-300/50 blur-[110px]"></div>

    <div class="relative mx-auto max-w-4xl px-4 sm:px-6">
        <div class="reveal rounded-[2rem] border border-ink/10 bg-white/70 px-7 py-14 text-center shadow-[0_20px_60px_-25px_rgba(21,21,26,.25)] backdrop-blur-xl sm:px-14">
            <h2 class="text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                Siap mengajukan?
            </h2>
            <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-ink/60">
                Butuh kurang dari lima menit. Masuk dengan akun JGU Anda, pilih layanannya,
                lalu pantau sendiri sampai selesai.
            </p>

            <a href="{{ $tujuanAjakan }}"
               class="kartu-angkat group mt-9 inline-flex items-center gap-2.5 rounded-full bg-ink px-8 py-4 text-base font-semibold text-white shadow-[0_12px_40px_-12px_rgba(21,21,26,.5)] hover:bg-ink/85">
                {{ $ajakan }}
                <svg class="size-4 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14m-6-6 6 6-6 6" />
                </svg>
            </a>

            <p class="mt-6 text-sm text-ink/60">
                Melayani seluruh sivitas Jakarta Global University.
            </p>
        </div>
    </div>
</section>

</main>

<footer class="border-t border-sun-deep/25 bg-sun-soft">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <div class="grid gap-10 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="h-9 w-auto">
                    <span>
                        <span class="block text-sm font-semibold">MSC Hub</span>
                        <span class="block text-xs text-ink/60">Media &amp; Strategic Communications</span>
                    </span>
                </div>
                <p class="mt-5 max-w-sm text-sm leading-relaxed text-ink/65">
                    Portal layanan media dan peminjaman fasilitas untuk seluruh sivitas Jakarta Global University.
                </p>
                <a href="{{ route('bio') }}" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-sun-ink transition hover:gap-3">
                    Semua tautan MSC
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14m-6-6 6 6-6 6" />
                    </svg>
                </a>
            </div>

            <div>
                <h3 class="text-sm font-semibold">Layanan</h3>
                <ul class="mt-4 space-y-2.5 text-sm text-ink/65">
                    <li><a href="{{ route('request.content') }}" class="transition hover:text-sun-ink">Pengajuan Konten</a></li>
                    <li><a href="{{ route('booking.room') }}" class="transition hover:text-sun-ink">Booking Ruangan</a></li>
                    <li><a href="{{ route('booking.inventory') }}" class="transition hover:text-sun-ink">Peminjaman Alat</a></li>
                    <li><a href="{{ route('announcements.index') }}" class="transition hover:text-sun-ink">Pengumuman</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold">Kontak</h3>
                <ul class="mt-4 space-y-2.5 text-sm text-ink/65">
                    <li><a href="mailto:{{ config('msc.contact_email') }}" class="transition hover:text-sun-ink">{{ config('msc.contact_email') }}</a></li>
                    <li class="leading-relaxed">
                        Jl. Boulevard Grand Depok City<br>
                        Depok, Jawa Barat 16412
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-12 border-t border-ink/[.07] pt-7 text-center text-xs text-ink/60">
            &copy; {{ date('Y') }} Jakarta Global University, Media &amp; Strategic Communications
        </div>
    </div>
</footer>

<script defer src="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>

<script>
    // Memunculkan isi saat tergulung ke dalam layar.
    //
    // Dipasang sebagai kelas lewat IntersectionObserver, bukan dengan
    // menyimak peristiwa scroll: peramban mengerjakannya di luar thread
    // utama, jadi halamannya tetap mulus saat digulung cepat.
    (function () {
        var elemen = document.querySelectorAll('.reveal');

        // Bila peramban tidak mendukungnya — atau penggunanya memang meminta
        // gerakan dikurangi — semuanya langsung ditampilkan.
        if (!('IntersectionObserver' in window) ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            elemen.forEach(function (el) { el.classList.add('tampil'); });
            return;
        }

        var pengamat = new IntersectionObserver(function (entri) {
            entri.forEach(function (e) {
                if (!e.isIntersecting) return;
                e.target.classList.add('tampil');
                pengamat.unobserve(e.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });

        elemen.forEach(function (el, i) {
            // Jeda bertingkat hanya di dalam satu baris kartu; lebih dari itu
            // halamannya terasa lambat menyusul pengguna.
            el.style.transitionDelay = (Math.min(i % 4, 3) * 70) + 'ms';
            pengamat.observe(el);
        });
    })();
</script>

</body>
</html>
