# Twibbon

Admin mengunggah bingkai di panel (**Konten → Twibbon**), lalu siapa pun bisa
memasangnya di atas fotonya sendiri di `/twibbon/{alamat}`, tanpa login.

## Untuk admin

1. Siapkan bingkai sebagai **PNG berlatar transparan**. Bagian tempat foto
   peserta harus benar-benar dikosongkan (transparan) saat mengekspor, bukan
   diisi putih.
2. Ukuran: sisi terpendek minimal **1080 px**, dengan rasio **1:1**
   (1080 × 1080, feed Instagram), **4:5** (1080 × 1350, potret), atau **9:16**
   (1080 × 1920, story).
3. Isi judul, deskripsi singkat, dan **caption** — teks yang disalin peserta
   dengan satu tombol saat membagikan, lengkap dengan tagar.
4. Nyalakan **Terbitkan**. Bila perlu, atur periode buka dan tutup.
5. Bagikan tautannya. Tombol **Buka halaman publik** di tabel menunjukkan
   alamatnya.

Bingkai ditolak sebelum disimpan bila bukan PNG, terlalu kecil, rasionya tidak
lazim, atau bagian transparannya kurang dari 10% — bingkai seperti itu menutup
foto peserta seluruhnya, dan biasanya baru ketahuan ketika ratusan peserta
sudah mencobanya. Kolom **Dipakai** menghitung berapa kali twibbon diunduh.

| Status | Artinya |
|---|---|
| Draf | belum diterbitkan; halamannya 404 |
| Terjadwal | diterbitkan, tetapi periode bukanya belum tiba; halamannya 404 |
| Dibuka | bisa dipakai siapa pun |
| Sudah ditutup | halamannya menjelaskan bahwa twibbon sudah ditutup, tanpa editor |

## Cara kerjanya

- **Foto peserta tidak pernah dikirim ke server.** Bingkai dan foto
  digabungkan di peramban lewat `<canvas>`. Privasinya terjaga, dan server
  tidak terbebani walau ratusan peserta memakainya bersamaan. Yang dikirim
  balik hanya tanda "dipakai" untuk penghitung
  (`POST /twibbon/{alamat}/dipakai`, dibatasi 120 per menit).
- Peserta bisa menggeser, memperbesar (gulir, cubit dua jari, atau penggeser),
  memutar 90°, dan menggerakkan foto dengan tombol panah. Hasilnya JPEG
  seukuran bingkai.
- **Bingkai dimuat dari alamat tanpa domain** (`Twibbon::frameRelativeUrl()`).
  Kanvas menolak mengekspor gambar dari asal lain, dan alamat absolut dibentuk
  dari `APP_URL` yang bisa berbeda dari alamat yang dibuka peserta (http lawan
  https, dengan atau tanpa www) — tombol unduh lalu gagal tanpa sebab yang
  terlihat.
- Peramban di dalam Instagram dan WhatsApp kerap memblokir unduhan. Hasilnya
  karena itu juga ditampilkan sebagai gambar biasa yang bisa ditekan lama untuk
  disimpan, dan di ponsel yang mendukung tersedia tombol **Bagikan** yang
  langsung membuka menu bagikan bawaan.
- Halaman twibbon **tidak dibatasi lajunya**: saat kegiatan kampus, ratusan
  peserta membukanya bersamaan dari Wi-Fi yang sama, dan pembatas per IP akan
  menolak sebagian besar dari mereka.
- Twibbon yang dibuka masuk peta situs, dan tautan **Twibbon kegiatan** muncul
  di `/bio` selama ada yang dibuka.

Berkasnya: `app/Models/Twibbon.php`, `app/Support/TwibbonFrame.php` (pemeriksa
bingkai), `app/Filament/Resources/TwibbonResource.php`,
`app/Http/Controllers/TwibbonController.php`, dan
`resources/views/twibbon/show.blade.php` (editor). Izinnya `twibbons.view`,
`twibbons.create`, `twibbons.edit`, `twibbons.delete`, diberikan lewat migrasi
kepada admin dan head_msc (semua) serta staff_msc (tanpa hapus).
