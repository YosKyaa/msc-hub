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
