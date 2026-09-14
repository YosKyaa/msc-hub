@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <div class="text-center">
        <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="mx-auto h-14 w-auto">
        <h1 class="mt-5 text-2xl font-bold text-gray-900 sm:text-3xl">MSC Hub</h1>
        <p class="mt-1 text-sm text-gray-500">Layanan Media &amp; Peminjaman &mdash; Jakarta Global University</p>
        <p class="mt-6 text-sm font-medium text-gray-600">Masuk sebagai</p>
    </div>

    {{-- Dua pintu masuk saja. Satu untuk yang mengajukan, satu untuk yang
         memprosesnya; masing-masing punya cara masuk sendiri di baliknya. --}}
    <div class="mt-6 grid gap-4 sm:mt-8 sm:grid-cols-2">
        <a href="{{ route('google.redirect', ['redirect' => $redirect]) }}"
           class="group flex flex-col rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-400 sm:p-8">
            <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 transition group-hover:bg-blue-100">
                <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="M12 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"/>
                    <path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>
            </span>

            <span class="mt-5 block text-lg font-semibold text-gray-900">Mahasiswa / Pengaju</span>
            <span class="mt-2 block text-sm leading-relaxed text-gray-500">
                Ajukan konten, pinjam alat, dan booking ruangan. Masuk dengan akun Google kampus Anda.
            </span>

            <span class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition group-hover:bg-blue-700">
                <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#fff" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.6v3h3.9c2.3-2.1 3.5-5.2 3.5-8.8Z" opacity=".9"/>
                    <path fill="#fff" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3c-1 .7-2.3 1.1-4 1.1-3.1 0-5.7-2.1-6.6-4.9H1.4v3.1A12 12 0 0 0 12 24Z" opacity=".75"/>
                    <path fill="#fff" d="M5.4 14.3a7.2 7.2 0 0 1 0-4.6V6.6H1.4a12 12 0 0 0 0 10.8l4-3.1Z" opacity=".6"/>
                    <path fill="#fff" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.4 6.6l4 3.1C6.3 6.9 8.9 4.8 12 4.8Z" opacity=".85"/>
                </svg>
                Masuk dengan Google
            </span>

            <span class="mt-3 block text-xs text-gray-400">
                Gunakan email <span class="font-semibold text-gray-500">@jgu.ac.id</span> atau
                <span class="font-semibold text-gray-500">@student.jgu.ac.id</span>
            </span>
        </a>

        <a href="{{ route('filament.admin.auth.login') }}"
           class="group flex flex-col rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-400 sm:p-8">
            <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 transition group-hover:bg-indigo-100">
                <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="M12 3 4 6v6c0 5 3.4 8.5 8 9 4.6-.5 8-4 8-9V6l-8-3Z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
            </span>

            <span class="mt-5 block text-lg font-semibold text-gray-900">Admin MSC</span>
            <span class="mt-2 block text-sm leading-relaxed text-gray-500">
                Proses pengajuan, kelola inventaris, dan terbitkan sertifikat lewat panel administrasi.
            </span>

            <span class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition group-hover:bg-indigo-700">
                Masuk ke Panel
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </span>

            <span class="mt-3 block text-xs text-gray-400">
                Khusus staf dengan email <span class="font-semibold text-gray-500">@jgu.ac.id</span>
            </span>
        </a>
    </div>

    <p class="mt-8 text-center text-sm text-gray-500">
        <a href="{{ route('landing') }}" class="font-medium text-gray-600 underline-offset-2 hover:underline">
            Kembali ke beranda
        </a>
    </p>
@endsection
