@extends('layouts.public')

@section('title', 'Request Konten')

@section('content')
<div class="space-y-6">
    {{-- Page Header --}}
    <div class="text-center">
        <h1 class="text-2xl font-bold text-gray-900">Request Konten MSC</h1>
        <p class="text-gray-500 mt-1">Ajukan permintaan pembuatan konten ke tim Media & Strategic Communications</p>
    </div>

    @if(!$requester)
        {{-- Login Required --}}
        <div class="bg-white rounded-xl shadow-sm border p-8 text-center">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Login Diperlukan</h2>
            <p class="text-gray-500 mb-6">Silakan login dengan akun Google JGU Anda untuk melanjutkan.</p>
            
            <a href="{{ route('auth.google.redirect', ['redirect' => route('request.content')]) }}" 
               class="inline-flex items-center gap-3 px-6 py-3 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm">
                <svg class="w-5 h-5" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span class="font-medium text-gray-700">Login dengan Google</span>
            </a>
            
            <p class="text-xs text-gray-400 mt-4">
                Hanya email @jgu.ac.id atau @student.jgu.ac.id yang diperbolehkan
            </p>
        </div>
    @else
        {{-- Request Form --}}
        <form action="{{ route('request.content.submit') }}" method="POST" class="space-y-6">
            @csrf
            
            {{-- Requester Info --}}
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="font-semibold text-gray-900 mb-4">Informasi Pemohon</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                        <input type="text" name="requester_name" value="{{ old('requester_name', $requester['name']) }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @error('requester_name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" value="{{ $requester['email'] }}" readonly
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Pemohon *</label>
                        <select name="requester_type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @foreach($requesterTypes as $type)
                                <option value="{{ $type->value }}" {{ old('requester_type', $requester['type']) === $type->value ? 'selected' : '' }}>
                                    {{ $type->getLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit/Fakultas/Prodi</label>
                        <input type="text" name="unit" value="{{ old('unit') }}" placeholder="Contoh: Fakultas Teknik"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon/WA</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>
            
            {{-- Request Details --}}
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="font-semibold text-gray-900 mb-4">Detail Permintaan</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Konten *</label>
                        <select name="content_type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Pilih Jenis Konten --</option>
                            @foreach($contentTypes as $type)
                                <option value="{{ $type->value }}" {{ old('content_type') === $type->value ? 'selected' : '' }}>
                                    {{ $type->getLabel() }}
                                </option>
                            @endforeach
                        </select>
                        @error('content_type')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Platform Target</label>
                        <input type="text" name="platform_target" value="{{ old('platform_target') }}" 
                               placeholder="Instagram, YouTube, Website, dll"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Event</label>
                        <input type="date" name="event_date" value="{{ old('event_date') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
                        <input type="text" name="location" value="{{ old('location') }}" placeholder="Lokasi event"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deadline *</label>
                        <input type="date" name="deadline" value="{{ old('deadline') }}" required min="{{ date('Y-m-d') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @error('deadline')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Target Audience</label>
                        <input type="text" name="audience" value="{{ old('audience') }}" 
                               placeholder="Mahasiswa, Dosen, Umum, dll"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tujuan Konten</label>
                        <textarea name="purpose" rows="2" placeholder="Jelaskan tujuan pembuatan konten ini"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('purpose') }}</textarea>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Link Materi Pendukung</label>
                        <input type="url" name="materials_link" value="{{ old('materials_link') }}" 
                               placeholder="https://drive.google.com/..."
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-400 mt-1">Upload materi ke Google Drive dan share linknya di sini</p>
                        @error('materials_link')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Tambahan</label>
                        <textarea name="notes" rows="3" placeholder="Informasi tambahan yang perlu diketahui tim MSC"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
            
            {{-- Submit --}}
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">* Wajib diisi</p>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    Kirim Request
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
