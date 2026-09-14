{{-- Satu footer untuk seluruh halaman publik; sebelumnya markupnya disalin
     di tiap layout dan mulai berbeda satu sama lain. --}}
<footer class="mt-auto border-t bg-white">
    <div class="mx-auto max-w-6xl px-4 py-6">
        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <div class="flex items-center gap-3">
                <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="h-8 w-auto">
                <div class="text-sm">
                    <div class="font-semibold text-gray-900">Jakarta Global University</div>
                    <div class="text-xs text-gray-500">Media &amp; Strategic Communications</div>
                </div>
            </div>
            <div class="text-center text-sm text-gray-500 sm:text-right">
                <p>&copy; {{ date('Y') }} Jakarta Global University</p>
                <p class="mt-1 text-xs">Jl. Boulevard Grand Depok City, Depok 16412</p>
            </div>
        </div>
    </div>
</footer>
