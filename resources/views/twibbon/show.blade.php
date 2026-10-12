{{--
    Editor twibbon.

    Foto peserta digabungkan dengan bingkai di peramban lewat <canvas> dan
    tidak pernah dikirim ke server: privasinya terjaga, dan server tidak
    terbebani walau ratusan peserta memakainya bersamaan. Yang dikirim balik
    hanya tanda "dipakai" untuk penghitung.

    Bingkai dimuat dari alamat tanpa domain (Twibbon::frameRelativeUrl), karena
    kanvas menolak mengekspor gambar dari asal lain.

    Peramban di dalam Instagram dan WhatsApp kerap memblokir unduhan, jadi
    hasilnya juga ditampilkan sebagai gambar biasa yang bisa ditekan lama
    untuk disimpan.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('img/jgusolo.png') }}">

    <x-organisms.seo
        :title="$twibbon->title"
        :description="$twibbon->description ?: 'Pasang twibbon '.$twibbon->title.' di foto Anda, langsung dari ponsel.'"
        :image="$twibbon->frameAbsoluteUrl()"
        :noindex="! $twibbon->isOpen()" />

    <x-organisms.design-system />

    <style>
        .tw-kanvas { touch-action: none; cursor: grab; }
        .tw-kanvas:active { cursor: grabbing; }
        .tw-kotak { background: repeating-conic-gradient(rgb(255 247 220) 0 25%, rgb(255 255 255) 0 50%) 0 0 / 24px 24px; }
    </style>
</head>

<body class="min-h-screen bg-paper text-ink antialiased">
    <div class="mx-auto w-full max-w-5xl px-5 py-8 sm:py-12">
        <a href="{{ route('twibbon.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-ink/60 hover:text-ink">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5 5-5M18 12H6"/></svg>
            Semua twibbon
        </a>

        <header class="mt-4">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $twibbon->title }}</h1>
            @if ($twibbon->description)
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-ink/65">{{ $twibbon->description }}</p>
            @endif
            @if ($twibbon->uses_count > 0)
                <p class="mt-2 text-sm font-semibold text-sun-ink">Sudah dipakai {{ number_format($twibbon->uses_count, 0, ',', '.') }} orang</p>
            @endif
        </header>

        @if (! $twibbon->isOpen())
            <div class="kaca-terang mt-8 max-w-xl rounded-2xl p-6" role="status">
                <p class="font-semibold">Twibbon ini sudah ditutup.</p>
                <p class="mt-1 text-sm text-ink/60">Masa pemakaiannya telah berakhir. Lihat twibbon lain yang masih dibuka.</p>
                <a href="{{ route('twibbon.index') }}" class="mt-4 inline-flex rounded-xl bg-sun px-4 py-2 text-sm font-bold text-ink">Lihat twibbon lain</a>
            </div>
        @else
            <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                {{-- Kanvas --}}
                <section aria-label="Pratinjau twibbon">
                    <div class="tw-kotak overflow-hidden rounded-2xl border border-ink/10 shadow-sm">
                        <canvas id="tw-kanvas"
                                class="tw-kanvas block h-auto w-full"
                                width="{{ $twibbon->frame_width ?: 1080 }}"
                                height="{{ $twibbon->frame_height ?: 1080 }}"
                                tabindex="0"
                                role="img"
                                aria-label="Pratinjau twibbon. Seret untuk menggeser foto, cubit atau gulir untuk memperbesar. Tombol panah juga bisa dipakai."></canvas>
                    </div>
                    <p id="tw-petunjuk" class="mt-3 text-center text-sm text-ink/55">Pilih foto untuk mulai. Setelah itu seret untuk menggeser, cubit atau gulir untuk memperbesar.</p>
                </section>

                {{-- Kontrol --}}
                <section class="space-y-5" aria-label="Pengaturan">
                    <div>
                        <label for="tw-berkas" class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-sun px-5 py-3 font-bold text-ink shadow-sm transition hover:brightness-95 focus-within:ring-4 focus-within:ring-sun/50">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span id="tw-label-pilih">Pilih foto</span>
                        </label>
                        <input id="tw-berkas" type="file" accept="image/*" class="sr-only">
                        <p id="tw-galat" class="mt-2 hidden text-sm font-semibold text-red-700" role="alert"></p>
                    </div>

                    <div id="tw-atur" class="hidden space-y-4 rounded-2xl border border-ink/10 bg-white p-5">
                        <div>
                            <label for="tw-zoom" class="text-sm font-semibold">Perbesar</label>
                            <input id="tw-zoom" type="range" min="0.5" max="4" step="0.01" value="1" class="mt-2 w-full accent-[#8A6000]">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" id="tw-putar" class="rounded-xl border border-ink/15 px-3 py-2 text-sm font-semibold hover:bg-sun-soft">Putar 90°</button>
                            <button type="button" id="tw-ulang" class="rounded-xl border border-ink/15 px-3 py-2 text-sm font-semibold hover:bg-sun-soft">Atur ulang</button>
                        </div>
                        <button type="button" id="tw-unduh" class="w-full rounded-xl bg-ink px-5 py-3 font-bold text-white hover:bg-ink/90 focus:outline-none focus-visible:ring-4 focus-visible:ring-sun">Buat twibbon</button>
                    </div>

                    <div id="tw-hasil" class="hidden space-y-3 rounded-2xl border border-ink/10 bg-white p-5">
                        <p class="font-semibold">Twibbon Anda siap.</p>
                        <img id="tw-gambar-hasil" alt="Twibbon Anda" class="w-full rounded-xl border border-ink/10">
                        <div class="grid gap-2">
                            <a id="tw-simpan" download class="rounded-xl bg-sun px-5 py-3 text-center font-bold text-ink">Simpan gambar</a>
                            <button type="button" id="tw-bagikan" class="hidden rounded-xl border border-ink/15 px-5 py-3 font-semibold hover:bg-sun-soft">Bagikan</button>
                        </div>
                        <p class="text-xs leading-relaxed text-ink/55">Tombol simpan tidak bekerja? Bila dibuka dari Instagram atau WhatsApp, tekan lama gambar di atas lalu pilih Simpan, atau buka halaman ini di Chrome atau Safari.</p>
                    </div>

                    @if ($twibbon->caption)
                        <div class="rounded-2xl border border-ink/10 bg-white p-5">
                            <p class="text-sm font-semibold">Caption</p>
                            <p id="tw-caption" class="mt-2 whitespace-pre-line text-sm text-ink/75">{{ $twibbon->caption }}</p>
                            <button type="button" id="tw-salin" class="mt-3 rounded-xl border border-ink/15 px-4 py-2 text-sm font-semibold hover:bg-sun-soft">Salin caption</button>
                        </div>
                    @endif

                    <p class="flex gap-2 text-xs leading-relaxed text-ink/55">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Foto Anda diproses di perangkat ini dan tidak diunggah ke server mana pun.
                    </p>
                </section>
            </div>

            @php
                $konfig = [
                    'bingkai' => $twibbon->frameRelativeUrl(),
                    'dipakai' => route('twibbon.used', $twibbon->slug),
                    'namaBerkas' => 'twibbon-'.$twibbon->slug.'.jpg',
                ];
            @endphp
            <script>
            (() => {
                const KONFIG = @json($konfig);

                const kanvas = document.getElementById('tw-kanvas');
                const ctx = kanvas.getContext('2d');
                const W = kanvas.width;
                const H = kanvas.height;
                const el = (id) => document.getElementById(id);

                const bingkai = new Image();
                let foto = null;       // gambar foto peserta
                let skalaPas = 1;      // skala agar foto menutup seluruh kanvas
                let zoom = 1;          // pengali di atas skalaPas
                let geser = { x: 0, y: 0 };
                let putaran = 0;       // kelipatan 90 derajat
                let tercatat = false;

                function galat(pesan) {
                    const p = el('tw-galat');
                    p.textContent = pesan || '';
                    p.classList.toggle('hidden', !pesan);
                }

                function hitungSkalaPas() {
                    const tegak = putaran % 2 === 1;
                    const lebar = tegak ? foto.height : foto.width;
                    const tinggi = tegak ? foto.width : foto.height;
                    skalaPas = Math.max(W / lebar, H / tinggi);
                }

                function gambar() {
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, W, H);

                    if (foto) {
                        ctx.save();
                        ctx.translate(W / 2 + geser.x, H / 2 + geser.y);
                        ctx.rotate(putaran * Math.PI / 2);
                        const s = skalaPas * zoom;
                        ctx.scale(s, s);
                        ctx.drawImage(foto, -foto.width / 2, -foto.height / 2);
                        ctx.restore();
                    } else {
                        ctx.fillStyle = '#FFF7DC';
                        ctx.fillRect(0, 0, W, H);
                    }

                    if (bingkai.complete && bingkai.naturalWidth > 0) {
                        ctx.drawImage(bingkai, 0, 0, W, H);
                    }
                }

                function aturUlang() {
                    zoom = 1;
                    geser = { x: 0, y: 0 };
                    putaran = 0;
                    if (foto) hitungSkalaPas();
                    el('tw-zoom').value = 1;
                    gambar();
                }

                bingkai.onload = gambar;
                bingkai.onerror = () => galat('Bingkai twibbon gagal dimuat. Muat ulang halaman ini.');
                bingkai.src = KONFIG.bingkai;

                el('tw-berkas').addEventListener('change', (e) => {
                    const berkas = e.target.files && e.target.files[0];
                    if (!berkas) return;
                    galat('');

                    const alamat = URL.createObjectURL(berkas);
                    const img = new Image();
                    img.onload = () => {
                        foto = img;
                        aturUlang();
                        el('tw-atur').classList.remove('hidden');
                        el('tw-hasil').classList.add('hidden');
                        el('tw-label-pilih').textContent = 'Ganti foto';
                        el('tw-petunjuk').textContent = 'Seret untuk menggeser, cubit atau gulir untuk memperbesar.';
                    };
                    img.onerror = () => galat('Foto ini tidak bisa dibuka. Gunakan foto JPG atau PNG; foto HEIC dari iPhone bisa diubah lewat menu bagikan lalu simpan sebagai JPG.');
                    img.src = alamat;
                });

                // ----- geser dan cubit, untuk tetikus maupun sentuhan -----
                const jari = new Map();
                let jarakAwal = 0;
                let zoomAwal = 1;

                function keKanvas(dx) {
                    return dx * (W / kanvas.getBoundingClientRect().width);
                }

                kanvas.addEventListener('pointerdown', (e) => {
                    if (!foto) return;
                    kanvas.setPointerCapture(e.pointerId);
                    jari.set(e.pointerId, { x: e.clientX, y: e.clientY });
                    if (jari.size === 2) {
                        const [a, b] = [...jari.values()];
                        jarakAwal = Math.hypot(a.x - b.x, a.y - b.y);
                        zoomAwal = zoom;
                    }
                });

                kanvas.addEventListener('pointermove', (e) => {
                    if (!foto || !jari.has(e.pointerId)) return;
                    const lama = jari.get(e.pointerId);
                    jari.set(e.pointerId, { x: e.clientX, y: e.clientY });

                    if (jari.size === 1) {
                        geser.x += keKanvas(e.clientX - lama.x);
                        geser.y += keKanvas(e.clientY - lama.y);
                    } else if (jari.size === 2 && jarakAwal > 0) {
                        const [a, b] = [...jari.values()];
                        zoom = Math.min(4, Math.max(0.5, zoomAwal * Math.hypot(a.x - b.x, a.y - b.y) / jarakAwal));
                        el('tw-zoom').value = zoom;
                    }
                    gambar();
                });

                const lepas = (e) => { jari.delete(e.pointerId); if (jari.size < 2) jarakAwal = 0; };
                kanvas.addEventListener('pointerup', lepas);
                kanvas.addEventListener('pointercancel', lepas);

                kanvas.addEventListener('wheel', (e) => {
                    if (!foto) return;
                    e.preventDefault();
                    zoom = Math.min(4, Math.max(0.5, zoom * (e.deltaY < 0 ? 1.06 : 1 / 1.06)));
                    el('tw-zoom').value = zoom;
                    gambar();
                }, { passive: false });

                kanvas.addEventListener('keydown', (e) => {
                    if (!foto) return;
                    const langkah = W / 50;
                    const aksi = {
                        ArrowLeft: () => geser.x -= langkah, ArrowRight: () => geser.x += langkah,
                        ArrowUp: () => geser.y -= langkah, ArrowDown: () => geser.y += langkah,
                        '+': () => zoom = Math.min(4, zoom * 1.06), '=': () => zoom = Math.min(4, zoom * 1.06),
                        '-': () => zoom = Math.max(0.5, zoom / 1.06),
                    }[e.key];
                    if (!aksi) return;
                    e.preventDefault();
                    aksi();
                    el('tw-zoom').value = zoom;
                    gambar();
                });

                el('tw-zoom').addEventListener('input', (e) => { zoom = parseFloat(e.target.value); gambar(); });
                el('tw-putar').addEventListener('click', () => { putaran = (putaran + 1) % 4; hitungSkalaPas(); gambar(); });
                el('tw-ulang').addEventListener('click', aturUlang);

                // ----- hasil -----
                let berkasHasil = null;

                el('tw-unduh').addEventListener('click', () => {
                    gambar();
                    kanvas.toBlob((blob) => {
                        if (!blob) {
                            galat('Gambar gagal dibuat. Coba lagi, atau buka halaman ini di Chrome atau Safari.');
                            return;
                        }
                        const alamat = URL.createObjectURL(blob);
                        berkasHasil = new File([blob], KONFIG.namaBerkas, { type: 'image/jpeg' });

                        el('tw-gambar-hasil').src = alamat;
                        el('tw-simpan').href = alamat;
                        el('tw-simpan').download = KONFIG.namaBerkas;
                        el('tw-hasil').classList.remove('hidden');
                        el('tw-bagikan').classList.toggle('hidden',
                            !(navigator.canShare && navigator.canShare({ files: [berkasHasil] })));
                        el('tw-hasil').scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                        if (!tercatat) {
                            tercatat = true;
                            fetch(KONFIG.dipakai, {
                                method: 'POST',
                                keepalive: true,
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            }).catch(() => {});
                        }
                    }, 'image/jpeg', 0.92);
                });

                el('tw-bagikan').addEventListener('click', async () => {
                    if (!berkasHasil) return;
                    try {
                        await navigator.share({
                            files: [berkasHasil],
                            text: el('tw-caption') ? el('tw-caption').textContent : undefined,
                        });
                    } catch (_) { /* dibatalkan pengguna */ }
                });

                const salin = el('tw-salin');
                if (salin) {
                    salin.addEventListener('click', async () => {
                        const teks = el('tw-caption').textContent;
                        try {
                            await navigator.clipboard.writeText(teks);
                        } catch (_) {
                            const area = document.createElement('textarea');
                            area.value = teks;
                            document.body.appendChild(area);
                            area.select();
                            document.execCommand('copy');
                            area.remove();
                        }
                        salin.textContent = 'Tersalin';
                        setTimeout(() => { salin.textContent = 'Salin caption'; }, 2000);
                    });
                }

                gambar();
            })();
            </script>
        @endif
    </div>
</body>
</html>
