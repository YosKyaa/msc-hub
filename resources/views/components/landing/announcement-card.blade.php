@props(['announcement', 'pinned' => false])

<article class="announcement-card bg-white rounded-2xl p-6 border {{ $pinned ? 'ring-2 ring-amber-300 bg-gradient-to-br from-amber-50 to-white' : '' }} hover:shadow-lg">
    <div class="flex items-center gap-2 mb-4">
        @if($pinned)
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-semibold rounded-lg">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/></svg>
                Pinned
            </span>
        @endif
        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-lg
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
    
    <h3 class="font-bold text-gray-900 text-lg mb-2 line-clamp-2 group-hover:text-accent transition">
        <a href="{{ route('announcements.show', $announcement->slug) }}" class="hover:text-blue-600">
            {{ $announcement->title }}
        </a>
    </h3>
    
    <p class="text-gray-500 text-sm mb-4 line-clamp-2">{{ $announcement->summary }}</p>
    
    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
        <span class="text-xs text-gray-400 flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ $announcement->published_at->format('d M Y') }}
        </span>
        <a href="{{ route('announcements.show', $announcement->slug) }}" class="inline-flex items-center gap-1.5 text-sm text-blue-600 font-semibold hover:text-blue-700 hover:gap-2.5 transition-all">
            <span>Baca</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
    </div>
</article>
