# Sistem Sertifikat MSC Hub

## Stack

- Laravel 12 + MySQL untuk domain, token verifikasi, dan route publik.
- Filament 4 untuk template, event, penerima, import, publikasi, dan pencabutan.
- Dompdf untuk PDF satu halaman dengan elemen posisi absolut dan font TTF.
- chillerlan/php-qrcode 5.0.5 untuk QR PNG; paket sudah tersedia pada proyek.
- Alpine.js untuk editor visual drag-and-drop tanpa SPA tambahan.
- maatwebsite/excel 3.1 untuk import peserta dari .xlsx/.csv.
- Laravel queue (driver database) untuk pengiriman email sertifikat, dan untuk
  penerbitan yang pesertanya melebihi batas penerbitan langsung.

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
              1. Terbitkan Digital (langsung s.d. 100 orang,
                 di atasnya Bus::batch) ─► Certificate + nomor + UUID
                                           │
                                           ▼
                          verifikasi publik /verify/certificate/{token}
                                           │
                                           ▼
              2. Kirim Email ─► SendCertificateEmailJob (antre, dijarakkan)
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

Menu **Sertifikat** di panel diurutkan menurut urutan kerjanya: Kegiatan &
Peserta, Desain Sertifikat, Cari Sertifikat, lalu dua pengaturan yang diisi
sekali saja (Penerbit dan Format Nomor).

1. Buat desain latar PNG/JPG tanpa nama, nomor, dan QR. Maksimal mengikuti
   batas PHP di server; kompres ke bawah 1 MB karena gambar ini ditanam ke
   dalam **setiap** PDF sertifikat.
2. Buka **Desain Sertifikat**, unggah latarnya, lalu tekan **Atur Letak
   Tulisan** untuk membuka editor visual.
3. Tambahkan variabel dari toolbar, lalu geser, ubah ukuran, dan atur
   tipografi serta perataannya langsung di atas desain. Elemen yang teksnya
   tidak muat ditandai merah. Gunakan Preview Bersih untuk melihat hasilnya
   tanpa garis editor.
4. Buka **Kegiatan & Peserta**, buat kegiatan, pilih desain dan penerbitnya.
5. Ubah statusnya menjadi `Dipublikasikan`. Selama masih `draft`, sertifikat
   belum sah dan emailnya tidak bisa dikirim.
6. Isi peserta lewat salah satu jalur: aktifkan absensi QR (pasang QR check-in
   di pintu masuk dan QR check-out menjelang acara bubar), tambahkan manual per
   email, atau impor Excel.
7. Tandai kehadiran dan kelayakan secara individual atau bulk.
8. Tekan **1. Terbitkan Digital**. Nomor diberikan dan halaman verifikasinya
   langsung aktif — email belum dikirim.
9. Periksa hasilnya lewat tombol lihat atau unduh pada salah satu peserta.
10. Tekan **2. Kirim Email**. Sertifikat dapat dicabut sewaktu-waktu tanpa
    mengubah QR.

Setiap kegiatan menampilkan keempat langkah itu di bagian paling atas
halamannya, lengkap dengan angka tiap langkah dan satu kalimat tentang apa yang
harus dikerjakan berikutnya (`App\Support\CertificateProgress`).

**Penerbitan tidak lagi memerlukan antrean** selama pesertanya tidak lebih dari
`MSC_INLINE_ISSUE_LIMIT` (bawaan 100): sertifikatnya dibuat seketika saat tombol
ditekan. Di atas angka itu penerbitan dipecah ke antrean.

**Pengiriman email selalu lewat antrean**, jadi `php artisan queue:work` wajib
berjalan di server untuk langkah 10.

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

Editor mendukung penambahan elemen dari toolbar, geser dan ubah ukuran,
pemilihan layer, duplikat, hapus, teks statis, font TTF sendiri, warna,
perataan mendatar (kiri/tengah/kanan) dan tegak (atas/tengah/bawah), tombol
panah untuk menggeser presisi, preview bersih, serta koordinat numerik.
Maksimal 50 elemen per template dan seluruh payload divalidasi ulang di server
sebelum disimpan.

### Satu tata letak, tiga penggambar

Sertifikat yang sama digambar tiga kali: di editor, di halaman verifikasi, dan
di dalam PDF. Ketiganya dulu menyusun CSS-nya sendiri-sendiri dan karenanya
tidak pernah benar-benar sama — editor meratakan teks ke tengah secara tegak
lalu **menyembunyikan** yang meluber, sementara dua lainnya menempelkan teks ke
atas dan membiarkannya meluber. Admin melihat satu baris rapi saat merancang,
penerima menerima baris bertumpuk.

Seluruh keputusan tata letak kini tinggal di `App\Support\CertificateElement`.
Yang berbeda antar ketiganya hanya satuan: piksel kanvas untuk PDF, persen dan
`cqw` untuk pratinjau. Editor tidak lagi memotong teks yang tidak muat —
elemennya ditandai merah beserta alasannya.

Perataan tegak dipasang lewat `display:table-cell` yang **membawa tingginya
sendiri**. Dompdf menerima `vertical-align` pada bentuk lain lalu mengabaikannya
diam-diam, jadi `tests/Feature/CertificateLayoutParityTest.php` memeriksa
koordinat yang sungguh dihasilkan PDF, bukan sekadar bahwa aturannya tertulis.

Template lama tidak punya kunci `valign`; bawaannya rata tengah, mengikuti apa
yang selama ini ditampilkan editor, supaya desain yang terlanjur dibuat tidak
bergeser.

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

## Penerbit sertifikat

MSC dapat menerbitkan sertifikat untuk pihak di luar JGU. Penerbit adalah
record tersendiri (`issuers`), bukan sekadar teks penyelenggara, sehingga
identitas dan penomoran dapat menempel padanya.

- **Penerbit rumah** — MSC JGU, ditandai `is_house`. Kegiatan yang tidak
  menyebut penerbit otomatis memakainya, jadi data lama tidak perlu disentuh
  dan tampilannya tidak berubah.
- **Penerbit mitra** — membawa nama, kode, logo, alamat, dan pola nomornya
  sendiri.

Yang mengikuti penerbit:

| Bagian | Perilaku |
|---|---|
| Halaman verifikasi | Logo, nama, alamat, dan kalimat penjaminnya |
| Kop email sertifikat | Logo dan nama penerbit |
| Kode `{kode_unit}` | Kode penerbit, mis. `LPPI` |
| Urutan nomor | Terpisah per penerbit — sertifikat mitra tidak menggerus urutan JGU |
| Variabel `{organizer}` | Nama penerbit bila kolom penyelenggara dikosongkan |

Untuk penerbit di luar JGU, kalimat bawaan di halaman verifikasi menyebut peran
MSC secara terpisah: *"Diterbitkan oleh {penerbit}, difasilitasi Media &
Strategic Communications Jakarta Global University"*. Kalimat ini dapat diubah
per penerbit lewat kolom **Kalimat pada halaman verifikasi**, karena
bunyinya adalah keputusan kelembagaan, bukan teknis.

Desain sertifikatnya sendiri sudah netral sejak awal — berupa gambar latar dan
elemen berkoordinat — jadi mitra cukup mengunggah templatenya sendiri.

## Penomoran sertifikat

Nomor mengikuti ketentuan kampus, bukan pola acak. Ada tiga tingkat, yang lebih
khusus selalu menang:

1. **Nomor manual per peserta** — kolom `nomor_sertifikat` pada file import atau
   kolom "Nomor sertifikat khusus" pada peserta. Dipakai apa adanya dan tidak
   menghabiskan jatah nomor urut otomatis.
2. **Pola khusus kegiatan** — kolom "Pola khusus kegiatan ini" pada formulir
   kegiatan. Untuk kegiatan yang penomorannya berbeda dari kebiasaan unit.
3. **Pola penerbit** — kolom pola pada *Sertifikat → Penerbit*. Untuk mitra yang
   punya ketentuan penomoran sendiri.
4. **Pola default sistem** — halaman panel *Sertifikat → Penomoran Sertifikat*.
   Berlaku otomatis untuk seluruh sertifikat yang tidak tercakup di atas.

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
sehingga dua job dalam satu batch tidak pernah memperoleh nomor sama. Cakupannya
selalu diawali penerbit (`issuer-3:year-2026`), jadi setiap penerbit memiliki
urutan sendiri. Kapan
urutan kembali ke 1 dapat dipilih: setiap tahun (bawaan), setiap bulan, setiap
kegiatan, atau tidak pernah.

Mengubah pola tidak menyentuh nomor yang sudah terbit.

## Aturan validitas

Sertifikat valid hanya jika event berstatus `published`, `issued_at` terisi, dan `revoked_at` kosong. QR berisi URL dengan UUID acak, bukan primary key database. Halaman verifikasi tetap menampilkan status tidak berlaku untuk sertifikat yang dicabut.

Definisi ini hidup di satu tempat, `Certificate::isValid()`, dan dipakai oleh
route unduh, aksi panel, serta job pengiriman email.

## Penerbitan dan email

Keduanya sengaja dipisah menjadi dua tombol. Menerbitkan **tidak** mengirim
apa pun: nomor diberikan dan halaman verifikasinya aktif, sehingga penerbitan
yang keliru tidak terlanjur mendarat di kotak masuk peserta.

### Penerbitan

Sampai `MSC_INLINE_ISSUE_LIMIT` peserta (bawaan 100) sertifikat dibuat seketika
di dalam permintaan itu juga; di atasnya dipecah menjadi `Bus::batch` berisi
`IssueCertificateJob`. Keputusannya ada di `CertificateBatchIssuer`.

Setiap job idempotent — keikutsertaan yang sudah punya sertifikat dilewati, dan
unique index pada `certificates.event_participant_id` menjaga hal itu di level
database. Nomor sertifikat khusus dari impor dipakai apa adanya; selebihnya
dibuat otomatis dan diperiksa keunikannya.

### Pengiriman email

`SendCertificateEmailJob` mengirim satu email per sertifikat berisi tautan unduh
dan tautan verifikasi. PDF tidak dilampirkan, agar penerima selalu mendapat
versi terbaru. Idempotensinya dijaga kolom `emailed_at`.

Email hanya dikirim untuk sertifikat valid, yaitu setelah kegiatannya
`Dipublikasikan`. **Publikasi tidak mengirim email dengan sendirinya** — admin
harus menekan "2. Kirim Email".

### Laju kirim

Hampir semua penyedia SMTP menolak kiriman yang terlalu rapat; milik kampus
menjawab `550 5.7.0 Too many emails per second` lalu menggugurkan sisanya,
sehingga melepas seratus email sekaligus justru membuat sebagian besarnya tidak
sampai.

`CertificateBatchMailer` karena itu menjarakkan tiap job menurut
`MSC_CERTIFICATE_EMAILS_PER_MINUTE` (bawaan 20, yaitu satu tiap tiga detik).
Perkiraan lamanya disebutkan di panel saat tombol ditekan.

### Ketika gagal

Sebab kegagalan dicatat pada **percobaan pertama**, bukan setelah ketiganya
habis: `email_failed_at` dan `email_error` diisi seketika, dan kejadiannya
masuk log sebagai `warning`. Bila seluruh percobaan menyerah, `failed()`
mencatatnya sebagai `error`.

Ini penting karena `failed()` baru berjalan tujuh menit kemudian — dan tidak
pernah berjalan sama sekali bila antreannya tidak ada yang mengerjakan. Tanpa
pencatatan per percobaan, panel hanya berkata "belum terkirim" tanpa menyebut
sebabnya.

Kolom `email_error` ditampilkan di tabel peserta sebagai "Kendala email", dan
dapat ditindaklanjuti dengan aksi "Kirim Ulang Email". Alur empat langkah di
atas halaman kegiatan ikut menyebut berapa email yang gagal.

### Memastikan emailnya jalan

Tombol **Kirim Email Uji** di header tiap kegiatan mengirim satu email ke
alamat yang diisi, menempuh jalur yang sama persis — templat, kop penerbit,
sambungan SMTP yang sama — tanpa menyentuh peserta mana pun. Bila gagal,
jawaban server email ditampilkan apa adanya, misalnya
`535 5.7.8 Username and Password not accepted`. Lihat `CertificateMailProbe`.

### Antrean yang tidak dikerjakan

Laravel tidak mencatat pekerja antreannya di mana pun, sehingga panel dulu
menjanjikan "email diantrekan" tanpa tahu apakah ada yang akan mengerjakannya.
Pekerja kini meninggalkan denyut tiap kali menengok antrean (peristiwa
`Looping` → `QueueHealth::recordWorkerHeartbeat()`), dan panel membacanya
sebelum berjanji. Peringatannya muncul seketika, bukan setelah lima menit
pekerjaan menumpuk.

`APP_URL` wajib menunjuk domain produksi karena dipakai membentuk URL absolut
pada QR dan tombol email.
