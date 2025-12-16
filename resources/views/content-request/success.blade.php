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
        
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Request Berhasil Dikirim!</h1>
        <p class="text-gray-500 mb-6">Permintaan konten Anda telah diterima dan akan segera diproses oleh tim MSC.</p>
        
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <div class="text-sm text-gray-500 mb-1">Kode Request Anda</div>
            <div class="text-2xl font-mono font-bold text-blue-600">{{ $code }}</div>
        </div>
        
        <p class="text-sm text-gray-500 mb-6">
            Simpan kode ini untuk memantau status request Anda.
        </p>
        
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('request.status') }}" 
               class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                Cek Status
            </a>
            <a href="{{ route('request.content') }}" 
               class="px-6 py-2 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition">
                Buat Request Baru
            </a>
        </div>
    </div>
</div>
@endsection
