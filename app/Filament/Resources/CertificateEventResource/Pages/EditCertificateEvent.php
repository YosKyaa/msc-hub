<?php

namespace App\Filament\Resources\CertificateEventResource\Pages;

use App\Filament\Resources\CertificateEventResource;
use App\Filament\Resources\CertificateEventResource\Widgets\CertificateProgressWidget;
use App\Services\Certificates\CertificateMailProbe;
use App\Support\CertificatePermission;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCertificateEvent extends EditRecord
{
    protected static string $resource = CertificateEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->testEmailAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * Membuktikan email benar-benar jalan sebelum peserta yang kena.
     *
     * Setelan SMTP yang salah baru ketahuan saat tombol kirim ditekan untuk
     * seluruh peserta, dan saat itu kegagalannya sudah menyebar. Percobaan ini
     * menempuh jalur yang sama persis — templat, kop penerbit, sambungan SMTP
     * yang sama — tetapi hanya ke satu alamat, dan galat dari server email
     * ditunjukkan apa adanya.
     */
    private function testEmailAction(): Action
    {
        return Action::make('testEmail')
            ->label('Kirim Email Uji')
            ->icon('heroicon-o-beaker')
            ->color('gray')
            ->visible(fn () => CertificatePermission::allowsIssuing())
            ->modalHeading('Kirim Email Uji')
            ->modalDescription('Satu email contoh dikirim ke alamat yang Anda isi, memakai templat dan penerbit kegiatan ini. Peserta tidak menerima apa pun.')
            ->modalSubmitActionLabel('Kirim Sekarang')
            ->schema([
                TextInput::make('email')
                    ->label('Kirim ke alamat')
                    ->email()
                    ->required()
                    ->default(fn () => auth()->user()?->email)
                    ->helperText('Biasanya alamat Anda sendiri, supaya hasilnya bisa langsung dilihat.'),
            ])
            ->action(function (array $data): void {
                $galat = app(CertificateMailProbe::class)->send($this->getRecord(), $data['email']);

                if ($galat === null) {
                    Notification::make()
                        ->title('Email uji terkirim')
                        ->body('Periksa kotak masuk '.$data['email'].'. Bila tidak ada, lihat juga folder spam.')
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Email uji gagal terkirim')
                    ->body('Server email menjawab: '.$galat)
                    ->danger()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Alur empat langkahnya di paling atas, sebelum formulir. Inilah yang
     * dicari orang begitu membuka kegiatan: sudah sampai mana, dan apa
     * berikutnya.
     */
    protected function getHeaderWidgets(): array
    {
        return [CertificateProgressWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
