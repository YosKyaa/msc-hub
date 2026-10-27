#!/usr/bin/env bash
#
# Rilis MSC Hub di server. Jalankan dari folder aplikasi:
#
#     bash deploy.sh
#
# Langkah yang sama dengan docs/DEPLOYMENT.md, dalam urutan yang benar dan
# tanpa ada yang terlewat. Yang paling sering terlewat sebelumnya:
# composer install setelah dependensi berubah, dan queue:restart, yang membuat
# pekerja antrean terus menjalankan kode lama tanpa tanda apa pun.
#
# Berhenti di langkah pertama yang gagal, lalu menghidupkan aplikasi kembali.

set -euo pipefail

cd "$(dirname "$0")"

langkah() { printf '\n\033[1;33m==> %s\033[0m\n' "$1"; }

mode_rawat=0
pulihkan() {
    status=$?
    if [ "$mode_rawat" = 1 ]; then
        printf '\n\033[1;31mRilis gagal di langkah di atas (kode %s). Aplikasi dihidupkan kembali.\033[0m\n' "$status"
        php artisan up || true
    fi
    exit "$status"
}
trap pulihkan ERR

if [ "${1:-}" != "--sudah-diambil" ]; then
    langkah "Ambil kode terbaru dari GitHub"
    git fetch origin
    # Perubahan yang dibuat langsung di server dibuang: server pernah menyimpan
    # commit "Resolve merge conflict" berisi sisa penanda konflik, dan seluruh
    # halaman menjadi galat 500.
    git reset --hard origin/main

    # Skrip ini sendiri bisa ikut berubah oleh reset di atas. Bash membaca
    # skrip sedikit demi sedikit, jadi melanjutkan berarti menjalankan
    # campuran isi lama dan baru. Jalankan ulang dari versi yang baru.
    exec bash "$0" --sudah-diambil
fi

langkah "Tolak sisa konflik merge"
if git grep -nE '^(<{7}|>{7})( |$)' -- . ':!*.md'; then
    echo "Ada sisa konflik merge di kode. Rilis dibatalkan sebelum aplikasi dimatikan."
    exit 1
fi

langkah "Mode perawatan"
php artisan down --retry=15
mode_rawat=1

# Dependensi dipasang lebih dulu: perintah backup sendiri berasal dari paket
# yang mungkin baru ditambahkan di rilis ini.
langkah "Pasang dependensi"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

langkah "Cadangkan basis data sebelum migrasi"
php artisan backup:run --only-db --disable-notifications

langkah "Migrasi basis data"
# Izin baru ikut lewat migrasi. Jangan jalankan RoleSeeder di sini: ia
# menimpa izin setiap peran yang pernah diubah admin lewat panel.
php artisan migrate --force

langkah "Tautan storage"
[ -L public/storage ] || php artisan storage:link

langkah "Folder font PDF"
# dompdf menyimpan font unggahan sertifikat di storage/fonts. Folder itu harus
# bisa ditulisi server web, atau PDF diam-diam memakai font cadangan. Pemilik
# dan izinnya disamakan dengan storage/logs, yang pasti bisa ditulisinya.
mkdir -p storage/fonts
chown -R --reference=storage/logs storage/fonts 2>/dev/null || true
chmod --reference=storage/logs storage/fonts 2>/dev/null || true

langkah "Bangun ulang cache"
php artisan optimize:clear
php artisan optimize

langkah "Mulai ulang pekerja antrean"
php artisan queue:restart

langkah "Hidupkan kembali"
php artisan up
mode_rawat=0

langkah "Selesai"
git log --oneline -1
php artisan about --only=environment || true
