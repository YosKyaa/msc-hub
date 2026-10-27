<?php

/*
|--------------------------------------------------------------------------
| Pesan validasi bawaan dalam Bahasa Indonesia
|--------------------------------------------------------------------------
|
| Tanpa berkas ini, server dengan APP_LOCALE=id dan APP_FALLBACK_LOCALE=id
| menampilkan kunci terjemahannya mentah-mentah, misalnya
| "validation.mimetypes" di bawah isian unggah font sertifikat. Isinya
| mengikuti daftar aturan Laravel; tests/Feature/IndonesianValidationTest.php
| memastikan tidak ada aturan yang terlewat setelah Laravel diperbarui.
|
*/

return [

    'accepted' => ':Attribute harus disetujui.',
    'accepted_if' => ':Attribute harus disetujui bila :other bernilai :value.',
    'active_url' => ':Attribute harus berupa URL yang valid.',
    'after' => ':Attribute harus berupa tanggal setelah :date.',
    'after_or_equal' => ':Attribute harus berupa tanggal yang sama dengan atau setelah :date.',
    'alpha' => ':Attribute hanya boleh berisi huruf.',
    'alpha_dash' => ':Attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => ':Attribute hanya boleh berisi huruf dan angka.',
    'any_of' => ':Attribute tidak valid.',
    'array' => ':Attribute harus berupa daftar.',
    'ascii' => ':Attribute hanya boleh berisi huruf, angka, dan simbol dasar.',
    'before' => ':Attribute harus berupa tanggal sebelum :date.',
    'before_or_equal' => ':Attribute harus berupa tanggal yang sama dengan atau sebelum :date.',
    'between' => [
        'array' => ':Attribute harus berisi :min sampai :max butir.',
        'file' => 'Ukuran :attribute harus :min sampai :max kilobita.',
        'numeric' => ':Attribute harus bernilai :min sampai :max.',
        'string' => ':Attribute harus terdiri atas :min sampai :max karakter.',
    ],
    'boolean' => ':Attribute harus bernilai ya atau tidak.',
    'can' => ':Attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'contains' => ':Attribute belum memuat nilai yang diwajibkan.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':Attribute harus berupa tanggal yang valid.',
    'date_equals' => ':Attribute harus berupa tanggal :date.',
    'date_format' => ':Attribute harus sesuai dengan format :format.',
    'decimal' => ':Attribute harus memiliki :decimal angka di belakang koma.',
    'declined' => ':Attribute harus ditolak.',
    'declined_if' => ':Attribute harus ditolak bila :other bernilai :value.',
    'different' => ':Attribute dan :other harus berbeda.',
    'digits' => ':Attribute harus terdiri atas :digits digit.',
    'digits_between' => ':Attribute harus terdiri atas :min sampai :max digit.',
    'dimensions' => 'Dimensi gambar :attribute tidak sesuai.',
    'distinct' => ':Attribute memiliki nilai yang berulang.',
    'doesnt_contain' => ':Attribute tidak boleh memuat salah satu dari: :values.',
    'doesnt_end_with' => ':Attribute tidak boleh diakhiri salah satu dari: :values.',
    'doesnt_start_with' => ':Attribute tidak boleh diawali salah satu dari: :values.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'encoding' => ':Attribute harus memakai pengodean :encoding.',
    'ends_with' => ':Attribute harus diakhiri salah satu dari: :values.',
    'enum' => ':Attribute yang dipilih tidak valid.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'extensions' => ':Attribute harus berekstensi salah satu dari: :values.',
    'file' => ':Attribute harus berupa berkas.',
    'filled' => ':Attribute wajib diisi.',
    'gt' => [
        'array' => ':Attribute harus berisi lebih dari :value butir.',
        'file' => 'Ukuran :attribute harus lebih dari :value kilobita.',
        'numeric' => ':Attribute harus lebih dari :value.',
        'string' => ':Attribute harus lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => ':Attribute harus berisi sedikitnya :value butir.',
        'file' => 'Ukuran :attribute harus sedikitnya :value kilobita.',
        'numeric' => ':Attribute harus sedikitnya :value.',
        'string' => ':Attribute harus terdiri atas sedikitnya :value karakter.',
    ],
    'hex_color' => ':Attribute harus berupa kode warna heksadesimal yang valid.',
    'image' => ':Attribute harus berupa gambar.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'in_array' => ':Attribute harus ada di dalam :other.',
    'in_array_keys' => ':Attribute harus memuat sedikitnya salah satu kunci berikut: :values.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'ip' => ':Attribute harus berupa alamat IP yang valid.',
    'ipv4' => ':Attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => ':Attribute harus berupa alamat IPv6 yang valid.',
    'json' => ':Attribute harus berupa teks JSON yang valid.',
    'list' => ':Attribute harus berupa daftar berurutan.',
    'lowercase' => ':Attribute harus ditulis dengan huruf kecil.',
    'lt' => [
        'array' => ':Attribute harus berisi kurang dari :value butir.',
        'file' => 'Ukuran :attribute harus kurang dari :value kilobita.',
        'numeric' => ':Attribute harus kurang dari :value.',
        'string' => ':Attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => ':Attribute tidak boleh berisi lebih dari :value butir.',
        'file' => 'Ukuran :attribute tidak boleh lebih dari :value kilobita.',
        'numeric' => ':Attribute tidak boleh lebih dari :value.',
        'string' => ':Attribute tidak boleh lebih dari :value karakter.',
    ],
    'mac_address' => ':Attribute harus berupa alamat MAC yang valid.',
    'max' => [
        'array' => ':Attribute tidak boleh berisi lebih dari :max butir.',
        'file' => 'Ukuran :attribute tidak boleh lebih dari :max kilobita.',
        'numeric' => ':Attribute tidak boleh lebih dari :max.',
        'string' => ':Attribute tidak boleh lebih dari :max karakter.',
    ],
    'max_digits' => ':Attribute tidak boleh lebih dari :max digit.',
    'mimes' => ':Attribute harus berupa berkas berjenis: :values.',
    'mimetypes' => ':Attribute harus berupa berkas berjenis: :values.',
    'min' => [
        'array' => ':Attribute harus berisi sedikitnya :min butir.',
        'file' => 'Ukuran :attribute harus sedikitnya :min kilobita.',
        'numeric' => ':Attribute harus sedikitnya :min.',
        'string' => ':Attribute harus terdiri atas sedikitnya :min karakter.',
    ],
    'min_digits' => ':Attribute harus terdiri atas sedikitnya :min digit.',
    'missing' => ':Attribute harus dikosongkan.',
    'missing_if' => ':Attribute harus dikosongkan bila :other bernilai :value.',
    'missing_unless' => ':Attribute harus dikosongkan kecuali :other bernilai :value.',
    'missing_with' => ':Attribute harus dikosongkan bila :values diisi.',
    'missing_with_all' => ':Attribute harus dikosongkan bila :values diisi.',
    'multiple_of' => ':Attribute harus kelipatan :value.',
    'not_in' => ':Attribute yang dipilih tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => ':Attribute harus berupa angka.',
    'password' => [
        'letters' => ':Attribute harus memuat sedikitnya satu huruf.',
        'mixed' => ':Attribute harus memuat sedikitnya satu huruf kapital dan satu huruf kecil.',
        'numbers' => ':Attribute harus memuat sedikitnya satu angka.',
        'symbols' => ':Attribute harus memuat sedikitnya satu simbol.',
        'uncompromised' => ':Attribute ini pernah muncul dalam kebocoran data. Gunakan :attribute yang lain.',
    ],
    'present' => ':Attribute wajib ada.',
    'present_if' => ':Attribute wajib ada bila :other bernilai :value.',
    'present_unless' => ':Attribute wajib ada kecuali :other bernilai :value.',
    'present_with' => ':Attribute wajib ada bila :values ada.',
    'present_with_all' => ':Attribute wajib ada bila :values ada.',
    'prohibited' => ':Attribute tidak boleh diisi.',
    'prohibited_if' => ':Attribute tidak boleh diisi bila :other bernilai :value.',
    'prohibited_if_accepted' => ':Attribute tidak boleh diisi bila :other disetujui.',
    'prohibited_if_declined' => ':Attribute tidak boleh diisi bila :other ditolak.',
    'prohibited_unless' => ':Attribute tidak boleh diisi kecuali :other termasuk :values.',
    'prohibits' => ':Attribute tidak membolehkan :other diisi.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':Attribute wajib diisi.',
    'required_array_keys' => ':Attribute harus memuat isian untuk: :values.',
    'required_if' => ':Attribute wajib diisi bila :other bernilai :value.',
    'required_if_accepted' => ':Attribute wajib diisi bila :other disetujui.',
    'required_if_declined' => ':Attribute wajib diisi bila :other ditolak.',
    'required_unless' => ':Attribute wajib diisi kecuali :other termasuk :values.',
    'required_with' => ':Attribute wajib diisi bila :values diisi.',
    'required_with_all' => ':Attribute wajib diisi bila :values diisi.',
    'required_without' => ':Attribute wajib diisi bila :values tidak diisi.',
    'required_without_all' => ':Attribute wajib diisi bila :values tidak ada yang diisi.',
    'same' => ':Attribute harus sama dengan :other.',
    'size' => [
        'array' => ':Attribute harus berisi :size butir.',
        'file' => 'Ukuran :attribute harus :size kilobita.',
        'numeric' => ':Attribute harus bernilai :size.',
        'string' => ':Attribute harus terdiri atas :size karakter.',
    ],
    'starts_with' => ':Attribute harus diawali salah satu dari: :values.',
    'string' => ':Attribute harus berupa teks.',
    'timezone' => ':Attribute harus berupa zona waktu yang valid.',
    'unique' => ':Attribute sudah digunakan.',
    'uploaded' => ':Attribute gagal diunggah.',
    'uppercase' => ':Attribute harus ditulis dengan huruf kapital.',
    'url' => ':Attribute harus berupa URL yang valid.',
    'ulid' => ':Attribute harus berupa ULID yang valid.',
    'uuid' => ':Attribute harus berupa UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Pesan khusus dan nama isian
    |--------------------------------------------------------------------------
    |
    | Pesan khusus sebaiknya ditulis di dekat aturannya, seperti di formulir
    | Filament, bukan di sini.
    |
    */

    'custom' => [],

    'attributes' => [],

];
