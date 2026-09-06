@props(['asset'])

<article class="work-card group bg-white rounded-2xl overflow-hidden border hover:shadow-xl">
    <div class="relative aspect-video bg-gradient-to-br from-gray-100 to-gray-200 overflow-hidden">
        {{-- Placeholder with icon based on type --}}
        <div class="absolute inset-0 flex items-center justify-center">
            @switch($asset->asset_type->value)
                @case('photo')
                    <div class="text-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    @break
                @case('video')
                    <div class="text-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </div>
                    @break
                @case('design')
                    <div class="text-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                    </div>
                    @break
                @default
                    <div class="text-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
            @endswitch
        </div>
        
        {{-- Hover overlay --}}
        <div class="work-overlay absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent flex items-end p-4">
            @if($asset->primary_link)
                <a href="{{ $asset->primary_link }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-900 rounded-lg font-medium text-sm hover:bg-gray-100 transition">
                    <span>Lihat Karya</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            @endif
        </div>
    </div>
    
    <div class="p-5">
        <h3 class="font-bold text-gray-900 mb-3 line-clamp-2 group-hover:text-blue-600 transition">{{ $asset->title }}</h3>
        
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-lg
                @switch($asset->asset_type->value)
                    @case('photo') bg-green-100 text-green-700 @break
                    @case('video') bg-red-100 text-red-700 @break
                    @case('design') bg-amber-100 text-amber-700 @break
                    @case('banner') bg-blue-100 text-blue-700 @break
                    @case('post') bg-indigo-100 text-indigo-700 @break
                    @default bg-gray-100 text-gray-700 @break
                @endswitch
            ">
                {{ $asset->asset_type->getLabel() }}
            </span>
            <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-lg bg-gray-100 text-gray-600">
                {{ $asset->platform->getLabel() }}
            </span>
        </div>
    </div>
</article>
