@php
    /**
     * Dokumentasi panel.
     *
     * Sebelumnya halaman ini hanya membahas Projects, Assets, dan Tags —
     * sepertiga sistem — sementara peminjaman, permintaan konten, dan
     * sertifikat yang justru paling sering dikerjakan tidak disebut sama
     * sekali. Susunannya kini mengikuti tiga alur kerja yang sebenarnya
     * berjalan, masing-masing dari pengajuan sampai selesai.
     */
    $alur = [
        [
            'judul' => 'Permintaan Konten',
            'ringkas' => 'Warga kampus minta dibuatkan foto, video, atau desain.',
            'warna' => 'primary',
            'langkah' => [
                ['Pemohon mengajukan', 'Lewat halaman publik. Sistem memberi kode seperti CR-2026-0001.'],
                ['Staf meninjau', 'Buka Permintaan Konten, tetapkan penanggung jawab dan tenggatnya.'],
                ['Dikerjakan', 'Status diubah mengikuti perkembangan; pemohon memantaunya sendiri.'],
                ['Kepala MSC menyetujui', 'Setelah disetujui, hasilnya dipublikasikan dan pemohon dikabari.'],
            ],
            'tautan' => ['filament.admin.resources.content-requests.index' => 'Buka Permintaan Konten'],
        ],
        [
            'judul' => 'Peminjaman Ruangan & Alat',
            'ringkas' => 'Pinjam studio, ruang rapat, kamera, lighting, dan audio.',
            'warna' => 'warning',
            'langkah' => [
                ['Peminjam mengajukan', 'Memilih jadwal dan alat. Bentrok jadwal ditolak sistem sejak awal.'],
                ['Staf menyetujui', 'Periksa jadwal dan kelengkapannya, lalu setujui atau tolak dengan alasan.'],
                ['Kepala MSC menyetujui', 'Persetujuan kedua. Peminjam dikabari lewat email tiap status berubah.'],
                ['Diambil & dikembalikan', 'Catat pengambilan dan pengembalian beserta kondisi alatnya.'],
            ],
            'tautan' => [
                'filament.admin.resources.room-bookings.index' => 'Booking Ruangan',
                'filament.admin.resources.inventory-bookings.index' => 'Peminjaman Alat',
            ],
        ],
        [
            'judul' => 'Sertifikat',
            'ringkas' => 'Terbitkan sertifikat kegiatan, lengkap dengan halaman verifikasi.',
            'warna' => 'success',
            'langkah' => [
                ['Siapkan kegiatan', 'Buat kegiatan, pilih templatnya, lalu publikasikan agar sertifikatnya sah.'],
                ['Kumpulkan peserta', 'Lewat absensi QR, impor berkas, atau tambah satu per satu.'],
                ['Terbitkan digital', 'Nomor diberikan dan halaman verifikasi langsung aktif. Email belum dikirim.'],
                ['Kirim email', 'Setelah hasilnya diperiksa. Yang sudah menerima tidak dikirimi ulang.'],
            ],
            'tautan' => ['filament.admin.resources.certificate-events.index' => 'Buka Kegiatan Sertifikat'],
        ],
        [
            'judul' => 'Arsip Media',
            'ringkas' => 'Simpan hasil karya agar mudah dicari kembali.',
            'warna' => 'gray',
            'langkah' => [
                ['Buat Project', 'Satu project untuk satu event atau kegiatan.'],
                ['Tambah Asset', 'Foto, video, atau desain, beserta tautan sumber dan hasilnya.'],
                ['Beri Tag', 'Supaya bisa ditemukan lewat pencarian nanti.'],
            ],
            'tautan' => [
                'filament.admin.resources.projects.index' => 'Projects',
                'filament.admin.resources.assets.index' => 'Assets',
                'filament.admin.resources.tags.index' => 'Tags',
            ],
        ],
    ];

    $akses = [
        ['Admin', 'Seluruh sistem, termasuk pengguna dan peran.'],
        ['Kepala MSC', 'Persetujuan akhir untuk semua pengajuan, dan penerbitan sertifikat.'],
        ['Staf MSC', 'Meninjau pengajuan, mengelola inventaris, arsip, dan sertifikat.'],
        ['Dosen / Mahasiswa', 'Tidak masuk panel. Mengajukan lewat halaman publik.'],
    ];

    $tanya = [
        [
            'Kenapa sertifikat tidak bisa diterbitkan?',
            'Kegiatannya belum memakai template yang aktif, atau belum ada peserta yang ditandai berhak. Keduanya disebutkan di pesan penolakannya.',
        ],
        [
            'Sudah diterbitkan, kenapa emailnya belum sampai?',
            'Menerbitkan dan mengirim memang dua langkah terpisah, supaya penerbitan yang keliru tidak terlanjur mendarat di kotak masuk. Tekan Kirim Email setelah hasilnya diperiksa.',
        ],
        [
            'Email tidak pernah terkirim sama sekali.',
            'Pengiriman email menunggu pekerja antrean. Pastikan `php artisan queue:work` berjalan di server. Panel akan memperingatkan bila antreannya menumpuk.',
        ],
        [
            'Peserta salah ketik namanya di sertifikat.',
            'Pakai tombol koreksi nama pada barisnya sebelum diterbitkan. Bila sudah terbit, cabut sertifikatnya lalu terbitkan ulang.',
        ],
        [
            'Kenapa peserta yang sudah punya sertifikat tidak bisa dihapus?',
            'Menghapusnya hanya melepaskan sertifikat dari pemiliknya — dokumennya tetap ada tetapi tidak terlihat lagi. Gunakan pencabutan bila sertifikatnya memang harus dibatalkan.',
        ],
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Cara kerja MSC Hub</x-slot>
            <x-slot name="description">
                Semuanya berawal dari pengajuan warga kampus lewat halaman publik, lalu diproses di panel ini.
            </x-slot>

            <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                Ada empat hal yang dikerjakan di sini: <strong>permintaan konten</strong>,
                <strong>peminjaman ruangan dan alat</strong>, <strong>penerbitan sertifikat</strong>, dan
                <strong>arsip media</strong>. Tiga yang pertama selalu menempuh pola yang sama —
                diajukan, ditinjau staf, disetujui Kepala MSC, lalu selesai — dan pemohon dikabari
                lewat email pada tiap perubahan status.
            </p>

            <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                Halaman depan panel menampilkan <strong>Perlu Tindakan Anda</strong>: apa saja yang
                sedang menunggu, lengkap dengan jalan pintas ke sana. Mulailah dari situ.
            </p>
        </x-filament::section>

        @foreach ($alur as $item)
            <x-filament::section collapsible :collapsed="! $loop->first">
                <x-slot name="heading">{{ $loop->iteration }}. {{ $item['judul'] }}</x-slot>
                <x-slot name="description">{{ $item['ringkas'] }}</x-slot>

                <ol class="space-y-3">
                    @foreach ($item['langkah'] as $nomor => $langkah)
                        <li class="flex gap-3">
                            <span @class([
                                'flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                                'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-200' => $item['warna'] === 'primary',
                                'bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-200' => $item['warna'] === 'warning',
                                'bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-200' => $item['warna'] === 'success',
                                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' => $item['warna'] === 'gray',
                            ])>{{ $nomor + 1 }}</span>

                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $langkah[0] }}</span>
                                <span class="mt-0.5 block text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $langkah[1] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-5 flex flex-wrap gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @foreach ($item['tautan'] as $route => $label)
                        <x-filament::link :href="route($route)">{{ $label }} &rarr;</x-filament::link>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Siapa boleh apa</x-slot>

                <dl class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    @foreach ($akses as [$peran, $keterangan])
                        <div class="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[9rem_1fr] sm:gap-3">
                            <dt class="font-medium text-gray-900 dark:text-white">{{ $peran }}</dt>
                            <dd class="text-gray-500 dark:text-gray-400">{{ $keterangan }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Yang sering ditanyakan</x-slot>

                <div class="space-y-4 text-sm">
                    @foreach ($tanya as [$pertanyaan, $jawaban])
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $pertanyaan }}</p>
                            <p class="mt-1 leading-relaxed text-gray-500 dark:text-gray-400">{{ $jawaban }}</p>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Butuh bantuan?</x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Hubungi tim Media &amp; Strategic Communications, Jakarta Global University.
                Sebutkan kode pengajuannya — misalnya <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">CR-2026-0001</code>
                atau <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">ROOM-2026-0001</code> — supaya lebih cepat ditelusuri.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
