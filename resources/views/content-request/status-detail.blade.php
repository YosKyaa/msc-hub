@extends('layouts.public')

@section('title', 'Status Request ' . $contentRequest->request_code)

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('request.status') }}" class="text-sm text-blue-600 hover:underline">&larr; Kembali</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $contentRequest->request_code }}</h1>
        </div>
        <div class="px-4 py-2 rounded-full text-sm font-medium
            @switch($contentRequest->status->value)
                @case('incoming') bg-blue-100 text-blue-700 @break
                @case('assigned') bg-purple-100 text-purple-700 @break
                @case('in_progress') bg-yellow-100 text-yellow-700 @break
                @case('need_revision') bg-red-100 text-red-700 @break
                @case('waiting_head_approval') bg-orange-100 text-orange-700 @break
                @case('approved') bg-green-100 text-green-700 @break
                @case('rejected') bg-red-100 text-red-700 @break
                @case('published') bg-green-100 text-green-700 @break
                @case('archived') bg-gray-100 text-gray-700 @break
                @default bg-gray-100 text-gray-700
            @endswitch">
            {{ $contentRequest->status->getLabel() }}
        </div>
    </div>

    {{-- Request Details --}}
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Detail Request</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-gray-500">Jenis Konten</div>
                <div class="font-medium">{{ $contentRequest->content_type->getLabel() }}</div>
            </div>
            <div>
                <div class="text-gray-500">Platform Target</div>
                <div class="font-medium">{{ $contentRequest->platform_target ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Tanggal Event</div>
                <div class="font-medium">{{ $contentRequest->event_date?->format('d M Y') ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Deadline</div>
                <div class="font-medium {{ $contentRequest->deadline && $contentRequest->deadline->isPast() ? 'text-red-600' : '' }}">
                    {{ $contentRequest->deadline?->format('d M Y') ?? '-' }}
                </div>
            </div>
            <div>
                <div class="text-gray-500">Lokasi</div>
                <div class="font-medium">{{ $contentRequest->location ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-500">PIC</div>
                <div class="font-medium">{{ $contentRequest->assignedTo?->name ?? 'Belum ditentukan' }}</div>
            </div>
            @if($contentRequest->purpose)
            <div class="md:col-span-2">
                <div class="text-gray-500">Tujuan</div>
                <div class="font-medium">{{ $contentRequest->purpose }}</div>
            </div>
            @endif
            @if($contentRequest->notes)
            <div class="md:col-span-2">
                <div class="text-gray-500">Catatan</div>
                <div class="font-medium">{{ $contentRequest->notes }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Timeline --}}
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Timeline</h2>
        
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </div>
                <div>
                    <div class="font-medium text-gray-900">Request Dibuat</div>
                    <div class="text-sm text-gray-500">{{ $contentRequest->created_at->format('d M Y H:i') }}</div>
                </div>
            </div>
            
            @if($contentRequest->assigned_to_user_id)
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <div class="font-medium text-gray-900">Ditugaskan ke {{ $contentRequest->assignedTo->name }}</div>
                </div>
            </div>
            @endif
            
            @if($contentRequest->staff_approved_at)
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <div class="font-medium text-gray-900">Disetujui Staff MSC</div>
                    <div class="text-sm text-gray-500">{{ $contentRequest->staff_approved_at->format('d M Y H:i') }}</div>
                </div>
            </div>
            @endif
            
            @if($contentRequest->head_approved_at)
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="font-medium text-gray-900">Disetujui Head MSC</div>
                    <div class="text-sm text-gray-500">{{ $contentRequest->head_approved_at->format('d M Y H:i') }}</div>
                </div>
            </div>
            @endif
            
            @if($contentRequest->rejected_at)
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <div>
                    <div class="font-medium text-gray-900">Ditolak</div>
                    <div class="text-sm text-gray-500">{{ $contentRequest->rejected_at->format('d M Y H:i') }}</div>
                    @if($contentRequest->reject_reason)
                    <div class="text-sm text-red-600 mt-1">Alasan: {{ $contentRequest->reject_reason }}</div>
                    @endif
                </div>
            </div>
            @endif
            
            @if($contentRequest->published_at)
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
                </div>
                <div>
                    <div class="font-medium text-gray-900">Dipublikasi</div>
                    <div class="text-sm text-gray-500">{{ $contentRequest->published_at->format('d M Y H:i') }}</div>
                    @if($contentRequest->published_link)
                    <a href="{{ $contentRequest->published_link }}" target="_blank" class="text-sm text-blue-600 hover:underline">
                        Lihat Hasil →
                    </a>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Comments --}}
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Komentar</h2>
        
        @if($contentRequest->comments->count() > 0)
        <div class="space-y-4 mb-6">
            @foreach($contentRequest->comments as $comment)
            <div class="flex gap-3 {{ $comment->author_type->value === 'requester' ? '' : 'bg-gray-50 p-3 rounded-lg' }}">
                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0
                    {{ $comment->author_type->value === 'requester' ? 'bg-blue-100' : ($comment->author_type->value === 'head' ? 'bg-green-100' : 'bg-purple-100') }}">
                    <span class="text-xs font-medium 
                        {{ $comment->author_type->value === 'requester' ? 'text-blue-600' : ($comment->author_type->value === 'head' ? 'text-green-600' : 'text-purple-600') }}">
                        {{ substr($comment->display_name, 0, 1) }}
                    </span>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-sm">{{ $comment->display_name }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full
                            {{ $comment->author_type->value === 'requester' ? 'bg-blue-100 text-blue-600' : ($comment->author_type->value === 'head' ? 'bg-green-100 text-green-600' : 'bg-purple-100 text-purple-600') }}">
                            {{ $comment->author_type->getLabel() }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">{{ $comment->message }}</p>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-sm mb-4">Belum ada komentar.</p>
        @endif
        
        {{-- Add Comment Form --}}
        @if(!in_array($contentRequest->status->value, ['published', 'archived', 'rejected']))
        <form action="{{ route('request.status.comment', $contentRequest) }}" method="POST" class="border-t pt-4">
            @csrf
            <div class="flex gap-3">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-medium text-blue-600">{{ substr($requester['name'], 0, 1) }}</span>
                </div>
                <div class="flex-1">
                    <textarea name="message" rows="2" required placeholder="Tulis komentar atau pertanyaan..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                    <button type="submit" class="mt-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                        Kirim
                    </button>
                </div>
            </div>
        </form>
        @endif
    </div>
</div>
@endsection
