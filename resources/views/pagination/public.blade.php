{{--
    Paginasi halaman publik, menggantikan tampilan bawaan Laravel. Dipanggil
    dengan namanya, `links('pagination.public')`, bukan sebagai bawaan:
    Livewire mengganti tampilan bawaan selama tabel panel dirender.

    Bawaannya rusak di ponsel. Tombolnya bertuliskan "pagination.previous" dan
    "pagination.next" mentah, karena aplikasi ini berbahasa Indonesia tanpa
    berkas terjemahan paginasi. Bila ponselnya memakai mode gelap, tombolnya
    juga berubah abu-abu gelap, karena membawa kelas dark: sementara halamannya
    tetap terang. Di layar lebar ia berbahasa Inggris.

    Yang ini berbahasa Indonesia dan memakai warna sistem desain.
--}}
@if ($paginator->hasPages())
    @php
        $tombol = 'inline-flex min-h-[2.5rem] items-center gap-1 rounded-lg border px-3 text-sm font-medium transition';
        $bisa = $tombol.' border-ink/15 bg-white text-ink hover:border-sun-deep hover:bg-sun-soft';
        $tidak = $tombol.' cursor-not-allowed border-ink/10 bg-paper-2 text-ink/35';
        $nomor = 'inline-flex min-h-[2.5rem] min-w-[2.5rem] items-center justify-center rounded-lg px-3 text-sm';
    @endphp

    <nav role="navigation" aria-label="Navigasi halaman">
        <div class="flex items-center justify-between gap-3">
            @if ($paginator->onFirstPage())
                <span class="{{ $tidak }}" aria-disabled="true">
                    <span aria-hidden="true">&lsaquo;</span> Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $bisa }}">
                    <span aria-hidden="true">&lsaquo;</span> Sebelumnya
                </a>
            @endif

            {{-- Deretan nomor halaman tidak muat di ponsel; di sana letak
                 halamannya disebut di bawah. --}}
            <div class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-2 text-sm text-ink/45" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $nomor }} bg-ink font-semibold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="Ke halaman {{ $page }}"
                                   class="{{ $nomor }} font-medium text-ink/75 hover:bg-sun-soft hover:text-ink">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $bisa }}">
                    Berikutnya <span aria-hidden="true">&rsaquo;</span>
                </a>
            @else
                <span class="{{ $tidak }}" aria-disabled="true">
                    Berikutnya <span aria-hidden="true">&rsaquo;</span>
                </span>
            @endif
        </div>

        <p class="mt-3 text-center text-xs text-ink/55">
            <span class="sm:hidden">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }} &middot;</span>
            Menampilkan {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </p>
    </nav>
@endif
