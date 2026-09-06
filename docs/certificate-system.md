# Sistem Sertifikat MSC Hub

## Stack

- Laravel 12 + MySQL untuk domain, token verifikasi, dan route publik.
- Filament 4 untuk template, event, penerima, import, publikasi, dan pencabutan.
- Dompdf untuk PDF satu halaman dengan elemen posisi absolut dan font TTF.
- chillerlan/php-qrcode 5.0.5 untuk QR PNG; paket sudah tersedia pada proyek.
- Alpine.js untuk editor visual drag-and-drop tanpa SPA tambahan.
- maatwebsite/excel 3.1 untuk import peserta dari .xlsx/.csv.
- Laravel queue (driver database) untuk penerbitan batch dan pengiriman email sertifikat.

Fabric.js tidak digunakan pada MVP karena state canvas Fabric harus dirender ulang secara identik di server. Editor koordinat menghasilkan JSON sederhana dan PDF yang lebih deterministik. Jika kebutuhan desain berkembang menjadi rotasi, shape, curved text, atau layer kompleks, Fabric.js dapat ditambahkan sebagai editor sambil mempertahankan schema elemen internal.

## Identitas dan peran

Master `Participant` menyimpan identitas orang, sedangkan `CertificateEventParticipant` menyimpan perannya pada satu kegiatan. Satu orang dapat menjadi peserta pada satu kegiatan dan panitia atau pembicara pada kegiatan lain tanpa membuat identitas ganda.

Tipe identitas: mahasiswa, dosen, staf, dan guest. Peran kegiatan bawaan: peserta, panitia, pembicara, moderator, penyelenggara, juri, mentor, relawan, dan label custom seperti Ketua Pelaksana.

## Pipeline

```
[Tiga jalur masuk]                       [Inti]                    [Keluaran]
1. Absensi QR + login Google  ┐
2. Input manual admin         ├─► CertificateEventParticipant ─► review admin
3. Import Excel               ┘            │
                                           ▼
                        Bus::batch(IssueCertificateJob) ─► Certificate + nomor + UUID
                                           │
                                           ▼
                              SendCertificateEmailJob (antre)
                                           │
                                           ▼
                          verifikasi publik /verify/certificate/{token}
```

## Absensi

Setiap kegiatan dapat mengaktifkan absensi. Saat diaktifkan pertama kali sistem
membuat dua token ULID yang tidak pernah berubah, sehingga QR yang sudah dicetak
tetap berlaku.

**Check-in dan check-out sengaja dipisah** menjadi dua QR, dua URL, dan dua
window waktu. Tanpa pemisahan itu peserta dapat menutup kehadirannya sesaat
setelah membukanya, sehingga catatan kehadiran tidak mencerminkan keikutsertaan
yang sebenarnya.

| Aksi | URL peserta | Window | Poster QR |
|---|---|---|---|
| Check-in | `/attend/checkin/{checkin_token}` | `checkin_open_at` … `checkin_close_at` | `/admin/attendance/{event}/qr/checkin` |
| Check-out | `/attend/checkout/{checkout_token}` | `checkout_open_at` … `checkout_close_at` | `/admin/attendance/{event}/qr/checkout` |

- Peserta wajib login Google domain `@jgu.ac.id` atau `@student.jgu.ac.id`.
- Window divalidasi di server. Batas yang dikosongkan berarti tidak dibatasi
  dari sisi itu. Isi `checkout_open_at` mendekati akhir acara agar QR check-out
  tetap ditolak walau tautannya bocor lebih awal.
- Token yang dipakai pada aksi yang salah menghasilkan 404, karena setiap aksi
  hanya mencari pada kolom tokennya sendiri.
- Check-out hanya sah sebagai penutup check-in yang sudah tercatat; memindai QR
  check-out tanpa check-in ditolak dan diberi pesan agar menghubungi panitia.
- Mengulang aksi yang sama tidak menggeser jam yang sudah tercatat.
- Halaman poster memakai warna berbeda per aksi (biru untuk check-in, hijau
  untuk check-out) supaya panitia langsung sadar bila QR yang terpasang keliru.

Anti titip-absen pada tahap ini bertumpu pada QR statis, dua window waktu, dan
review admin. Rotating QR belum dikerjakan.

### Nama yang dicetak di sertifikat

Peserta yang **belum ada di master** diminta mengetik nama lengkapnya saat
check-in, lengkap dengan gelar bila ada. Nama itu yang dicetak di sertifikat.

- Nama hanya dapat diisi **sekali**. Setelah peserta tercatat pada sebuah
  kegiatan, halaman absensi menampilkannya sebagai teks mati beserta arahan
  menghubungi panitia.
- **Menghapus baris peserta dari panel mengembalikan haknya mengisi nama.**
  Bila nama telanjur salah ketik, panitia cukup menghapus barisnya lalu meminta
  peserta check-in ulang. Syaratnya record master itu memang lahir dari jalur
  absensi dan peserta tidak lagi tercatat di kegiatan mana pun.
- Nama yang diketik admin — input manual, import Excel, atau Master Participant
  — tetap terkunci walau peserta belum tercatat di kegiatan mana pun, agar
  koreksi resmi tidak tertimpa peserta.
- Peserta yang masih tercatat di kegiatan lain tetap terkunci namanya.
- Nama dari profil Google tetap disimpan terpisah di `google_display_name`
  sebagai bahan pembanding saat admin mengoreksi.
- Koreksi dilakukan admin lewat aksi "Koreksi Nama" pada tabel peserta.
- Validasi: 3–150 karakter, hanya huruf, spasi, titik, koma, apostrof, dan
  tanda hubung — angka dan simbol lain ditolak.

### Aturan kelayakan

Dipilih per kegiatan dan dievaluasi di satu tempat
(`CertificateEventParticipant::syncEligibility()`):

| Aturan | Peserta berhak ketika |
|---|---|
| `checkin_only` | `checked_in_at` terisi |
| `checkin_and_checkout` | `checked_in_at` dan `checked_out_at` terisi |
| `manual` | hanya ketika admin menandainya; absensi tidak mengubah flag |

Admin selalu dapat menimpa flag kelayakan untuk ketiga aturan.

## Alur staf

1. Buat desain latar PNG/JPG tanpa nama, nomor, dan QR.
2. Buat Template Sertifikat dan unggah desain.
3. Setelah template disimpan, sistem otomatis membuka Editor Visual.
4. Tambahkan variabel dari toolbar, lalu drag, resize, dan atur tipografi langsung di atas desain. Gunakan Preview Bersih untuk memeriksa hasil tanpa garis editor.
5. Buat event sertifikat dan pilih template.
6. Isi peserta lewat salah satu jalur: aktifkan absensi QR (pasang QR check-in
   di pintu masuk dan QR check-out menjelang acara bubar), tambahkan manual per
   email, atau import Excel.
7. Tandai kehadiran dan kelayakan secara individual atau bulk.
8. Tekan "Terbitkan Semua Eligible" (atau pilih baris lalu bulk action).
   Penerbitan berjalan di antrean; hasilnya dikabarkan lewat notifikasi panel.
9. Periksa contoh PDF, lalu ubah event menjadi `Dipublikasikan`.
10. Publikasi membuat QR valid dan otomatis mengirim email yang masih tertunda.
    Sertifikat dapat dicabut sewaktu-waktu tanpa mengubah QR.

Antrean wajib berjalan (`php artisan queue:work`) agar langkah 8 dan 10
menghasilkan sertifikat dan email.

## Variabel bawaan

- `recipient_name`
- `recipient_role`
- `certificate_number`
- `event_name`
- `event_date`
- `organizer`
- `signatory_name`
- `signatory_title`
- `verification_url`
- `qr_code`
- `custom_text`

Variabel tambahan per penerima disimpan dalam field `variables` dan dapat dikembangkan menjadi pilihan editor pada iterasi berikutnya.

## Editor visual

Editor mendukung penambahan elemen dari toolbar, drag-and-drop, resize dengan handle, layer selection, duplicate, delete, custom text, custom font TTF, warna, alignment, keyboard arrow untuk nudging, preview bersih, dan koordinat numerik sebagai kontrol presisi. Maksimal 50 elemen per template dan seluruh payload divalidasi ulang di server sebelum disimpan.

## Import peserta

Berkas `.xlsx` atau `.csv`, header di baris 1, maksimal 500 baris data, maksimal
2 MB. Kolom dicocokkan berdasarkan **nama** sehingga urutannya bebas; kolom yang
tidak dikenal diabaikan dengan peringatan.

| Kolom | Wajib | Dipetakan ke |
|---|---|---|
| `nama_sertifikat` | ya | `participants.name` (nama yang dicetak) |
| `email` | ya | `participants.email` — kunci dedup |
| `peran` | tidak | `certificate_event_participants.role` (default Peserta) |
| `nim_nip` | tidak | `participants.institutional_id` |
| `unit_prodi` | tidak | `participants.study_program` |
| `nomor_sertifikat` | tidak | dipakai saat penerbitan; kosong → nomor otomatis |

Nilai `peran` yang diterima: Peserta, Panitia, Pembicara, Moderator,
Penyelenggara, Juri, Mentor, Relawan, Lainnya.

Alur dua langkah: unggah menghasilkan **pratinjau** (tidak menulis apa pun,
hasilnya di-cache 30 menit), lalu konfirmasi menulis dalam satu transaksi dan
membuang berkas sementara.

Aturan dedup: email dinormalkan (lowercase + trim). Email yang sudah ada di
master dipakai ulang dan **namanya tidak pernah ditimpa** — perbedaannya
dilaporkan sebagai peringatan. Peserta yang sudah terdaftar di kegiatan yang
sama dilewati dan dihitung pada laporan hasil.

Email di luar domain JGU diperbolehkan lewat import dan input manual (tipe
`guest`); jalur absensi QR tetap ketat domain JGU.

## Halaman verifikasi publik

`/verify/certificate/{token}` menampilkan **pratinjau sertifikat**, bukan sekadar
tabel data, sehingga pemeriksa langsung melihat dokumen yang dimaksud.

Pratinjau dirender oleh komponen `x-molecules.certificate-preview` memakai
template, koordinat elemen, dan variabel yang **sama persis** dengan berkas PDF —
satu sumber data, dua keluaran. Ukurannya relatif seluruhnya: posisi dan dimensi
dalam persen, tipografi dalam `cqw` terhadap container kanvas. Hasilnya sebangun
dengan PDF di lebar layar mana pun tanpa JavaScript dan tanpa render gambar di
server.

Sertifikat yang tidak berlaku tetap ditampilkan, tetapi diredupkan dan diberi
cap "Tidak Berlaku", disertai keterangan apakah kegiatannya belum dipublikasikan
atau sertifikatnya dicabut beserta tanggalnya. Tombol unduh hanya muncul untuk
sertifikat yang valid.

Halaman juga menyediakan QR dan tombol salin tautan agar mudah dibagikan, serta
diberi `noindex` supaya nama penerima tidak terindeks mesin pencari.

## Penomoran sertifikat

Nomor mengikuti ketentuan kampus, bukan pola acak. Ada tiga tingkat, yang lebih
khusus selalu menang:

1. **Nomor manual per peserta** — kolom `nomor_sertifikat` pada file import atau
   kolom "Nomor sertifikat khusus" pada peserta. Dipakai apa adanya dan tidak
   menghabiskan jatah nomor urut otomatis.
2. **Pola khusus kegiatan** — kolom "Pola khusus kegiatan ini" pada formulir
   kegiatan. Untuk kegiatan yang penomorannya berbeda dari kebiasaan unit.
3. **Pola default kampus** — halaman panel *Sertifikat → Penomoran Sertifikat*.
   Berlaku otomatis untuk seluruh sertifikat yang tidak tercakup dua tingkat di
   atas.

### Token pola

| Token | Hasil |
|---|---|
| `{nomor}` | Nomor urut, mis. `57`. `{nomor:4}` memberi bantalan nol menjadi `0057` |
| `{tahun}` / `{tahun_pendek}` | `2026` / `26` |
| `{bulan}` / `{bulan_romawi}` | `09` / `IX` |
| `{tanggal}` | `06` |
| `{kode_kegiatan}` | Kode pada kegiatan; bila kosong memakai nama kegiatan tanpa spasi |
| `{kode_unit}` | Kode unit penerbit dari pengaturan, mis. `MSC-JGU` |

Contoh: `{nomor:4}/CERT/{kode_unit}/{bulan_romawi}/{tahun}` menghasilkan
`0057/CERT/MSC-JGU/IX/2026`.

Pola **wajib memuat `{nomor}`** — divalidasi di panel — karena tanpa nomor urut
setiap sertifikat akan menghasilkan teks yang sama dan bertabrakan.

### Nomor urut

Diambil dari tabel `certificate_number_sequences` dengan penguncian baris,
sehingga dua job dalam satu batch tidak pernah memperoleh nomor sama. Kapan
urutan kembali ke 1 dapat dipilih: setiap tahun (bawaan), setiap bulan, setiap
kegiatan, atau tidak pernah.

Mengubah pola tidak menyentuh nomor yang sudah terbit.

## Aturan validitas

Sertifikat valid hanya jika event berstatus `published`, `issued_at` terisi, dan `revoked_at` kosong. QR berisi URL dengan UUID acak, bukan primary key database. Halaman verifikasi tetap menampilkan status tidak berlaku untuk sertifikat yang dicabut.

Definisi ini hidup di satu tempat, `Certificate::isValid()`, dan dipakai oleh
route unduh, aksi panel, serta job pengiriman email.

## Penerbitan dan email

Penerbitan berjalan asinkron: satu `IssueCertificateJob` per peserta eligible di
dalam satu `Bus::batch`. Setiap job idempotent — keikutsertaan yang sudah punya
sertifikat dilewati, dan unique index pada `certificates.event_participant_id`
menjaga hal itu di level database. Nomor sertifikat khusus dari import dipakai
apa adanya; selebihnya dibuat otomatis dan diperiksa keunikannya.

`SendCertificateEmailJob` mengirim satu email per sertifikat berisi tautan unduh
dan tautan verifikasi (PDF tidak dilampirkan, agar penerima selalu mendapat
versi terbaru). Idempotensinya dijaga kolom `emailed_at`; kegagalan final
dicatat pada `email_failed_at` dan `email_error`, dan dapat ditindaklanjuti
dengan aksi "Kirim Ulang Email".

Email hanya dikirim untuk sertifikat valid. Bila penerbitan dilakukan sebelum
publikasi, email menyusul otomatis saat kegiatan diubah menjadi
`Dipublikasikan` (`CertificateEventObserver`).

`APP_URL` wajib menunjuk domain produksi karena dipakai membentuk URL absolut
pada QR dan tombol email.
