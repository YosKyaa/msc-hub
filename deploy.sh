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

langkah "Ambil kode terbaru dari GitHub"
git fetch origin
# Perubahan yang dibuat langsung di server dibuang: server pernah menyimpan
# commit "Resolve merge conflict" berisi sisa penanda konflik, dan seluruh
# halaman menjadi galat 500.
git reset --hard origin/main

langkah "Tolak sisa konflik merge"
if git grep -nE '^(<{7}|>{7})( |$)' -- . ':!*.md'; then
    echo "Ada sisa konflik merge di kode. Rilis dibatalkan sebelum aplikasi dimatikan."
    exit 1
fi

langkah "Mode perawatan"
php artisan down --retry=15
mode_rawat=1

langkah "Cadangkan basis data sebelum migrasi"
php artisan backup:run --only-db --disable-notifications

langkah "Pasang dependensi"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

langkah "Migrasi basis data"
# Izin baru ikut lewat migrasi. Jangan jalankan RoleSeeder di sini: ia
# menimpa izin setiap peran yang pernah diubah admin lewat panel.
php artisan migrate --force

langkah "Tautan storage"
[ -L public/storage ] || php artisan storage:link

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
