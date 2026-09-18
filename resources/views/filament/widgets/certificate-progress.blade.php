{{--
    Gaya ditulis di sini sebagai CSS biasa, bukan utility Tailwind.
    Panel ini tidak memakai viteTheme(), jadi kelas Tailwind yang tidak
    dipakai di tempat lain tidak pernah ikut dikompilasi dan hasilnya polos.
--}}
<style>
.sert-langkah{padding:18px;border:1px solid rgb(228 228 231);border-radius:12px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.dark .sert-langkah{background:rgb(24 24 27);border-color:rgb(63 63 70)}
.sert-langkah-judul{margin:0 0 14px;font-size:14px;font-weight:700;color:rgb(24 24 27)}
.dark .sert-langkah-judul{color:rgb(244 244 245)}

/* auto-fit: menumpuk sendiri di layar sempit, tanpa media query. */
.sert-deret{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
.sert-kotak{position:relative;padding:12px 12px 12px 42px;border:1px solid rgb(228 228 231);border-radius:10px;background:rgb(250 250 250)}
.dark .sert-kotak{background:rgb(39 39 42);border-color:rgb(63 63 70)}
.sert-nomor{position:absolute;left:12px;top:12px;display:flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;font-size:11px;font-weight:700;line-height:1}
.sert-nama{font-size:13px;font-weight:600;line-height:1.3;color:rgb(24 24 27)}
.dark .sert-nama{color:rgb(244 244 245)}
.sert-angka{margin-top:3px;font-size:12px;line-height:1.35;color:rgb(113 113 122)}
.dark .sert-angka{color:rgb(161 161 170)}

.sert-kotak.selesai{border-color:rgb(187 247 208);background:rgb(240 253 244)}
.dark .sert-kotak.selesai{background:rgba(22,101,52,.22);border-color:rgb(22 101 52)}
.sert-kotak.selesai .sert-nomor{background:rgb(22 163 74);color:#fff}
.sert-kotak.berjalan{border-color:rgb(253 230 138);background:rgb(255 251 235)}
.dark .sert-kotak.berjalan{background:rgba(146,64,14,.22);border-color:rgb(146 64 14)}
.sert-kotak.berjalan .sert-nomor{background:rgb(217 119 6);color:#fff}
.sert-kotak.belum .sert-nomor{background:rgb(228 228 231);color:rgb(113 113 122)}
.dark .sert-kotak.belum .sert-nomor{background:rgb(63 63 70);color:rgb(161 161 170)}

.sert-pesan{display:flex;gap:9px;margin-top:14px;padding:11px 13px;border-radius:9px;font-size:13px;line-height:1.5}
.sert-pesan-ikon{flex-shrink:0;font-weight:700}
.sert-lanjut{border:1px solid rgb(191 219 254);background:rgb(239 246 255);color:rgb(30 64 175)}
.dark .sert-lanjut{background:rgba(30,64,175,.22);border-color:rgb(30 64 175);color:rgb(191 219 254)}
.sert-tuntas{border:1px solid rgb(187 247 208);background:rgb(240 253 244);color:rgb(22 101 52)}
.dark .sert-tuntas{background:rgba(22,101,52,.22);border-color:rgb(22 101 52);color:rgb(187 247 208)}
.sert-awas{border:1px solid rgb(254 215 170);background:rgb(255 247 237);color:rgb(154 52 18)}
.dark .sert-awas{background:rgba(154,52,18,.22);border-color:rgb(154 52 18);color:rgb(254 215 170)}
</style>

<div class="sert-langkah">
    <h3 class="sert-langkah-judul">Alur pembuatan sertifikat</h3>

    <div class="sert-deret">
        @foreach($steps as $nomor => $step)
            <div class="sert-kotak {{ $step['keadaan'] }}">
                <span class="sert-nomor">{{ $step['keadaan'] === 'selesai' ? '✓' : $nomor + 1 }}</span>
                <div class="sert-nama">{{ $step['judul'] }}</div>
                <div class="sert-angka">{{ $step['angka'] }}</div>
            </div>
        @endforeach
    </div>

    @if($warning)
        <p class="sert-pesan sert-awas">
            <span class="sert-pesan-ikon">!</span>
            <span>{{ $warning }}</span>
        </p>
    @endif

    <p class="sert-pesan {{ $done ? 'sert-tuntas' : 'sert-lanjut' }}">
        <span class="sert-pesan-ikon">{{ $done ? '✓' : '→' }}</span>
        <span>{{ $nextStep }}</span>
    </p>
</div>
