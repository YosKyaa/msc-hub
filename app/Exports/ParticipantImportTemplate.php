<?php

namespace App\Exports;

use App\Enums\ParticipantRole;
use App\Services\Certificates\Import\ParticipantImportParser;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Berkas contoh untuk mengimpor peserta.
 *
 * Nama pada sertifikat dicetak apa adanya, dan sertifikat yang telanjur
 * terkirim dengan nama keliru harus dikoreksi lalu dikirim ulang, jadi
 * kesalahan mengetiknya mahal. Template ini menyebutkan
 * kolom yang dibaca beserta artinya, memberi contoh isian, dan mengunci
 * kolom peran menjadi daftar pilihan supaya tidak bisa salah ketik.
 */
class ParticipantImportTemplate implements WithMultipleSheets
{
    use Exportable;

    public const FILENAME = 'Template-Import-Peserta-MSC-Hub.xlsx';

    /**
     * Contoh isian, sengaja memakai nama bergelar dan peran berbeda-beda
     * supaya bentuk yang diharapkan langsung terlihat.
     *
     * Alamatnya diawali "contoh." supaya mustahil milik orang sungguhan. Contoh
     * lama memakai alamat @jgu.ac.id yang masuk akal dimiliki dosen atau
     * mahasiswa, sehingga baris contoh yang lupa dihapus bisa membuat
     * sertifikat terkirim ke orang yang tidak pernah ikut kegiatannya.
     *
     * @return array<int, array<int, string>>
     */
    public static function contoh(): array
    {
        return [
            ['Budi Santoso', 'contoh.budi@student.jgu.ac.id', 'Peserta', '20210001', 'Teknik Informatika', ''],
            ['Dr. Djoko Susilo, M.Kom.', 'contoh.djoko@jgu.ac.id', 'Pembicara', '198001012005011001', 'Fakultas Teknik', ''],
            ['Siti Nurhaliza, S.Ds.', 'contoh.siti@jgu.ac.id', 'Panitia', '', 'MSC', ''],
        ];
    }

    /**
     * Contoh pada template versi sebelumnya, yang masih tersimpan di komputer
     * panitia yang sudah mengunduhnya.
     *
     * @return array<int, array<int, string>>
     */
    public static function contohLama(): array
    {
        return [
            ['Budi Santoso', 'budi@student.jgu.ac.id', 'Peserta', '20210001', 'Teknik Informatika', ''],
            ['Dr. Djoko Susilo, M.Kom.', 'djoko@jgu.ac.id', 'Pembicara', '198001012005011001', 'Fakultas Teknik', ''],
            ['Siti Nurhaliza, S.Ds.', 'siti@jgu.ac.id', 'Panitia', '', 'MSC', ''],
        ];
    }

    /**
     * Apakah baris ini contoh dari template yang lupa dihapus?
     *
     * Contoh baru dikenali dari alamatnya saja: tidak ada orang sungguhan yang
     * beralamat contoh.budi@... Contoh lama memakai alamat yang mungkin memang
     * milik seseorang, jadi baru dianggap contoh bila seluruh isinya sama
     * persis — nama, email, NIM/NIP, dan unit.
     */
    public static function isExample(string $nama, string $email, string $nimNip, string $unitProdi): bool
    {
        $email = mb_strtolower($email);

        foreach (self::contoh() as $contoh) {
            if ($email === $contoh[1]) {
                return true;
            }
        }

        foreach (self::contohLama() as $contoh) {
            if ([$nama, $email, $nimNip, $unitProdi] === [$contoh[0], $contoh[1], $contoh[3], $contoh[4]]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new ParticipantImportTemplateSheet,
            new ParticipantImportGuideSheet,
        ];
    }

    /**
     * Keterangan tiap kolom, dipakai lembar petunjuk maupun test.
     *
     * @return array<int, array<int, string>>
     */
    public static function penjelasanKolom(): array
    {
        return [
            [
                'nama_sertifikat',
                'WAJIB',
                'Nama yang dicetak di sertifikat, apa adanya.',
                'Tulis lengkap beserta gelar bila memang ingin tercetak. Periksa ejaannya: sertifikat yang telanjur terkirim dengan nama keliru harus dikoreksi lalu dikirim ulang ke pemiliknya.',
            ],
            [
                'email',
                'Disarankan',
                'Alamat email penerima. Alamat kampus maupun pribadi (Gmail dan lainnya) sama-sama diterima.',
                'Dipakai sebagai kunci peserta dan tujuan pengiriman sertifikat. Tidak boleh kembar di dalam satu berkas. Boleh dikosongkan bila orang ini belum punya email: sertifikatnya tetap terbit dan bisa dicari di halaman daftar penerima, tetapi tidak dikirim lewat email sampai alamatnya ditambahkan.',
            ],
            [
                'peran',
                'Opsional',
                'Peran orang ini pada kegiatan.',
                'Pilih dari daftar di kolomnya. Dikosongkan berarti Peserta. Nilai yang diterima: '.implode(', ', ParticipantRole::importableLabels()).'.',
            ],
            [
                'nim_nip',
                'Opsional',
                'NIM mahasiswa atau NIP dosen dan tendik.',
                'Hanya untuk pencatatan; tidak ikut tercetak kecuali templat sertifikatnya memang memuatnya.',
            ],
            [
                'unit_prodi',
                'Opsional',
                'Unit, fakultas, atau program studi.',
                'Hanya untuk pencatatan.',
            ],
            [
                'nomor_sertifikat',
                'Opsional',
                'Nomor yang sudah ditetapkan dari luar sistem.',
                'Kosongkan agar nomornya dibuat otomatis mengikuti pola penerbit. Nomor yang sudah terpakai akan ditolak.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function aturan(): array
    {
        return [
            'Baris pertama adalah nama kolom. Jangan diubah, dihapus, atau ditukar isinya.',
            'Urutan kolom bebas, dan kolom yang tidak dipakai boleh dihapus selama kolom nama_sertifikat dan email tetap ada, walau isi email-nya boleh kosong.',
            'Panitia dan peserta boleh di lembar terpisah dalam satu berkas, masing-masing dengan baris judul kolomnya sendiri. Semua lembar dibaca, dan peran yang dikosongkan mengikuti nama lembarnya: lembar "Panitia" berarti Panitia.',
            'Dari Google Sheets, unduh lewat File > Download > Microsoft Excel (.xlsx) supaya semua lembar ikut terbawa.',
            'Maksimal '.ParticipantImportParser::MAX_ROWS.' baris data dalam satu berkas.',
            'Hapus tiga baris contoh sebelum mengunggah. Bila terlupa, sistem menolaknya; baris yang kosong seluruhnya dilewati.',
            'Kolom nim_nip dan nomor_sertifikat sudah berformat Teks. NIP 18 digit yang diketik di kolom berformat angka dipotong Excel setelah digit ke-15.',
            'Berkas boleh disimpan sebagai .xlsx, .xls, .ods, atau .csv.',
            'Berkas diunggah lewat tombol Import Peserta pada halaman kegiatan sertifikat.',
            'Setelah diunggah, sistem menampilkan pratinjau. Baris yang bermasalah disebutkan alasannya dan tidak ikut diimpor.',
        ];
    }
}
