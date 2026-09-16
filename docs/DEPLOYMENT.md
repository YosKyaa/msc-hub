# Menaikkan MSC Hub ke Server

Panduan ini untuk rilis pertama modul sertifikat sekaligus seluruh perbaikan
yang menyertainya. Ganti `USER`, `NAMA_DB`, `/path/ke/msc-hub`, dan
`msc.jgu.ac.id` dengan milik Anda.

> **Sebelum mulai, ketahui ini dulu**
>
> Yang naik bukan satu-dua perubahan kecil: **52 commit** dan **12 migrasi**.
> Seluruh modul sertifikat baru pertama kali dijalankan di server, dan salah
> satu migrasinya mengubah data — bukan hanya struktur tabel.

---

## 0. Yang harus tersedia di server

| Kebutuhan | Versi |
|---|---|
| PHP | 8.2 atau lebih baru |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `gd`, `zip`, `intl`, `fileinfo`, `openssl`, `curl` |
| MySQL / MariaDB | 8.0 / 10.6 ke atas |
| Composer | 2.x |
| Supervisor | untuk menjaga pekerja antrean tetap hidup |

`gd` dipakai membuat kode QR absensi, `zip` untuk impor peserta dari Excel.

Periksa sekaligus:

```bash
php -v
php -m | grep -E 'pdo_mysql|mbstring|gd|zip|intl|fileinfo'
composer --version
```

**Tidak perlu Node.js maupun `npm run build`.** Halaman publik memakai Tailwind
lewat CDN dan panel memakai CSS bawaan Filament, jadi tidak ada aset yang perlu
dikompilasi.

### Batas ukuran unggahan

Bawaan PHP hanya 2 MB. Desain latar sertifikat dan lampiran pengajuan biasanya
lebih besar dari itu, dan berkas yang melewati batas ditolak PHP **sebelum**
Laravel sempat melihatnya — yang muncul di layar hanyalah `failed to upload`,
tanpa menyebut sebab maupun angkanya.

Periksa dulu yang berlaku sekarang:

```bash
php -i | grep -E 'upload_max_filesize|post_max_size|memory_limit'
```

Bila masih 2 MB, naikkan di `php.ini` milik PHP-FPM
(`/etc/php/8.2/fpm/php.ini`, sesuaikan versinya):

```ini
upload_max_filesize = 16M
post_max_size = 20M
memory_limit = 256M
```

`post_max_size` harus lebih besar daripada `upload_max_filesize` karena satu
kiriman formulir memuat berkas **beserta** isian lainnya. Lalu muat ulang:

```bash
sudo systemctl reload php8.2-fpm
```

Bila memakai Nginx, batasnya ada dua lapis — tambahkan di blok `server`:

```nginx
client_max_body_size 20M;
```

Formulir di panel menyebut angkanya sendiri mengikuti pengaturan ini, jadi
setelah diubah tidak ada yang perlu disunting di kode.

> Desain latar ditanam ke dalam **setiap** PDF sertifikat. Latar 8 MB membuat
> tiap sertifikat ikut ± 10 MB, dan seribu peserta berarti 10 GB terkirim lewat
> email. Kompres latar ke bawah 1 MB; pada kanvas 1123 × 794 px hasilnya tidak
> akan terlihat berbeda.


---

## 1. Cadangkan basis data — wajib

```bash
mysqldump -u USER -p NAMA_DB > ~/backup-msc-$(date +%F-%H%M).sql
ls -lh ~/backup-msc-*.sql
```

Migrasi `enforce_unique_participant_email` menggabungkan peserta beremail
kembar lalu **menghapus yang kalah** dan menulis ulang `certificates.participant_id`.
Tidak dapat dibatalkan. Pastikan berkas cadangannya benar-benar ada dan
ukurannya masuk akal sebelum melangkah.

---

## 2. Ambil kodenya

Di mesin pengembangan:

```bash
git checkout main
git merge --no-ff feature/certificate-automation
git push origin main
```

Di server:

```bash
cd /path/ke/msc-hub

php artisan down --retry=60

git pull origin main
composer install --no-dev --optimize-autoloader
```

`php artisan down` memasang halaman perawatan supaya tidak ada yang mengirim
pengajuan di tengah migrasi.

---

## 3. Sesuaikan `.env` server

Berkas `.env` tidak ikut git, jadi kunci baru harus ditambahkan sendiri.
Bandingkan dengan `.env.example` untuk melihat mana yang belum ada.

### Yang wajib diperiksa

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://msc.jgu.ac.id

SESSION_LIFETIME=480
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=database
LOG_LEVEL=error
```

- **`APP_DEBUG=false`** — bila true, jejak galat beserta isi konfigurasi,
  termasuk kredensial basis data, tampil kepada pengunjung.
- **`APP_URL` harus lengkap beserta `https://` dan tanpa garis miring di
  akhir.** Seluruh tautan verifikasi sertifikat, alamat kanonik SEO, peta
  situs, dan redirect Google dibentuk dari sini. Tanpa skema, setiap alamat
  menjadi `http://localhost/msc.jgu.ac.id`.
- **`SESSION_LIFETIME=480`** — harus minimal sebesar
  `MSC_REQUESTER_IDLE_TIMEOUT`. Bila lebih pendek, cookie mati lebih dulu dan
  peserta acara sehari penuh terlempar keluar di antara check-in dan check-out.
- **`SESSION_SECURE_COOKIE=true`** — wajib di HTTPS.

### Kunci baru yang belum ada di server

```dotenv
APP_TIMEZONE=Asia/Jakarta

# Batas waktu login (menit). idle = tanpa permintaan; absolute = sejak masuk.
MSC_REQUESTER_IDLE_TIMEOUT=480
MSC_REQUESTER_ABSOLUTE_TIMEOUT=720
MSC_PANEL_IDLE_TIMEOUT=120
MSC_PANEL_ABSOLUTE_TIMEOUT=480
MSC_LOGIN_ATTEMPT_TIMEOUT=15

# Sampai sejumlah ini sertifikat diterbitkan langsung saat tombol ditekan.
MSC_INLINE_ISSUE_LIMIT=100

MSC_NOTIFICATION_RECIPIENTS="media@jgu.ac.id,chika@jgu.ac.id,yosua@jgu.ac.id,hadi@jgu.ac.id"
MSC_CONTACT_EMAIL=media@jgu.ac.id

MSC_SITE_NAME="MSC Hub — Jakarta Global University"
MSC_SITE_DESCRIPTION="Portal layanan Media & Strategic Communications Jakarta Global University: pengajuan konten, peminjaman ruangan dan alat multimedia, serta penerbitan dan verifikasi sertifikat kegiatan."
MSC_SITE_IMAGE=img/jgucover.png
```

Semuanya punya nilai bawaan di `config/msc.php`, jadi aplikasinya tetap jalan
tanpa kunci ini — tetapi menuliskannya membuat pengaturannya terlihat.

### SMTP

Pastikan `MAIL_HOST` menunjuk SMTP kampus, bukan Mailtrap. Mailtrap hanya
menampung email tanpa pernah mengirimkannya ke penerima sungguhan.

Setelah menyunting, **jangan lupa**:

```bash
php artisan config:clear
```

---

## 4. Migrasi dan storage

```bash
php artisan migrate --force
php artisan storage:link
```

`--force` diperlukan karena `APP_ENV=production` membuat Laravel meminta
konfirmasi interaktif.

`storage:link` membuat symlink `public/storage` → `storage/app/public`. Tanpa
itu, PDF sertifikat tetap terbit tetapi **tanpa desain latarnya** — berkasnya
tidak ditemukan, dan kejadian itu dicatat di log tanpa menggagalkan unduhan.

Periksa hasilnya:

```bash
php artisan migrate:status | tail -15
ls -l public/storage
```

---

## 5. Cache dan hidupkan kembali

```bash
php artisan optimize:clear
php artisan optimize
php artisan up
```

`optimize` menyimpan config, rute, view, dan event ke cache. Setelah ini,
**setiap perubahan `.env` menuntut `php artisan config:cache` diulang** —
kalau tidak, nilainya tidak akan terbaca.

---

## 6. Pekerja antrean — jangan dilewati

Tanpa ini **email tidak akan pernah terkirim**: sertifikat tetap terbit dan
halaman verifikasinya hidup, tetapi pengirimannya menumpuk diam-diam.

Uji dulu di depan mata:

```bash
php artisan queue:work --stop-when-empty --tries=3
```

Lalu pasang Supervisor agar selalu hidup:

```ini
# /etc/supervisor/conf.d/msc-hub-worker.conf
[program:msc-hub-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/ke/msc-hub/artisan queue:work --tries=3 --timeout=120 --sleep=3 --max-time=3600
directory=/path/ke/msc-hub
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/ke/msc-hub/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start msc-hub-worker:*
sudo supervisorctl status
```

> Panel akan memperingatkan sendiri bila antreannya tertahan lebih dari lima
> menit, jadi Anda tidak perlu memantaunya terus-menerus.

Bila Supervisor belum tersedia, alternatif sementara: setel
`QUEUE_CONNECTION=sync`. Email dikirim langsung dalam permintaan — menekan
tombol terasa lebih lambat, tetapi tidak ada yang menumpuk.

---

## 7. Google OAuth

Di [Google Cloud Console](https://console.cloud.google.com/apis/credentials),
pada OAuth Client yang dipakai, tambahkan **Authorized redirect URI**:

```
https://msc.jgu.ac.id/auth/google/callback
```

Harus **persis sama** dengan `GOOGLE_REDIRECT_URI` di `.env`. Bila tidak
cocok, login gagal dan sebabnya tercatat di log sebagai
`redirect_uri_mismatch`.

Satu proyek OAuth dipakai bersama oleh peminjam dan panel; keduanya dibedakan
lewat penanda di sesi, bukan alamat callback yang berbeda.

---

## 8. Periksa setelah naik

```bash
php artisan about --only=environment
php artisan queue:failed
curl -I https://msc.jgu.ac.id/up
curl -s https://msc.jgu.ac.id/robots.txt | tail -3
```

Lalu telusuri dengan tangan:

- [ ] Buka `/` — beranda tampil
- [ ] Buka `/masuk` — dua pilihan muncul
- [ ] Masuk sebagai admin lewat Google → mendarat di `/panel`
- [ ] Dasbor panel menampilkan **Perlu Tindakan Anda**
- [ ] Ajukan satu booking percobaan dari sisi peminjam → berhasil, bukan galat
- [ ] Lonceng panel berisi pemberitahuan booking itu **dalam hitungan detik**
- [ ] Buka satu sertifikat → halaman verifikasi tampil, PDF-nya terunduh
- [ ] Daftarkan `https://msc.jgu.ac.id/sitemap.xml` di Google Search Console

---

## Yang berubah perilakunya setelah rilis ini

**Zona waktu UTC → Asia/Jakarta.** Booking dan permintaan konten yang dibuat
**sebelum** rilis ini tersimpan sebagai UTC, jadi akan tampil bergeser tujuh
jam. Datanya tidak rusak, hanya tampilannya. Rinciannya di
`docs/timezone-cutoff.md`. Bila barisnya sedikit, lebih mudah dikoreksi manual
daripada dibiarkan membingungkan.

**Sertifikat tidak lagi terkirim otomatis.** Menerbitkan dan mengirim kini dua
langkah terpisah: tekan *Terbitkan Digital*, periksa hasilnya, baru *Kirim
Email*. Beri tahu staf yang mengelola sertifikat.

**Daftar penerima bawaannya tertutup.** Kegiatan yang ingin daftarnya dibuka ke
publik harus dinyalakan satu per satu dari panel.

---

## Bila ada yang gagal

```bash
tail -50 storage/logs/laravel.log
```

| Gejala | Penyebab yang paling sering |
|---|---|
| Login Google gagal | `redirect_uri_mismatch` — alamat di Console tidak sama dengan `.env` |
| Semua halaman 500 | `php artisan config:clear` lalu `config:cache` belum diulang setelah menyunting `.env` |
| Email tidak sampai | Pekerja antrean tidak berjalan; periksa `supervisorctl status` |
| Sertifikat tanpa latar | `php artisan storage:link` belum dijalankan |
| Alamat aneh di email | `APP_URL` kurang `https://` atau berakhiran garis miring |

### Mengembalikan keadaan

```bash
php artisan down
git reset --hard <commit-sebelumnya>
mysql -u USER -p NAMA_DB < ~/backup-msc-*.sql
composer install --no-dev --optimize-autoloader
php artisan optimize:clear && php artisan optimize
php artisan up
```

Migrasi tidak dibatalkan dengan `migrate:rollback` melainkan dipulihkan dari
cadangan, karena penggabungan peserta beremail kembar tidak punya jalan balik.
