@extends('layouts.public')

@section('title', 'Request Berhasil')

@section('content')
<div class="max-w-lg mx-auto text-center">
    <div class="bg-white rounded-xl shadow-sm border p-8">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        
        <h1 class="text-2xl font-bold text-ink mb-2">Request Berhasil Dikirim!</h1>
        <p class="text-ink/60 mb-6">Permintaan konten Anda telah diterima dan akan segera diproses oleh tim MSC.</p>
        
        <div class="bg-paper-2 rounded-lg p-4 mb-6" x-data="{ copied: false }">
            <div class="text-sm text-ink/60 mb-1">Kode Request Anda</div>
            <div class="flex items-center justify-center gap-3">
                <div class="text-2xl font-mono font-bold text-sun-ink" id="request-code">{{ $code }}</div>
                <button 
                    @click="navigator.clipboard.writeText('{{ $code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="p-2 rounded-lg hover:bg-gray-200 transition-colors"
                    :class="copied ? 'text-green-600' : 'text-ink/45 hover:text-ink/65'"
                    title="Salin kode">
                    <svg x-show="!copied" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <svg x-show="copied" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
            </div>
            <div x-show="copied" x-cloak x-transition class="text-sm text-green-600 mt-2">
                Kode berhasil disalin!
            </div>
        </div>
        
        <p class="text-sm text-ink/60 mb-6">
            Simpan kode ini untuk memantau status request Anda.
        </p>
        
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('request.status') }}" 
               class="px-6 py-2 bg-sun text-ink font-medium rounded-lg hover:bg-sun-deep transition">
                Cek Status
            </a>
            <a href="{{ route('request.content') }}" 
               class="px-6 py-2 bg-paper-3 text-ink/75 font-medium rounded-lg hover:bg-gray-200 transition">
                Buat Request Baru
            </a>
        </div>
    </div>
</div>
@endsection
