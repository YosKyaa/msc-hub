@extends('layouts.public')

@section('title', 'Cek Status Request')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Cek Status Request</h1>
        <p class="text-gray-500 mt-1">Masukkan kode request untuk melihat status</p>
    </div>

    @if(!$requester)
        {{-- Login Required --}}
        <div class="bg-white rounded-xl shadow-sm border p-8 text-center">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Login Diperlukan</h2>
            <p class="text-gray-500 mb-6">Silakan login untuk melihat status request Anda.</p>
            
            <a href="{{ route('auth.google.redirect', ['redirect' => route('request.status')]) }}" 
               class="inline-flex items-center gap-3 px-6 py-3 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm">
                <svg class="w-5 h-5" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span class="font-medium text-gray-700">Login dengan Google</span>
            </a>
        </div>
    @else
        {{-- Check Status Form --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <form action="{{ route('request.status.check') }}" method="POST" class="space-y-4">
                @csrf
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kode Request</label>
                    <input type="text" name="request_code" value="{{ old('request_code') }}" 
                           placeholder="CR-2025-0001" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-center text-lg font-mono uppercase">
                    @error('request_code')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <button type="submit" 
                        class="w-full px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    Cek Status
                </button>
            </form>
        </div>
        
        <p class="text-center text-sm text-gray-500 mt-4">
            Anda hanya dapat melihat request yang dibuat dengan email {{ $requester['email'] }}
        </p>
    @endif
</div>
@endsection
