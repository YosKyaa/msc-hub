# Formulir Peminjaman Ruangan / Fasilitas (FM/JGU/L.89)

Lembar resmi kampus, dicetak dari data booking. Satu template melayani booking
ruangan maupun inventaris, sebagaimana formulir aslinya.

## Cetakan

- Kertas A4 potret, huruf **Times New Roman**.
- Marjin dipasang sebagai padding `body`, **bukan** `@page`, karena Dompdf
  mengabaikan marjin pada `@page` — tanpa ini seluruh teks menempel di pojok
  kertas dan pita kaki surat terlempar keluar halaman.
- Pita kaki surat JGU menempel rata tepi bawah pada setiap lembar.
- Kolom yang belum terisi di sistem dicetak sebagai garis titik-titik untuk
  dilengkapi dengan tangan.

## Daftar fasilitas yang panjang

Daftar fasilitas **tidak pernah dipotong**. Dokumen ini ditandatangani, jadi
alat yang tidak tercetak berarti alat yang luput saat serah terima.

| | Baris per lembar |
|---|---|
| Lembar pertama | 11 (mengikuti formulir asli) |
| Lembar lanjutan | 16 (kopnya ringkas sehingga muat lebih banyak) |

Aturan tata letaknya:

- Setiap lembar lanjutan membawa kop ringkas berisi kode formulir, judul
  bertanda "Lanjutan", kode booking, nama peminjam, dan tanggal — sehingga
  lembar yang terpisah tetap dapat dikenali.
- Setiap baris fasilitas bernomor urut menerus lintas lembar.
- Blok tanda tangan, catatan "berlaku 1 acara 1 hari", dan keterangan tanda
  bintang **hanya dicetak sekali**, di lembar terakhir.
- Kuota 16 baris dipilih agar lembar terakhir yang terisi penuh pun masih
  memuat blok tanda tangan — blok itu tidak pernah terdorong sendirian ke
  lembar kosong.
- Nomor "Halaman X dari Y" dicetak hanya bila lembarnya lebih dari satu, agar
  lembar yang hilang setelah dicetak ketahuan.

Batas yang diuji di `tests/Feature/BorrowingFormTest.php`:

| Alat | Baris | Lembar |
|---|---|---|
| 10 | 11 | 1 |
| 11 | 12 | 2 |
| 26 | 27 | 2 (lembar kedua terisi penuh) |
| 42 | 43 | 3 (lembar ketiga terisi penuh) |

## Isian yang belum ada di sistem

Formulir resmi mewajibkan **Penanggung Jawab (Dosen)** dan **No. HP Peminjam**.
Keduanya kolom nullable pada `room_bookings` dan `inventory_bookings`, diisi
lewat formulir publik maupun panel. Booking lama tanpa kedua isian itu tetap
sah dan tercetak dengan garis titik-titik.
