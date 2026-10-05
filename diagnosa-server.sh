#!/usr/bin/env bash
#
# Cari sebab 500 di server. Hanya membaca, tidak mengubah apa pun.
#
# Jalankan dari akar aplikasi di server:
#   bash diagnosa-server.sh
#
# Tempelkan seluruh keluarannya kembali ke percakapan.

cd "$(dirname "$0")" || exit 1

garis() { printf '\n== %s %s\n' "$1" "$(printf '%.0s-' $(seq 1 $((60 - ${#1}))))"; }

garis "Galat terakhir di log"
LOG="storage/logs/laravel.log"
if [ -f "$LOG" ]; then
    # Baris ERROR terakhir beserta beberapa baris jejaknya. Inilah jawaban
    # sebenarnya; sisanya di bawah hanya dugaan yang sering terbukti.
    baris=$(grep -n "\.ERROR" "$LOG" | tail -1 | cut -d: -f1)

    if [ -n "$baris" ]; then
        echo "--- galat terakhir, beserta delapan bingkai pertama jejaknya ---"
        # Hanya bingkai awal yang menyebut kode aplikasi; sisanya kerangka
        # Laravel yang sama untuk galat apa pun.
        sed -n "${baris},$((baris + 9))p" "$LOG" | cut -c1-260
        echo
        echo "--- tiga galat terakhir, ringkas ---"
        grep -n "\.ERROR" "$LOG" | tail -3 | cut -c1-180
    else
        echo "Tidak ada baris ERROR di log."
        echo "Kalau halamannya tetap 500, galatnya terjadi sebelum Laravel sempat mencatat:"
        echo "  periksa log PHP-FPM dan Nginx, lalu bagian 'Izin tulis' di bawah."
    fi
else
    echo "TIDAK ADA $LOG — ini sendiri sudah mencurigakan:"
    echo "  storage/ mungkin tidak bisa ditulis, sehingga Laravel gagal sebelum sempat mencatat."
fi

garis "PHP"
php -v | head -1
echo "Ekstensi yang dibutuhkan:"
for e in pdo_mysql mbstring gd zip intl fileinfo openssl curl; do
    php -m | grep -qix "$e" && echo "  ada        $e" || echo "  HILANG     $e"
done

garis "Izin tulis"
for d in storage storage/logs storage/framework storage/framework/views storage/framework/cache storage/framework/sessions bootstrap/cache; do
    if [ ! -d "$d" ]; then
        echo "  TIDAK ADA  $d"
    elif [ -w "$d" ]; then
        echo "  bisa tulis $d"
    else
        echo "  TAK BISA   $d   <-- sebab 500 paling sering"
    fi
done
echo
echo "Pemilik direktori (bandingkan dengan pengguna PHP-FPM, biasanya www-data):"
ls -ld storage bootstrap/cache 2>/dev/null | awk '{print "  "$3":"$4"  "$9}'
echo "PHP berjalan sebagai: $(php -r 'echo get_current_user();')"

garis "Berkas inti"
for f in .env vendor/autoload.php composer.lock; do
    [ -e "$f" ] && echo "  ada        $f" || echo "  HILANG     $f"
done
[ -L public/storage ] && echo "  ada        public/storage (tautan simbolik)" || echo "  HILANG     public/storage  -> jalankan: php artisan storage:link"

garis "Setelan .env yang menentukan"
if [ -f .env ]; then
    for k in APP_KEY APP_ENV APP_DEBUG APP_URL DB_CONNECTION DB_DATABASE QUEUE_CONNECTION SESSION_DRIVER CACHE_STORE; do
        nilai=$(grep -E "^${k}=" .env | head -1 | cut -d= -f2-)
        case "$k" in
            APP_KEY)
                [ -n "$nilai" ] && echo "  terisi     APP_KEY" || echo "  KOSONG     APP_KEY  -> jalankan: php artisan key:generate"
                ;;
            *) printf '  %-18s %s\n' "$k" "${nilai:-(kosong)}" ;;
        esac
    done
    echo
    echo "Baris ganda di .env (yang terakhir menang, sering tidak disadari):"
    grep -oE '^[A-Z_]+=' .env | sort | uniq -d | sed 's/^/  /' || true
fi

garis "Cache"
for f in bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/packages.php bootstrap/cache/services.php; do
    [ -f "$f" ] && echo "  ada        $f  ($(date -r "$f" '+%Y-%m-%d %H:%M'))" || echo "  tidak ada  $f"
done
if [ -f bootstrap/cache/config.php ] && [ -f .env ] && [ .env -nt bootstrap/cache/config.php ]; then
    echo
    echo "  PERHATIAN: .env lebih baru daripada config.php yang di-cache."
    echo "  Nilai .env yang baru TIDAK terbaca. Jalankan: php artisan config:cache"
fi

garis "Basis data dan migrasi"
php artisan migrate:status 2>&1 | tail -8

garis "Boot aplikasi"
php artisan about --only=environment 2>&1 | head -12

echo
echo "=============================================================="
echo "Tempelkan SELURUH keluaran di atas kembali ke percakapan."
echo "Bagian paling menentukan ada di 'Galat terakhir di log'."
echo "=============================================================="
