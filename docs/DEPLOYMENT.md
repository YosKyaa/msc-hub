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
php artisan queue:restart
php artisan up
```

`optimize` menyimpan config, rute, view, dan event ke cache. Setelah ini,
**setiap perubahan `.env` menuntut `php artisan config:cache` diulang**, kalau
tidak nilainya tidak akan terbaca.

> `queue:restart` jangan dilewati. Pekerja antrean memuat kode aplikasi sekali
> saat dijalankan lalu menyimpannya di memori: pekerja yang sudah hidup sebelum
> rilis akan terus menjalankan kode lama sampai dimulai ulang. Perbaikan yang
> baru saja Anda naikkan tidak berlaku baginya, dan tidak ada tanda apa pun
> bahwa itu sedang terjadi.
>
> Perintah ini tidak mematikan pekerjanya, hanya memberi tanda agar ia
> berhenti setelah menyelesaikan pekerjaan yang sedang dipegangnya. Supervisor
> menyalakannya kembali dengan kode baru.

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

> Panel memperingatkan sendiri bila tidak ada pekerja yang berjalan — seketika,
> bukan setelah menunggu. Pekerja meninggalkan denyut tiap kali menengok
> antrean, dan panel membacanya sebelum menjanjikan email akan terkirim.

Bila Supervisor belum tersedia, pakai cron. Penjadwal aplikasi sudah memuat
tugas yang mengosongkan antrean tiap menit, jadi yang perlu dipasang di server
hanya satu baris:

```cron
* * * * * cd /var/www/msc-hub && php artisan schedule:run >> /dev/null 2>&1
```

> Cron saja tidak cukup bila penjadwalnya kosong. Sebelum ini tidak ada satu
> pun tugas terjadwal, sehingga `schedule:run` berjalan tiap menit tanpa
> menemukan apa pun untuk dikerjakan: antreannya tidak pernah tersentuh, tidak
> ada galat, dan tidak ada email. Periksa dengan `php artisan schedule:list`,
> dan pastikan `queue:work` ada di sana.

Alternatif terakhir: setel `QUEUE_CONNECTION=sync`. Email dikirim langsung
dalam permintaan, menekan tombol terasa lebih lambat, tetapi tidak ada yang
menumpuk.

### Pastikan emailnya benar-benar terkirim, bukan dibuang

Laravel menganggap pengiriman berhasil selama pengantarnya tidak melempar
galat, dan pengantar `log` maupun `array` tidak pernah melempar apa pun:
keduanya menerima surat lalu membuangnya. Sistem mencatat "terkirim", panel
menampilkan "Email terkirim", dan penerimanya tidak menerima apa-apa.

```bash
php artisan tinker --execute="print_r(App\\Support\\MailHealth::summary());"
```

`mengirim` harus bernilai 1. Bila 0, setel di `.env` server. Produksi memakai
akun Google Workspace `no-reply@jgu.ac.id`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@jgu.ac.id
MAIL_PASSWORD=sandi-aplikasi-16-huruf
MAIL_FROM_ADDRESS=no-reply@jgu.ac.id
MAIL_FROM_NAME="MSC HUB JGU"
```

- `MAIL_PASSWORD` adalah **Sandi Aplikasi** (App Password), bukan kata sandi
  akun. Google menolak kata sandi biasa lewat SMTP. Sandi Aplikasi baru bisa
  dibuat setelah Verifikasi 2 Langkah aktif di akun tersebut.
- `MAIL_FROM_ADDRESS` harus sama dengan akun yang login, atau alias yang sudah
  diverifikasi di setelan "Kirim email sebagai". Bila berbeda, Gmail diam-diam
  menimpa alamat pengirimnya dengan akun yang login.
- Port 587 tidak memerlukan `MAIL_SCHEME`: sambungannya dinaikkan ke TLS
  dengan sendirinya. `MAIL_ENCRYPTION` sudah tidak dibaca sejak Laravel 11.

Lalu `php artisan config:cache` dan `php artisan queue:restart`. Tanda
setelannya benar: di Gmail penerima, rincian pesan menyebut **ditandatangani
oleh: jgu.ac.id**. Panel ikut memperingatkan sendiri: alur empat
langkah di tiap kegiatan menyebutkannya, dan tombol Kirim Email Uji menolak
berpura-pura berhasil.

### Laju kirim email

Hampir semua penyedia SMTP menolak kiriman yang terlalu rapat. Milik kampus
menjawab `550 5.7.0 Too many emails per second` lalu menggugurkan sisanya —
sehingga mengirim seratus sertifikat sekaligus justru membuat sebagian besarnya
tidak sampai.

Kirimannya karena itu dijarakkan. Sesuaikan dengan paket SMTP yang dipakai:

```dotenv
# Email sertifikat per menit. 20 (satu tiap tiga detik) aman untuk paket
# gratis kebanyakan; naikkan bila paketnya memang mengizinkan.
MSC_CERTIFICATE_EMAILS_PER_MINUTE=20
```

Seratus peserta pada 20 per menit berarti lima menit. Panel menyebutkan
perkiraan itu saat tombol kirim ditekan.

### Kuota harian akun pengirim

Akun Google Workspace yang mengirim lewat `smtp.gmail.com` dibatasi **2.000
penerima per 24 jam bergulir** (bukan direset tengah malam). Gmail yang
menerima kiriman melewati batas itu mengunci akunnya sampai sehari penuh, dan
selama itu **seluruh** email aplikasi tertahan, termasuk pemberitahuan
peminjaman.

```dotenv
# Email sertifikat per 24 jam bergulir; 0 berarti tanpa batas.
MSC_CERTIFICATE_DAILY_EMAIL_LIMIT=1800
```

Yang dihitung: sertifikat yang terkirim dalam 24 jam terakhir, ditambah yang
sudah diantrekan tetapi belum berangkat. Bila sisa kuota tidak cukup, hanya
sebagian yang dikirim; sisanya tetap berstatus menunggu kirim dan panel
menyebutkan jam kuotanya terbuka lagi. Tekan **Kirim Email** lagi setelah jam
itu.

Bawaannya 1.800, bukan 2.000, karena email lain dari akun yang sama
(pemberitahuan peminjaman, email uji) tidak ikut dihitung. Bila kampus
mengaktifkan SMTP relay Workspace (`smtp-relay.gmail.com`, 10.000 per hari)
atau pindah ke layanan email transaksional, naikkan angkanya atau setel 0.

### Buktikan emailnya jalan sebelum peserta yang kena

Jangan menunggu pengiriman massal untuk tahu SMTP-nya salah setel. Buka satu
kegiatan di panel, tekan **Kirim Email Uji**, isi alamat Anda sendiri.

Email itu menempuh jalur yang sama persis — templat, kop penerbit, sambungan
SMTP yang sama — tetapi hanya ke satu alamat, dan peserta tidak menerima apa
pun. Bila gagal, jawaban server email ditampilkan apa adanya, misalnya
`535 5.7.8 Username and Password not accepted`.

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
