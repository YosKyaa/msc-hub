<?php

namespace App\Http\Controllers;

/**
 * Halaman tautan ringkas, untuk ditaruh di bio Instagram.
 *
 * Hampir semua yang membukanya datang dari ponsel, sekali, dan tidak tahu
 * MSC melayani apa saja — bukan orang yang sudah hafal menu portalnya.
 * Karena itu halaman ini sengaja tanpa sidebar dan tanpa bilah atas: satu
 * kolom berisi tombol-tombol besar, dan setiap tombol menyebutkan apa yang
 * terjadi kalau ditekan.
 *
 * Daftarnya tinggal di sini, bukan di dalam blade, supaya urutan dan
 * penamaannya bisa diuji dan diubah di satu tempat.
 */
class BioController extends Controller
{
    public function __invoke()
    {
        return view('bio', [
            'kelompok' => $this->kelompok(),
            'admin' => $this->admin(),
        ]);
    }

    /**
     * Tautan utamanya, dikelompokkan menurut keadaan pengunjungnya.
     *
     * @return list<array{judul: string, catatan: ?string, tautan: list<array<string, mixed>>}>
     */
    private function kelompok(): array
    {
        return [
            [
                'judul' => 'Mau mengajukan apa?',
                'catatan' => null,
                'tautan' => [
                    [
                        'label' => 'Ajukan Konten',
                        'keterangan' => 'Minta dibuatkan foto, video, atau desain.',
                        'url' => route('request.content'),
                        'icon' => 'M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586Z',
                        'utama' => true,
                    ],
                    [
                        'label' => 'Booking Ruangan',
                        'keterangan' => 'Pinjam studio atau ruang rapat MSC.',
                        'url' => route('booking.room'),
                        'icon' => 'M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4',
                        'utama' => false,
                    ],
                    [
                        'label' => 'Pinjam Alat',
                        'keterangan' => 'Kamera, lighting, audio, dan lainnya.',
                        'url' => route('booking.inventory'),
                        'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                        'utama' => false,
                    ],
                ],
            ],
            [
                'judul' => 'Sudah pernah mengajukan?',
                // Keduanya meminta masuk dengan Google. Disebutkan di muka
                // supaya halaman login tidak terasa seperti penghalang
                // mendadak.
                'catatan' => 'Masuk dulu dengan akun JGU Anda.',
                'tautan' => [
                    [
                        'label' => 'Cek Status Konten',
                        'keterangan' => 'Sudah sampai mana permintaan Anda.',
                        'url' => route('request.status'),
                        'icon' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4',
                        'utama' => false,
                    ],
                    [
                        'label' => 'Riwayat Booking',
                        'keterangan' => 'Ruangan dan alat yang pernah Anda pinjam.',
                        'url' => route('my.bookings'),
                        'icon' => 'M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                        'utama' => false,
                    ],
                ],
            ],
            [
                'judul' => 'Lainnya',
                'catatan' => null,
                'tautan' => [
                    [
                        'label' => 'Pengumuman',
                        'keterangan' => 'Jadwal, info layanan, dan kabar terbaru.',
                        'url' => route('announcements.index'),
                        'icon' => 'M11 5.882V19.24a1.76 1.76 0 0 1-3.417.592l-2.147-6.15M18 13a3 3 0 1 0 0-6M5.436 13.683A4.001 4.001 0 0 1 7 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 0 1-1.564-.317Z',
                        'utama' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Admin yang bisa dihubungi langsung.
     *
     * Orang yang bingung mengisi formulir lebih cepat tertolong dengan
     * bertanya kepada orang daripada membaca satu paragraf lagi. Karena itu
     * ketiganya disebut dengan nama, bukan disembunyikan di balik satu tombol
     * "kontak" yang tidak jelas menuju siapa.
     *
     * @return list<array{nama: string, via: string, url: string, inisial: string}>
     */
    private function admin(): array
    {
        $hasil = [];

        foreach (explode(',', (string) config('msc.bio.admins')) as $baris) {
            [$nama, $kontak] = array_pad(explode(':', trim($baris), 2), 2, '');

            $nama = trim($nama);
            $kontak = trim((string) $kontak);

            if ($nama === '' || $kontak === '') {
                continue;
            }

            // Alamat email dikenali dari tanda @; sisanya diperlakukan sebagai
            // nomor WhatsApp, dan tanda bacanya dibuang karena orang menuliskan
            // nomor dengan spasi dan strip.
            $lewatEmail = str_contains($kontak, '@');

            $hasil[] = [
                'nama' => $nama,
                'via' => $lewatEmail ? 'Email' : 'WhatsApp',
                'url' => $lewatEmail
                    ? 'mailto:'.$kontak
                    : 'https://wa.me/'.preg_replace('/\D+/', '', $kontak),
                'inisial' => mb_strtoupper(mb_substr($nama, 0, 1)),
            ];
        }

        return $hasil;
    }
}
