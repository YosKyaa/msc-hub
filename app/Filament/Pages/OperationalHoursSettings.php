<?php

namespace App\Filament\Pages;

use App\Support\AppSetting;
use App\Support\JamOperasional;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Jam buka layanan peminjaman, bisa diubah tanpa merilis ulang.
 *
 * Angkanya dulu ditulis langsung di dalam formulir peminjaman alat. Mengubah
 * jam buka karena itu menuntut menyunting berkas tampilan, dan hampir pasti
 * terlewat di salah satu dari dua formulir yang menampilkannya.
 */
class OperationalHoursSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Jam Operasional';

    protected static ?string $title = 'Jam Operasional Layanan';

    protected string $view = 'filament.pages.operational-hours-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        // Jam buka menyangkut peminjaman ruangan maupun alat, jadi siapa pun
        // yang mengelola salah satunya boleh mengubahnya.
        return auth()->user()?->canAny(['rooms.edit', 'inventory.edit']) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'jam_buka' => JamOperasional::buka(),
            'jam_tutup' => JamOperasional::tutup(),
            'catatan' => JamOperasional::catatan(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Jam buka')
                    ->description('Ditampilkan di formulir peminjaman ruangan dan alat.')
                    ->schema([
                        TextInput::make('jam_buka')
                            ->label('Mulai')
                            ->required()
                            ->placeholder('08:00')
                            ->rule('date_format:H:i')
                            ->validationMessages(['date_format' => 'Tulis dalam format 24 jam, misalnya 08:00.']),

                        TextInput::make('jam_tutup')
                            ->label('Selesai')
                            ->required()
                            ->placeholder('16:00')
                            ->rule('date_format:H:i')
                            ->validationMessages(['date_format' => 'Tulis dalam format 24 jam, misalnya 16:00.'])
                            ->rules([
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                    $buka = $this->data['jam_buka'] ?? null;

                                    if (blank($buka) || blank($value)) {
                                        return;
                                    }

                                    try {
                                        $mulai = Carbon::createFromFormat('H:i', $buka);
                                        $selesai = Carbon::createFromFormat('H:i', $value);
                                    } catch (\Throwable) {
                                        return;
                                    }

                                    if ($selesai->lessThanOrEqualTo($mulai)) {
                                        $fail('Jam selesai harus setelah jam mulai.');
                                    }
                                },
                            ]),
                    ])
                    ->columns(2),

                Section::make('Keterangan')
                    ->description('Kalimat yang menyertai jamnya di formulir peminjaman.')
                    ->schema([
                        Textarea::make('catatan')
                            ->label('Catatan untuk peminjam')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Kosongkan untuk memakai kalimat bawaan.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('simpan')
                ->label('Simpan')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        AppSetting::set(JamOperasional::KUNCI_BUKA, $data['jam_buka']);
        AppSetting::set(JamOperasional::KUNCI_TUTUP, $data['jam_tutup']);
        AppSetting::set(JamOperasional::KUNCI_CATATAN, $data['catatan'] ?: null);

        Notification::make()
            ->title('Jam operasional tersimpan')
            ->body('Formulir peminjaman ruangan dan alat langsung memakai jam ini.')
            ->success()
            ->send();
    }
}
