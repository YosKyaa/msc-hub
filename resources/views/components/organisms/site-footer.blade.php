{{-- Satu footer untuk seluruh halaman publik; sebelumnya markupnya disalin
     di tiap layout dan mulai berbeda satu sama lain.

     Kontak yang dipasang hanya email resmi MSC dan tautan ke /bio. Nomor
     WhatsApp admin adalah nomor pribadi, dan sengaja hanya tampil di /bio;
     footer ini muncul di setiap halaman publik. --}}
<footer class="mt-auto border-t bg-white">
    <div class="mx-auto max-w-6xl px-4 py-6">
        <div class="flex flex-col items-center gap-5 text-center sm:flex-row sm:items-start sm:justify-between sm:text-left">
            <div class="flex items-center gap-3">
                <img src="{{ asset('img/jgu.png') }}" alt="Jakarta Global University" class="h-8 w-auto">
                <div class="text-left text-sm">
                    <div class="font-semibold text-ink">Jakarta Global University</div>
                    <div class="text-xs text-ink/60">Media &amp; Strategic Communications</div>
                </div>
            </div>

            <div class="text-sm">
                <div class="font-semibold text-ink">Hubungi MSC</div>
                <div class="mt-1.5 flex flex-col items-center gap-1.5 text-ink/70 sm:items-start">
                    @if (filled(config('msc.contact_email')))
                        <a href="mailto:{{ config('msc.contact_email') }}" class="inline-flex items-center gap-2 hover:text-sun-ink hover:underline">
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M4 6h16v12H4z" stroke-linejoin="round"/><path d="m4 7 8 6 8-6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            {{ config('msc.contact_email') }}
                        </a>
                    @endif

                    @if (filled(config('msc.bio.admins')))
                        <a href="{{ route('bio') }}" class="inline-flex items-center gap-2 hover:text-sun-ink hover:underline">
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M7 18.5 3.5 20l1.2-3.6A8 8 0 1 1 7 18.5Z" stroke-linejoin="round"/>
                            </svg>
                            Tanya admin MSC
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-5 flex flex-col gap-1 border-t border-ink/10 pt-4 text-center text-xs text-ink/60 sm:flex-row sm:justify-between sm:text-left">
            <p>&copy; {{ date('Y') }} Jakarta Global University</p>
            <p>Jl. Boulevard Grand Depok City, Depok 16412</p>
        </div>
    </div>
</footer>
