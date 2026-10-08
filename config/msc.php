<?php

return [
    /*
    | Operational recipients for every new public submission.
    | Keep this configurable so personnel changes do not require code changes.
    */
    'notification_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'MSC_NOTIFICATION_RECIPIENTS',
            'media@jgu.ac.id,chika@jgu.ac.id,yosua@jgu.ac.id,hadi@jgu.ac.id'
        ))
    ))),

    /*
    | Halaman tautan ringkas di /bio, untuk ditaruh di bio Instagram.
    |
    | Semuanya boleh dikosongkan: tautan yang kosong tidak ditampilkan, jadi
    | tidak ada akun yang ditebak-tebak lalu tercetak di halaman publik.
    */
    'bio' => [
        'tagline' => env('MSC_BIO_TAGLINE', 'Layanan media dan peminjaman fasilitas untuk sivitas Jakarta Global University.'),
        'website' => env('MSC_BIO_WEBSITE', 'https://jgu.ac.id'),

        /*
        | Admin yang bisa dihubungi langsung dari halaman /bio.
        |
        | Format "Nama:kontak", dipisah koma — mengikuti cara
        | MSC_NOTIFICATION_RECIPIENTS ditulis. Kontaknya boleh nomor WhatsApp
        | atau alamat email: yang memuat @ diperlakukan sebagai email, sisanya
        | sebagai nomor WhatsApp.
        |
        | Bawaannya memakai alamat email ketiga admin yang memang sudah
        | tercatat di proyek ini, supaya halamannya tetap berguna sebelum
        | nomor WhatsApp-nya diisi.
        */
        'admins' => env('MSC_BIO_ADMINS', 'Hadi:+62 822-7877-5003,Chika:+62 877-7141-2625,Yosua:+62 821-1218-7810'),
    ],

    /*
    | Identitas situs untuk mesin pencari dan pratinjau tautan.
    |
    | Dikumpulkan di sini supaya judul, deskripsi, dan gambar yang muncul di
    | hasil pencarian maupun saat tautannya dibagikan tidak ditulis ulang
    | berbeda-beda di tiap halaman.
    */
    'seo' => [
        'site_name' => env('MSC_SITE_NAME', 'MSC Hub — Jakarta Global University'),
        'description' => env('MSC_SITE_DESCRIPTION', 'Portal layanan Media & Strategic Communications Jakarta Global University: pengajuan konten, peminjaman ruangan dan alat multimedia, serta penerbitan dan verifikasi sertifikat kegiatan.'),
        'image' => env('MSC_SITE_IMAGE', 'img/jgucover.png'),
        'locale' => 'id_ID',
        'organization' => [
            'name' => 'Media & Strategic Communications, Jakarta Global University',
            'short_name' => 'MSC Hub',
            'street' => 'Jl. Boulevard Grand Depok City',
            'city' => 'Depok',
            'region' => 'Jawa Barat',
            'postal_code' => '16412',
            'country' => 'ID',
        ],
    ],

    /*
    | Alamat yang disebut ketika pengunjung diminta menghubungi tim MSC,
    | misalnya saat daftar penerima sebuah kegiatan belum dibuka.
    */
    'contact_email' => env('MSC_CONTACT_EMAIL', 'media@jgu.ac.id'),

    /*
    | Penerbitan sertifikat.
    |
    | Sampai sejumlah ini sertifikat diterbitkan langsung saat tombol ditekan,
    | supaya hasilnya seketika terlihat tanpa bergantung pada pekerja antrean.
    | Di atasnya barulah dikerjakan di latar belakang.
    */
    'certificates' => [
        'inline_issue_limit' => (int) env('MSC_INLINE_ISSUE_LIMIT', 100),

        /*
         * Berapa email sertifikat yang boleh dikirim tiap menit.
         *
         * Hampir semua penyedia SMTP menolak kiriman yang terlalu rapat —
         * milik kampus menjawab "550 Too many emails per second" lalu
         * menggugurkan sisanya. Mengirim seratus email sekaligus karena itu
         * justru membuat sebagian besarnya tidak sampai.
         *
         * Angkanya diatur menurut paket SMTP yang dipakai. 20 per menit
         * (satu tiap tiga detik) aman untuk paket gratis kebanyakan.
         */
        'emails_per_minute' => (int) env('MSC_CERTIFICATE_EMAILS_PER_MINUTE', 20),

        /*
         * Berapa email sertifikat yang boleh dikirim dalam 24 jam bergulir.
         * 0 berarti tanpa batas.
         *
         * Akun Google Workspace yang mengirim lewat smtp.gmail.com dibatasi
         * 2.000 penerima per 24 jam. Gmail yang menerima kiriman melewati
         * batas itu mengunci akunnya sampai sehari penuh, dan selama itu
         * seluruh email aplikasi tertahan, bukan hanya sertifikat. Bawaannya
         * 1.800 supaya tersisa ruang bagi pemberitahuan peminjaman dan email
         * lain dari akun yang sama, yang tidak ikut dihitung di sini.
         */
        'daily_email_limit' => (int) env('MSC_CERTIFICATE_DAILY_EMAIL_LIMIT', 1800),
    ],

    /*
    | Batas waktu login, dalam menit.
    |
    | idle     : sesi berakhir bila tidak ada permintaan selama sekian menit.
    | absolute : batas keras sejak login, ditegakkan seaktif apa pun
    |            penggunanya, supaya tidak ada sesi yang hidup selamanya.
    |
    | Nilai 0 mematikan batas yang bersangkutan.
    |
    | Catatan: SESSION_LIFETIME harus minimal sebesar idle terlama di sini,
    | kalau tidak cookie-nya mati lebih dulu dan batas ini tidak pernah
    | sempat berlaku.
    */
    'session' => [
        // Peminjam dan peserta acara. Jeda antara check-in dan check-out bisa
        // memakan satu hari acara penuh, jadi batas menganggurnya longgar.
        'requester' => [
            'idle_timeout' => (int) env('MSC_REQUESTER_IDLE_TIMEOUT', 480),
            'absolute_timeout' => (int) env('MSC_REQUESTER_ABSOLUTE_TIMEOUT', 720),
        ],

        // Panel menyetujui peminjaman dan menerbitkan sertifikat, jadi
        // perangkat yang ditinggal harus keluar jauh lebih cepat.
        'panel' => [
            'idle_timeout' => (int) env('MSC_PANEL_IDLE_TIMEOUT', 120),
            'absolute_timeout' => (int) env('MSC_PANEL_ABSOLUTE_TIMEOUT', 480),
        ],

        // Umur satu percobaan login Google, dihitung sejak pengguna diarahkan
        // ke Google sampai ia kembali. Halaman login yang dibiarkan terbuka
        // berjam-jam menghasilkan kode otorisasi basi.
        'login_attempt_timeout' => (int) env('MSC_LOGIN_ATTEMPT_TIMEOUT', 15),
    ],
];
