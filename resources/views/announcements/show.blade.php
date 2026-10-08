@extends('layouts.public')

@section('title', $announcement->title)

@section('content')
<div class="mx-auto max-w-3xl">
    {{-- Breadcrumb --}}
    <nav class="mb-6">
        <ol class="flex items-center gap-2 text-sm text-ink/60">
            <li><a href="{{ route('landing') }}" class="hover:text-sun-ink">Beranda</a></li>
            <li><span class="text-gray-300">/</span></li>
            <li><a href="{{ route('announcements.index') }}" class="hover:text-sun-ink">Pengumuman</a></li>
            <li><span class="text-gray-300">/</span></li>
            <li class="text-ink font-medium truncate max-w-[200px]">{{ $announcement->title }}</li>
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
            <h1 class="text-2xl md:text-3xl font-bold text-ink mb-4">{{ $announcement->title }}</h1>
            <div class="flex items-center gap-4 text-sm text-ink/60">
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
        <div class="bg-paper-2 rounded-lg p-4 mb-6">
            <p class="text-ink/75 font-medium">{{ $announcement->summary }}</p>
        </div>

        {{-- Isinya ditulis staf lewat RichEditor dan sudah dinormalkan tiptap
             saat disimpan. Penyaring Filament dipasang lagi di sini sebagai
             lapis kedua: isi yang masuk bukan lewat editor — seeder, impor,
             atau langsung ke basis data — tidak melewati penyaring pertama. --}}
        @if($announcement->content)
            <div class="prose text-ink/75">
                {!! \Filament\Forms\Components\RichEditor\RichContentRenderer::make($announcement->content)->toHtml() !!}
            </div>
        @endif
    </article>

    {{-- Related Announcements --}}
    @if($relatedAnnouncements->isNotEmpty())
        <div class="mt-8">
            <h2 class="text-lg font-semibold text-ink mb-4">Pengumuman Terkait</h2>
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
                        <h3 class="font-medium text-ink text-sm mb-1 line-clamp-2">
                            <a href="{{ route('announcements.show', $related->slug) }}" class="hover:text-sun-ink">
                                {{ $related->title }}
                            </a>
                        </h3>
                        <span class="text-xs text-ink/45">{{ $related->published_at->format('d M Y') }}</span>
                    </article>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Back Link --}}
    <div class="mt-8">
        <a href="{{ route('announcements.index') }}" class="inline-flex items-center gap-2 text-sun-ink hover:text-sun-ink font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Daftar Pengumuman
        </a>
    </div>
</div>
@endsection
