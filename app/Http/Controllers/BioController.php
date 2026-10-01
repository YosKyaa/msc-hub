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
            'sosial' => $this->sosial(),
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
     * Tautan media sosial dan kontak.
     *
     * Semuanya berasal dari konfigurasi dan disembunyikan bila dikosongkan,
     * supaya tidak ada akun yang ditebak-tebak lalu tercetak di halaman publik.
     *
     * @return list<array{label: string, url: string, icon: string}>
     */
    private function sosial(): array
    {
        $bio = config('msc.bio');

        $kandidat = [
            [
                'label' => 'Instagram',
                'url' => filled($bio['instagram'] ?? null)
                    ? 'https://instagram.com/'.ltrim((string) $bio['instagram'], '@')
                    : null,
                'icon' => 'M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm5 5.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9ZM17.8 6.2h.01',
            ],
            [
                'label' => 'WhatsApp',
                'url' => filled($bio['whatsapp'] ?? null)
                    ? 'https://wa.me/'.preg_replace('/\D+/', '', (string) $bio['whatsapp'])
                    : null,
                'icon' => 'M3 21l1.65-4.5A8.5 8.5 0 1 1 7.5 19.4L3 21Zm6.2-10.1c.3 1 1.9 2.6 2.9 2.9.4.1.9 0 1.2-.3l.5-.6 1.8.9c0 .9-.7 1.6-1.6 1.6-3 0-6.4-3.4-6.4-6.4 0-.9.7-1.6 1.6-1.6l.9 1.8-.6.5c-.3.3-.4.8-.3 1.2Z',
            ],
            [
                'label' => 'Situs JGU',
                'url' => $bio['website'] ?? null,
                'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0c2.5-2.3 3.8-5.3 3.8-9S14.5 5.3 12 3C9.5 5.3 8.2 8.3 8.2 12s1.3 6.7 3.8 9ZM3.3 9h17.4M3.3 15h17.4',
            ],
            [
                'label' => 'Email',
                'url' => filled(config('msc.contact_email'))
                    ? 'mailto:'.config('msc.contact_email')
                    : null,
                'icon' => 'M3 8l7.9 5.3a2 2 0 0 0 2.2 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z',
            ],
        ];

        return array_values(array_filter($kandidat, fn (array $t) => filled($t['url'])));
    }
}
