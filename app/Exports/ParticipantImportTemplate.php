<?php

namespace App\Exports;

use App\Enums\ParticipantRole;
use App\Services\Certificates\Import\ParticipantImportParser;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Berkas contoh untuk mengimpor peserta.
 *
 * Nama pada sertifikat dicetak apa adanya dan tidak bisa ditarik kembali
 * setelah terbit, jadi kesalahan mengetiknya mahal. Template ini menyebutkan
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
     * @return array<int, array<int, string>>
     */
    public static function contoh(): array
    {
        return [
            ['Budi Santoso', 'budi@student.jgu.ac.id', 'Peserta', '20210001', 'Teknik Informatika', ''],
            ['Dr. Djoko Susilo, M.Kom.', 'djoko@jgu.ac.id', 'Pembicara', '198001012005011001', 'Fakultas Teknik', ''],
            ['Siti Nurhaliza, S.Ds.', 'siti@jgu.ac.id', 'Panitia', '', 'MSC', ''],
        ];
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
                'Tulis lengkap beserta gelar bila memang ingin tercetak. Periksa ejaannya — nama yang salah hanya bisa diperbaiki dengan mencabut lalu menerbitkan ulang sertifikatnya.',
            ],
            [
                'email',
                'WAJIB',
                'Alamat email penerima.',
                'Dipakai sebagai kunci peserta dan tujuan pengiriman sertifikat. Tidak boleh kembar di dalam satu berkas.',
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
            'Urutan kolom bebas, dan kolom yang tidak dipakai boleh dihapus selama nama_sertifikat dan email tetap ada.',
            'Maksimal '.ParticipantImportParser::MAX_ROWS.' baris data dalam satu berkas.',
            'Hapus tiga baris contoh sebelum mengunggah; baris yang kosong seluruhnya akan dilewati.',
            'Berkas diunggah lewat tombol Import Peserta pada halaman kegiatan sertifikat.',
            'Setelah diunggah, sistem menampilkan pratinjau. Baris yang bermasalah disebutkan alasannya dan tidak ikut diimpor.',
        ];
    }
}
