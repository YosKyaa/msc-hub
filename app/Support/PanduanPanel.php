<?php

namespace App\Support;

/**
 * Isi panduan panel.
 *
 * Satu sumber untuk kartu ringkasan maupun halaman rinciannya, supaya
 * keduanya tidak bisa menyebut hal yang berbeda. Panduannya disusun mengikuti
 * pekerjaan yang benar-benar dikerjakan di panel, bukan mengikuti daftar menu.
 */
class PanduanPanel
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function semua(): array
    {
        return [
            'permintaan-konten' => [
                'judul' => 'Permintaan Konten',
                'ringkas' => 'Warga kampus minta dibuatkan foto, video, atau desain.',
                'warna' => 'primary',
                'ikon' => 'heroicon-o-pencil-square',
                'langkah' => [
                    ['Pemohon mengajukan', 'Lewat halaman publik. Sistem memberi kode seperti CR-2026-0001 yang dipakai untuk menelusuri pengajuannya.'],
                    ['Staf meninjau', 'Buka Permintaan Konten, tetapkan penanggung jawab dan tenggatnya. Pemohon dikabari lewat email.'],
                    ['Dikerjakan', 'Ubah statusnya mengikuti perkembangan. Pemohon memantau sendiri dari halaman Konten Saya.'],
                    ['Kepala MSC menyetujui', 'Persetujuan akhir. Setelah itu hasilnya dipublikasikan dan pemohon dikabari.'],
                ],
                'catatan' => [
                    'Tenggat yang masuk akal minimal tiga hari kerja; pemohon diberi tahu hal ini di formulirnya.',
                    'Penolakan wajib menyertakan alasan, dan alasan itu ikut terkirim ke pemohon.',
                ],
                'tautan' => ['filament.admin.resources.content-requests.index' => 'Buka Permintaan Konten'],
            ],

            'peminjaman' => [
                'judul' => 'Peminjaman Ruangan & Alat',
                'ringkas' => 'Pinjam studio, ruang rapat, kamera, lighting, dan audio.',
                'warna' => 'warning',
                'ikon' => 'heroicon-o-building-office-2',
                'langkah' => [
                    ['Peminjam mengajukan', 'Memilih jadwal dan alat. Jadwal yang bentrok ditolak sistem sejak awal, jadi tidak perlu diperiksa manual.'],
                    ['Staf menyetujui', 'Periksa jadwal dan kelengkapannya, lalu setujui atau tolak dengan alasan.'],
                    ['Kepala MSC menyetujui', 'Persetujuan kedua. Peminjam dikabari lewat email pada tiap perubahan status.'],
                    ['Diambil & dikembalikan', 'Catat pengambilan dan pengembalian beserta kondisi alatnya, supaya kerusakan tidak tertukar antar peminjam.'],
                ],
                'catatan' => [
                    'Formulir resmi FM/JGU/L.89 bisa dibuka dari tiap pengajuan, lengkap dengan pratinjau sebelum diunduh.',
                    'Alat yang dipinjam ikut tercatat pada pengajuan ruangan, jadi tidak perlu dua pengajuan terpisah.',
                ],
                'tautan' => [
                    'filament.admin.resources.room-bookings.index' => 'Booking Ruangan',
                    'filament.admin.resources.inventory-bookings.index' => 'Peminjaman Alat',
                ],
            ],

            'sertifikat' => [
                'judul' => 'Sertifikat',
                'ringkas' => 'Terbitkan sertifikat kegiatan, lengkap dengan halaman verifikasi.',
                'warna' => 'success',
                'ikon' => 'heroicon-o-academic-cap',
                'langkah' => [
                    ['Siapkan kegiatan', 'Buat kegiatan, pilih templat dan penerbitnya, lalu publikasikan agar sertifikatnya sah.'],
                    ['Kumpulkan peserta', 'Lewat absensi QR, impor berkas, atau tambah satu per satu. Tandai siapa yang berhak menerima.'],
                    ['Terbitkan digital', 'Nomor diberikan dan halaman verifikasi langsung aktif. Email BELUM dikirim.'],
                    ['Kirim email', 'Setelah hasilnya diperiksa. Yang sudah menerima tidak dikirimi ulang.'],
                ],
                'catatan' => [
                    'Menerbitkan dan mengirim sengaja dipisah, supaya penerbitan yang keliru tidak terlanjur mendarat di kotak masuk peserta.',
                    'Tiap penerbit — Rektorat, SCD, jurusan, MSC — memegang pola dan urutan nomornya sendiri, dan nomornya bisa diselaraskan dengan register yang sudah berjalan.',
                    'Peserta yang sudah memegang sertifikat tidak bisa dihapus; gunakan pencabutan bila dokumennya memang harus dibatalkan.',
                ],
                'tautan' => [
                    'filament.admin.resources.certificate-events.index' => 'Kegiatan Sertifikat',
                    'filament.admin.resources.issuers.index' => 'Penerbit',
                    'filament.admin.resources.certificate-templates.index' => 'Templat Sertifikat',
                ],
            ],

            'arsip-media' => [
                'judul' => 'Arsip Media',
                'ringkas' => 'Simpan hasil karya agar mudah dicari kembali.',
                'warna' => 'gray',
                'ikon' => 'heroicon-o-archive-box',
                'langkah' => [
                    ['Buat Project', 'Satu project untuk satu event atau kegiatan, lengkap dengan unit dan tanggalnya.'],
                    ['Tambah Asset', 'Foto, video, atau desain, beserta tautan sumber dan hasil akhirnya.'],
                    ['Beri Tag', 'Supaya bisa ditemukan lewat pencarian berbulan-bulan kemudian.'],
                ],
                'catatan' => [
                    'Tautan sumber menunjuk berkas mentahnya, tautan hasil menunjuk yang sudah tayang.',
                ],
                'tautan' => [
                    'filament.admin.resources.projects.index' => 'Projects',
                    'filament.admin.resources.assets.index' => 'Assets',
                    'filament.admin.resources.tags.index' => 'Tags',
                ],
            ],

            'hak-akses' => [
                'judul' => 'Siapa Boleh Apa',
                'ringkas' => 'Pembagian wewenang antar peran di panel.',
                'warna' => 'info',
                'ikon' => 'heroicon-o-shield-check',
                'peran' => [
                    ['Admin', 'Seluruh sistem, termasuk pengguna dan peran.'],
                    ['Kepala MSC', 'Persetujuan akhir untuk semua pengajuan, dan penerbitan sertifikat.'],
                    ['Staf MSC', 'Meninjau pengajuan, mengelola inventaris, arsip, dan sertifikat.'],
                    ['Dosen / Mahasiswa', 'Tidak masuk panel. Mengajukan lewat halaman publik.'],
                ],
                'catatan' => [
                    'Sesi panel berakhir setelah dua jam tanpa aktivitas, dan paling lama delapan jam sejak masuk.',
                ],
                'tautan' => [
                    'filament.admin.resources.users.index' => 'Pengguna',
                    'filament.admin.resources.roles.index' => 'Peran',
                ],
            ],

            'kendala-umum' => [
                'judul' => 'Kendala Umum',
                'ringkas' => 'Yang paling sering ditanyakan, beserta jawabannya.',
                'warna' => 'danger',
                'ikon' => 'heroicon-o-lifebuoy',
                'tanya' => [
                    [
                        'Kenapa sertifikat tidak bisa diterbitkan?',
                        'Kegiatannya belum memakai templat yang aktif, atau belum ada peserta yang ditandai berhak. Keduanya disebutkan di pesan penolakannya.',
                    ],
                    [
                        'Sudah diterbitkan, kenapa emailnya belum sampai?',
                        'Menerbitkan dan mengirim memang dua langkah terpisah, supaya penerbitan yang keliru tidak terlanjur mendarat di kotak masuk. Tekan Kirim Email setelah hasilnya diperiksa.',
                    ],
                    [
                        'Email tidak pernah terkirim sama sekali.',
                        'Pengiriman email menunggu pekerja antrean. Pastikan php artisan queue:work berjalan di server. Panel akan memperingatkan bila antreannya menumpuk.',
                    ],
                    [
                        'Peserta salah ketik namanya di sertifikat.',
                        'Pakai tombol koreksi nama pada barisnya sebelum diterbitkan. Bila sudah terbit, cabut sertifikatnya lalu terbitkan ulang.',
                    ],
                    [
                        'Nomor sertifikat tidak sesuai register unit.',
                        'Buka Penerbit, lalu setel nomor berikutnya agar sejalan dengan register yang sudah berjalan di unit tersebut.',
                    ],
                    [
                        'Peminjam bilang tombol kirimnya galat.',
                        'Periksa catatan aplikasi di storage/logs. Sejak perbaikan terakhir, kegagalan pemberitahuan tidak lagi menggagalkan pengajuannya.',
                    ],
                ],
                'tautan' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function topik(string $slug): ?array
    {
        return static::semua()[$slug] ?? null;
    }

    /**
     * Kelas warna ditulis utuh, bukan dirangkai dari nama warnanya, supaya
     * tetap hidup ketika kelasnya dipindai saat pembuatan aset.
     *
     * @return array{kotak: string, angka: string}
     */
    public static function warna(string $nama): array
    {
        return match ($nama) {
            'primary' => ['kotak' => 'bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-400', 'angka' => 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-200'],
            'warning' => ['kotak' => 'bg-warning-50 text-warning-600 dark:bg-warning-950 dark:text-warning-400', 'angka' => 'bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-200'],
            'success' => ['kotak' => 'bg-success-50 text-success-600 dark:bg-success-950 dark:text-success-400', 'angka' => 'bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-200'],
            'info' => ['kotak' => 'bg-info-50 text-info-600 dark:bg-info-950 dark:text-info-400', 'angka' => 'bg-info-100 text-info-700 dark:bg-info-900 dark:text-info-200'],
            'danger' => ['kotak' => 'bg-danger-50 text-danger-600 dark:bg-danger-950 dark:text-danger-400', 'angka' => 'bg-danger-100 text-danger-700 dark:bg-danger-900 dark:text-danger-200'],
            default => ['kotak' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400', 'angka' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200'],
        };
    }
}
