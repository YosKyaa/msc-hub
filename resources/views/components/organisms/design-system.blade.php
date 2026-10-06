{{--
    Sistem desain untuk seluruh halaman publik.

    Satu berkas, dipanggil tiap layout. Sebelumnya tiap layout memuat Tailwind
    sendiri tanpa tetapan warna apa pun, sehingga beranda memakai kertas hangat
    dan aksen kuning sementara halaman pengajuannya tetap putih dan biru. Dua
    wajah untuk satu portal, dan tidak ada tempat untuk memperbaikinya sekaligus.

    Warnanya dinamai menurut peran, bukan menurut rupanya:

      ink      tulisan dan bidang gelap
      paper    latar, tiga tingkat kehangatan
      sun      aksen kuning untuk bidang dan tombol
      sun-ink  aksen kuning untuk tulisan, cukup gelap agar terbaca di kertas

    `primary` sengaja ikut dipetakan ke kuning: banyak halaman lama memakainya
    sebagai warna utama, dan memetakannya di sini membuat seluruhnya ikut
    berpindah tanpa perlu menyentuh satu per satu.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>

<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    inter: ['Inter', 'sans-serif'],
                    playfair: ['Playfair Display', 'serif'],
                },
                colors: {
                    ink: '#15151A',
                    paper: '#FBFAF7',
                    'paper-2': '#F5F3ED',
                    'paper-3': '#EDEAE1',
                    sun: '#FFD84D',
                    'sun-deep': '#E8BE1F',
                    'sun-ink': '#8A6000',
                    'sun-soft': '#FFF7DC',
                    'sun-tint': '#FFEFB5',

                    // Skala penuh, supaya kelas seperti primary-50 dan
                    // primary-700 yang sudah dipakai halaman lama tetap jatuh
                    // ke warna yang benar.
                    primary: {
                        50: '#FFFBEB',
                        100: '#FFF7DC',
                        200: '#FFEFB5',
                        300: '#FFE488',
                        400: '#FFD84D',
                        500: '#F0C52B',
                        600: '#E8BE1F',
                        700: '#B58B08',
                        800: '#8A6000',
                        900: '#6B4A00',
                    },
                },
            },
        },
    }
</script>

<style>
    [x-cloak] { display: none !important; }
    body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
    .font-playfair { font-family: 'Playfair Display', serif; }

    /* Kaca.
       Di latar terang kaca hanya terbaca bila ada warna di belakangnya: putih
       di atas putih tidak terlihat buram, hanya terlihat putih. Panel kaca
       karena itu selalu berdiri di atas noda kuning yang ditaruh sengaja. */
    .kaca {
        background: rgba(255, 255, 255, .62);
        border: 1px solid rgba(21, 21, 26, .07);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        box-shadow: 0 1px 2px rgba(21, 21, 26, .04);
    }
    .kaca-terang {
        background: rgba(255, 255, 255, .78);
        border: 1px solid rgba(21, 21, 26, .09);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        box-shadow: 0 8px 32px -12px rgba(21, 21, 26, .12);
    }

    /* Isian formulir.
       Satu definisi, bukan deretan kelas yang harus ditempel ke tiap isian:
       satu formulir pengajuan memuat belasan isian, dan yang terlewat akan
       terlihat berbeda sendiri. */
    .isian {
        width: 100%;
        border-radius: .75rem;
        border: 1px solid rgba(21, 21, 26, .12);
        background: #fff;
        padding: .625rem .875rem;
        font-size: .875rem;
        line-height: 1.5;
        color: #15151A;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .isian::placeholder { color: rgba(21, 21, 26, .38); }
    .isian:hover { border-color: rgba(21, 21, 26, .22); }
    .isian:focus {
        outline: none;
        border-color: #E8BE1F;
        /* Cincin, bukan garis tebal: lebarnya tidak ikut menggeser tata
           letak saat isiannya disentuh. */
        box-shadow: 0 0 0 3px rgba(255, 216, 77, .4);
    }
    .isian:disabled,
    .isian[readonly] {
        background: #F5F3ED;
        color: rgba(21, 21, 26, .55);
        cursor: not-allowed;
    }
    textarea.isian { min-height: 6rem; resize: vertical; }

    /* Panah select digambar sendiri: bawaan peramban berbeda-beda bentuknya
       dan tidak satu pun mengikuti warna di sini. */
    select.isian {
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2315151A' stroke-opacity='.5' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right .75rem center;
        background-size: 1.15rem;
        padding-right: 2.5rem;
    }

    /* Kotak centang dan tombol pilihan. Bukan isian teks, jadi lebarnya
       tidak diikutkan. */
    .kotak-centang {
        width: 1rem;
        height: 1rem;
        border-radius: .25rem;
        border: 1px solid rgba(21, 21, 26, .25);
        accent-color: #E8BE1F;
    }
    .kotak-centang:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 216, 77, .45);
    }

    .label-isian {
        display: block;
        margin-bottom: .375rem;
        font-size: .875rem;
        font-weight: 500;
        color: rgba(21, 21, 26, .8);
    }

    @media (prefers-reduced-motion: reduce) {
        .isian { transition: none; }
    }

    .kartu-angkat { transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease; }
    .kartu-angkat:hover { transform: translateY(-3px); }

    @media (prefers-reduced-motion: reduce) {
        .kartu-angkat { transition: none; }
        .kartu-angkat:hover { transform: none; }
    }

    ::-webkit-scrollbar { width: 10px; }
    ::-webkit-scrollbar-track { background: #F5F3ED; }
    ::-webkit-scrollbar-thumb { background: #D6D1C4; border-radius: 9999px; }
    ::-webkit-scrollbar-thumb:hover { background: #C2BCAB; }
</style>
