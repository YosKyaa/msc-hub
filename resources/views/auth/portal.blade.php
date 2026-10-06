@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <div class="text-center">
        <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="mx-auto h-14 w-auto">
        <h1 class="mt-5 text-2xl font-bold text-ink sm:text-3xl">MSC Hub</h1>
        <p class="mt-1 text-sm text-ink/60">Layanan Media &amp; Peminjaman, Jakarta Global University</p>
        <p class="mt-7 text-xs font-semibold uppercase tracking-wider text-sun-ink">Masuk sebagai</p>
    </div>

    {{-- Dua pintu masuk saja. Satu untuk yang mengajukan, satu untuk yang
         memprosesnya; masing-masing punya cara masuk sendiri di baliknya. --}}
    <div class="mt-6 grid gap-4 sm:mt-8 sm:grid-cols-2">
        <a href="{{ route('google.redirect', ['redirect' => $redirect]) }}"
           class="kaca kartu-angkat group flex flex-col rounded-3xl p-6 text-center hover:border-sun-deep/50 hover:shadow-lg hover:shadow-sun/25 focus:outline-none focus:ring-2 focus:ring-sun-deep/40 sm:p-8">
            <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-sun-tint text-sun-ink transition group-hover:bg-sun group-hover:text-ink">
                <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="M12 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"/>
                    <path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>
            </span>

            <span class="mt-5 block text-lg font-semibold text-ink">Mahasiswa / Pengaju</span>
            <span class="mt-2 block text-sm leading-relaxed text-ink/65">
                Ajukan konten, pinjam alat, dan booking ruangan. Masuk dengan akun Google kampus Anda.
            </span>

            <span class="mt-6 inline-flex items-center justify-center gap-2 rounded-full bg-sun px-5 py-3 text-sm font-semibold text-ink transition group-hover:bg-sun-deep">
                <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.6v3h3.9c2.3-2.1 3.5-5.2 3.5-8.8Z"/>
                    <path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3c-1 .7-2.3 1.1-4 1.1-3.1 0-5.7-2.1-6.6-4.9H1.4v3.1A12 12 0 0 0 12 24Z"/>
                    <path fill="#FBBC05" d="M5.4 14.3a7.2 7.2 0 0 1 0-4.6V6.6H1.4a12 12 0 0 0 0 10.8l4-3.1Z"/>
                    <path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.4 6.6l4 3.1C6.3 6.9 8.9 4.8 12 4.8Z"/>
                </svg>
                Masuk dengan Google
            </span>

            <span class="mt-3 block text-xs text-ink/60">
                Gunakan email <span class="font-semibold text-ink/80">@jgu.ac.id</span> atau
                <span class="font-semibold text-ink/80">@student.jgu.ac.id</span>
            </span>
        </a>

        <a href="{{ route('filament.admin.auth.login') }}"
           class="kaca kartu-angkat group flex flex-col rounded-3xl p-6 text-center hover:border-ink/20 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-ink/25 sm:p-8">
            <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-paper-3 text-ink/70 transition group-hover:bg-ink group-hover:text-white">
                <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="M12 3 4 6v6c0 5 3.4 8.5 8 9 4.6-.5 8-4 8-9V6l-8-3Z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
            </span>

            <span class="mt-5 block text-lg font-semibold text-ink">Admin MSC</span>
            <span class="mt-2 block text-sm leading-relaxed text-ink/65">
                Proses pengajuan, kelola inventaris, dan terbitkan sertifikat lewat panel administrasi.
            </span>

            <span class="mt-6 inline-flex items-center justify-center gap-2 rounded-full bg-ink px-5 py-3 text-sm font-semibold text-white transition group-hover:bg-ink/85">
                Masuk ke Panel
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </span>

            <span class="mt-3 block text-xs text-ink/60">
                Khusus staf dengan email <span class="font-semibold text-ink/80">@jgu.ac.id</span>
            </span>
        </a>
    </div>

    <p class="mt-8 text-center text-sm text-ink/60">
        <a href="{{ route('landing') }}" class="font-semibold text-sun-ink underline-offset-4 hover:underline">
            Kembali ke beranda
        </a>
    </p>
@endsection
