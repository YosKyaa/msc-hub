@extends('layouts.public')

@section('title', $announcement->title)

@section('content')
<div class="mx-auto max-w-3xl">
    {{-- Breadcrumb --}}
    <nav class="mb-6">
        <ol class="flex items-center gap-2 text-sm text-gray-500">
            <li><a href="{{ route('landing') }}" class="hover:text-blue-600">Beranda</a></li>
            <li><span class="text-gray-300">/</span></li>
            <li><a href="{{ route('announcements.index') }}" class="hover:text-blue-600">Pengumuman</a></li>
            <li><span class="text-gray-300">/</span></li>
            <li class="text-gray-900 font-medium truncate max-w-[200px]">{{ $announcement->title }}</li>
        </ol>
    </nav>

    {{-- Article --}}
    <article class="bg-white rounded-xl p-6 md:p-8 shadow-sm border">
        {{-- Header --}}
        <header class="mb-6 pb-6 border-b">
            <div class="flex items-center gap-2 mb-4">
                @if($announcement->is_pinned)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/></svg>
                        Pinned
                    </span>
                @endif
                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                    @switch($announcement->category->value)
                        @case('announcement') bg-blue-100 text-blue-700 @break
                        @case('event') bg-green-100 text-green-700 @break
                        @case('sop') bg-amber-100 text-amber-700 @break
                        @case('maintenance') bg-red-100 text-red-700 @break
                    @endswitch
                ">
                    {{ $announcement->category->getLabel() }}
                </span>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">{{ $announcement->title }}</h1>
            <div class="flex items-center gap-4 text-sm text-gray-500">
                <span class="flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $announcement->published_at->format('d F Y') }}
                </span>
                @if($announcement->creator)
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        {{ $announcement->creator->name }}
                    </span>
                @endif
            </div>
        </header>

        {{-- Summary --}}
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <p class="text-gray-700 font-medium">{{ $announcement->summary }}</p>
        </div>

        {{-- Content --}}
        @if($announcement->content)
            <div class="prose text-gray-700">
                {!! $announcement->content !!}
            </div>
        @endif
    </article>

    {{-- Related Announcements --}}
    @if($relatedAnnouncements->isNotEmpty())
        <div class="mt-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Pengumuman Terkait</h2>
            <div class="grid sm:grid-cols-3 gap-4">
                @foreach($relatedAnnouncements as $related)
                    <article class="bg-white rounded-xl p-4 border hover:shadow-md transition">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                @switch($related->category->value)
                                    @case('announcement') bg-blue-100 text-blue-700 @break
                                    @case('event') bg-green-100 text-green-700 @break
                                    @case('sop') bg-amber-100 text-amber-700 @break
                                    @case('maintenance') bg-red-100 text-red-700 @break
                                @endswitch
                            ">
                                {{ $related->category->getLabel() }}
                            </span>
                        </div>
                        <h3 class="font-medium text-gray-900 text-sm mb-1 line-clamp-2">
                            <a href="{{ route('announcements.show', $related->slug) }}" class="hover:text-blue-600">
                                {{ $related->title }}
                            </a>
                        </h3>
                        <span class="text-xs text-gray-400">{{ $related->published_at->format('d M Y') }}</span>
                    </article>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Back Link --}}
    <div class="mt-8">
        <a href="{{ route('announcements.index') }}" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Daftar Pengumuman
        </a>
    </div>
</div>
@endsection
