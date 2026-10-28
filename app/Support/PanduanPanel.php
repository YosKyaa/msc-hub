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
                    ['Siapkan desainnya', 'Di menu Desain Sertifikat: unggah gambar latar, lalu atur letak nama, nomor, dan QR. Satu desain bisa dipakai berkali-kali. Rinciannya ada di panduan Desain Sertifikat & Font.'],
                    ['Buat kegiatan', 'Di menu Kegiatan & Peserta: pilih desain dan penerbitnya, lalu ubah statusnya menjadi Dipublikasikan agar sertifikatnya sah. Desainnya boleh dikosongkan bila seluruh sertifikatnya didesain sendiri.'],
                    ['Daftarkan peserta', 'Impor dari Excel, tambah satu per satu, atau biarkan masuk sendiri lewat absensi QR. Lalu tandai siapa yang berhak menerima.'],
                    ['Terbitkan digital', 'Nomor diberikan dan halaman verifikasinya langsung aktif. Email BELUM dikirim, jadi hasilnya masih bisa diperiksa dulu.'],
                    ['Kirim email', 'Setelah hasilnya diperiksa. Yang sudah menerima tidak dikirimi ulang. Kirimannya dijarakkan beberapa detik agar tidak ditolak penyedia email, jadi seratus peserta memakan sekitar lima menit.'],
                ],
                'catatan' => [
                    'Setiap kegiatan menampilkan alur empat langkahnya di bagian paling atas, lengkap dengan angka dan satu kalimat tentang apa yang harus dikerjakan berikutnya.',
                    'Menerbitkan dan mengirim sengaja dipisah, supaya penerbitan yang keliru tidak terlanjur mendarat di kotak masuk peserta.',
                    'Ragu emailnya jalan atau tidak? Tombol Kirim Email Uji di atas halaman kegiatan mengirim satu contoh ke alamat Anda sendiri, lewat jalur yang sama persis dan tanpa menyentuh peserta.',
                    'Email yang gagal terkirim tercatat sebabnya di kolom Kendala email pada tabel peserta, dan jumlahnya ikut disebut di alur empat langkah.',
                    'Tiap penerbit — Rektorat, SCD, jurusan, MSC — memegang pola dan urutan nomornya sendiri, dan nomornya bisa diselaraskan dengan register yang sudah berjalan.',
                    'Bulan dan tahun pada nomor sertifikat mengikuti tanggal kegiatan. Bila perlu tanggal lain, ganti lewat Tanggal pada nomor sertifikat di bagian Penomoran Sertifikat pada kegiatannya.',
                    'Baris peserta yang dikuningkan belum punya alamat email. Sertifikatnya tetap terbit dan sah, hanya tidak terkirim; lengkapi alamatnya lewat tombol koreksi pada barisnya. Filter Belum ada email menampilkan mereka saja.',
                    'Kegiatan yang menyalakan Izinkan peserta di luar JGU menerima absensi dari akun Google apa pun, termasuk Gmail pribadi.',
                    'Sertifikat yang didesain di luar sistem — misalnya untuk pemenang dan pembicara — punya alurnya sendiri. Lihat panduan Sertifikat Desain Sendiri.',
                    'Peserta yang sudah memegang sertifikat tidak bisa dihapus; gunakan pencabutan bila dokumennya memang harus dibatalkan.',
                ],
                'tautan' => [
                    'filament.admin.resources.certificate-events.index' => 'Kegiatan & Peserta',
                    'filament.admin.resources.certificate-templates.index' => 'Desain Sertifikat',
                    'filament.admin.resources.issuers.index' => 'Penerbit',
                ],
            ],

            'desain-sertifikat' => [
                'judul' => 'Desain Sertifikat & Font',
                'ringkas' => 'Atur letak nama, nomor, dan QR, serta pakai font sendiri.',
                'warna' => 'success',
                'ikon' => 'heroicon-o-swatch',
                'langkah' => [
                    ['Unggah gambar latar', 'Di menu Desain Sertifikat, buat template lalu unggah latarnya: PNG atau JPG landscape, sebaiknya 1123 × 794 px. Kompres dulu bila besar, karena gambar ini ikut tertanam di setiap PDF.'],
                    ['Unggah font, bila perlu', 'Di bagian Font Kustom pada formulir yang sama. Font yang diunggah muncul di editor dalam kelompok Font unggahan, dengan nama berkas aslinya.'],
                    ['Tambah elemen', 'Setelah template disimpan, editornya terbuka sendiri; dari daftar template, buka lewat Atur Letak Tulisan. Tambahkan nama, nomor, peran, QR, atau teks dari toolbar, lalu atur font, ukuran, ketebalan, dan warnanya di panel Properti elemen.'],
                    ['Tempatkan kotaknya', 'Seret kotaknya; di dekat tengah desain ia menempel sendiri dan garis bantu merah muda muncul. Deretan Posisi di kanvas menaruhnya tepat di tepi atau tengah, dan tombol panah menggesernya 1 piksel (10 piksel dengan Shift).'],
                    ['Periksa lalu simpan', 'Kotak yang teksnya tidak muat ditandai merah. Lebarkan kotaknya atau kecilkan hurufnya, lalu tekan Simpan template.'],
                ],
                'catatan' => [
                    'Posisi di kanvas menggeser kotaknya, sedangkan Perataan mendatar dan Perataan tegak meratakan teks di dalam kotak. Untuk nama yang benar-benar di tengah sertifikat, pakai keduanya.',
                    'Font yang diterima: .ttf, dan .otf berbasis TrueType. OpenType berbasis CFF, font web .woff dan .woff2, kumpulan font .ttc, dan berkas rusak ditolak beserta alasannya — unggah versi .ttf-nya.',
                    'Semi Bold dan Bold dicetak dengan versi tebal dari font yang sama, karena PDF hanya mengenal dua ketebalan: biasa dan tebal.',
                    'Satu berkas font unggahan dipakai untuk huruf biasa maupun tebal, jadi memilih Bold tidak membuatnya lebih tebal — di editor maupun di PDF. Butuh versi tebalnya? Unggah juga berkas Bold dari font itu, lalu pilih untuk elemen yang ingin tebal.',
                    'Bila muncul keterangan merah di bawah isian Berkas font, folder font di server tidak bisa ditulisi dan PDF memakai font cadangan. Minta pengelola server menjalankan bash deploy.sh.',
                ],
                'tautan' => [
                    'filament.admin.resources.certificate-templates.index' => 'Desain Sertifikat',
                ],
            ],

            'desain-sendiri' => [
                'judul' => 'Sertifikat Desain Sendiri',
                'ringkas' => 'Nomor dan QR terbit lebih dulu, desainnya dibuat di luar sistem.',
                'warna' => 'success',
                'ikon' => 'heroicon-o-qr-code',
                'langkah' => [
                    ['Terbitkan nomor & QR', 'Di tabel peserta kegiatan, centang penerimanya — misalnya para pemenang atau pembicara — lalu pilih Terbitkan Nomor & QR (Desain Sendiri) dari menu aksi massal. Template tidak diperlukan, dan yang belum ditandai berhak ikut ditandai.'],
                    ['Unduh Paket QR', 'Lewat tombol Desain Sendiri di atas tabel. Isinya QR tiap penerima dalam SVG dan PNG 1440 px, Daftar QR.xlsx berisi nomor, nama, dan perannya, serta petunjuk untuk desainer.'],
                    ['Desain di luar sistem', 'Di Canva, Photoshop, atau Illustrator, dengan kualitas penuh. Pasang QR minimal 2,5 × 2,5 cm tanpa dipotong atau diubah warnanya, lalu tulis nomor, nama, dan peran persis seperti di Daftar QR.xlsx.'],
                    ['Unggah berkas jadinya', 'Desain Sendiri → Unggah Berkas Desain, banyak sekaligus: PDF, JPG, atau PNG. Berkas yang namanya diawali nomor sertifikat — seperti nama berkas di Paket QR — atau memuat nama penerimanya dipasangkan sendiri.'],
                    ['Publikasikan & kirim email', 'Setelah kegiatan dipublikasikan, QR-nya menyatakan sertifikat sah. Kirim email seperti biasa; penerima yang berkasnya belum diunggah dilewati.'],
                ],
                'catatan' => [
                    'Nomor dan QR yang sudah terbit tidak pernah berubah, jadi desainnya boleh dikerjakan belakangan dan QR yang sudah tercetak tetap sah.',
                    'Selama berkas jadinya belum diunggah, tahapnya tertulis Menunggu desain, dan tombol unduh serta kirim email belum muncul untuk orang itu.',
                    'Berkas yang tidak cocok dengan siapa pun disebutkan setelah diunggah. Pasang satu per satu lewat tombol unggah pada barisnya; tombol yang sama dipakai untuk mengganti berkas.',
                    'Gambar yang sisi terpanjangnya di bawah 1000 px ditolak karena hasil cetaknya akan pecah. Ekspor sedikitnya 2000 px, atau sebagai PDF.',
                    'Halaman verifikasi menampilkan desain aslinya. Sebelum berkasnya diunggah, pemeriksa diminta mencocokkan nama, peran, dan nomor dengan rincian di halaman itu.',
                    'Satu kegiatan boleh mencampur keduanya: peserta biasa dari template, pemenang dan pembicara didesain sendiri. Paket QR juga bisa diunduh untuk peserta yang dicentang saja.',
                    'Berkas jadi disimpan privat, hanya bisa dibuka lewat tautan sertifikatnya, dan ikut dicadangkan setiap malam.',
                ],
                'tautan' => [
                    'filament.admin.resources.certificate-events.index' => 'Kegiatan & Peserta',
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
                        'Kegiatannya belum memakai template yang aktif, atau belum ada peserta yang ditandai berhak. Keduanya disebutkan di pesan penolakannya. Bila sertifikatnya memang didesain sendiri, template tidak diperlukan: centang penerimanya lalu pakai Terbitkan Nomor & QR (Desain Sendiri).',
                    ],
                    [
                        'Sudah diterbitkan, kenapa emailnya belum sampai?',
                        'Menerbitkan dan mengirim memang dua langkah terpisah, supaya penerbitan yang keliru tidak terlanjur mendarat di kotak masuk. Tekan Kirim Email setelah hasilnya diperiksa.',
                    ],
                    [
                        'Email tidak pernah terkirim sama sekali.',
                        'Buka kegiatannya lalu tekan Kirim Email Uji dan isi alamat Anda sendiri. Email itu menempuh jalur yang sama persis tanpa menyentuh peserta, dan bila gagal jawaban server emailnya ditampilkan apa adanya — di situlah sebabnya terbaca.',
                    ],
                    [
                        'Kenapa emailnya lama sekali sampainya?',
                        'Kirimannya sengaja dijarakkan beberapa detik. Penyedia email menolak kiriman yang terlalu rapat lalu menggugurkan sisanya, sehingga mengirim serentak justru membuat sebagian besarnya tidak sampai. Perkiraan lamanya disebutkan saat tombol kirim ditekan.',
                    ],
                    [
                        'Ada peserta yang emailnya gagal. Bagaimana tahu sebabnya?',
                        'Di tabel peserta, tampilkan kolom Kendala email lewat tombol pengaturan kolom. Sebabnya tercatat di situ, misalnya alamat tidak ditemukan atau kotak masuknya penuh. Setelah dibereskan, pakai tombol Kirim Ulang Email pada barisnya.',
                    ],
                    [
                        'Panel bilang tidak ada pekerja antrean.',
                        'Email hanya akan menumpuk dan tidak terkirim. Minta pengelola server menjalankan php artisan queue:work, atau menghidupkan kembali layanan Supervisor-nya. Setelah hidup, email yang menumpuk terkirim sendiri tanpa perlu ditekan ulang.',
                    ],
                    [
                        'Tulisan di sertifikat meluber atau bertumpuk.',
                        'Buka Desain Sertifikat lalu Atur Letak Tulisan. Elemen yang teksnya tidak muat ditandai merah. Lebarkan kotaknya, kecilkan ukuran hurufnya, atau tinggikan kotaknya.',
                    ],
                    [
                        'Desain latarnya gagal diunggah.',
                        'Berkasnya melebihi batas yang diterima server. Keterangan di bawah isiannya menyebut batas yang berlaku. Kompres gambarnya — latar yang besar juga membuat setiap PDF sertifikat ikut berat.',
                    ],
                    [
                        'Font gagal diunggah.',
                        'Pesan di bawah isiannya menyebut sebabnya. Yang diterima hanya .ttf dan .otf berbasis TrueType; untuk font lain, unggah versi .ttf-nya. Font yang dulu ditolak dengan pesan validation.mimetypes kini bisa diunggah ulang.',
                    ],
                    [
                        'Font tampil di editor, tetapi PDF-nya memakai huruf lain.',
                        'Folder font di server tidak bisa ditulisi — keterangan merah di bawah isian Berkas font menandainya. Minta pengelola server menjalankan bash deploy.sh.',
                    ],
                    [
                        'Sertifikat desain sendiri belum bisa diunduh atau dikirim.',
                        'Berkas jadinya belum diunggah; tahapnya tertulis Menunggu desain. Unggah lewat Desain Sendiri → Unggah Berkas Desain, atau lewat tombol unggah pada barisnya.',
                    ],
                    [
                        'Berkas desain tidak terpasang ke penerimanya.',
                        'Nama berkasnya tidak memuat nomor sertifikat, atau namanya cocok dengan lebih dari satu penerima. Beri nama seperti berkas QR di Paket QR, atau pasang lewat tombol unggah pada barisnya.',
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
     * Warna topik sebagai nilai jadi, bukan kelas Tailwind.
     *
     * Panel ini tidak memakai viteTheme(), sehingga hanya kelas yang sudah
     * dipakai Filament sendiri yang ikut terkompilasi — kelas karangan
     * sendiri tidak akan pernah berlaku. Nilai warnanya karena itu dipasang
     * langsung pada elemennya.
     *
     * @return array{latar: string, teks: string}
     */
    public static function warna(string $nama): array
    {
        return match ($nama) {
            'primary' => ['latar' => 'rgb(254 243 199)', 'teks' => 'rgb(180 83 9)'],
            'warning' => ['latar' => 'rgb(255 237 213)', 'teks' => 'rgb(194 65 12)'],
            'success' => ['latar' => 'rgb(209 250 229)', 'teks' => 'rgb(4 120 87)'],
            'info' => ['latar' => 'rgb(219 234 254)', 'teks' => 'rgb(29 78 216)'],
            'danger' => ['latar' => 'rgb(254 226 226)', 'teks' => 'rgb(185 28 28)'],
            default => ['latar' => 'rgb(244 244 245)', 'teks' => 'rgb(82 82 91)'],
        };
    }
}
