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
