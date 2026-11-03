<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesCertificateModule;
use App\Filament\Resources\ParticipantResource\Pages;
use App\Models\Participant;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Throwable;
use UnitEnum;

class ParticipantResource extends Resource
{
    use AuthorizesCertificateModule;

    protected static ?string $model = Participant::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|UnitEnum|null $navigationGroup = 'Sertifikat';
    protected static ?string $navigationLabel = 'Master Participant';
    protected static ?string $recordTitleAttribute = 'name';

    /** Akibat menghapus satu peserta yang tidak terlihat dari tabelnya. */
    public const DELETION_NOTE = 'Keikutsertaannya di setiap kegiatan ikut terhapus, dan tindakan ini tidak dapat dibatalkan.';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas')->schema([
                Select::make('type')->label('Tipe')->options(['student'=>'Mahasiswa','lecturer'=>'Dosen','staff'=>'Staf','guest'=>'Guest/Eksternal'])->required()->default('guest'),
                TextInput::make('institutional_id')->label('NIM/NIP/NIDN'),
                TextInput::make('name')->label('Nama lengkap')->required(),
                TextInput::make('email')->email(),
                TextInput::make('phone')->label('Telepon'),
                TextInput::make('institution')->label('Institusi')->default('Jakarta Global University'),
                TextInput::make('faculty')->label('Fakultas/Unit'),
                TextInput::make('study_program')->label('Program Studi'),
                KeyValue::make('metadata')->label('Data tambahan')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            TextColumn::make('type')->label('Tipe')->badge(),
            TextColumn::make('institutional_id')->label('NIM/NIP')->searchable()->placeholder('—'),
            TextColumn::make('email')->searchable(),
            TextColumn::make('institution')->label('Institusi')->toggleable(),
            TextColumn::make('participations_count')->label('Kegiatan')->counts('participations'),
            // Pemegang sertifikat tidak bisa dihapus; kolom ini menjelaskan
            // kenapa tombol Hapus di barisnya tidak ada.
            TextColumn::make('certificates_count')->label('Sertifikat')->counts('certificates'),
        ])->actions([
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->modalDescription(self::DELETION_NOTE)
                ->visible(fn (Participant $record) => $record->isDeletable()),
        ])->bulkActions([
            Actions\DeleteBulkAction::make()
                ->modalDescription('Keikutsertaan mereka di setiap kegiatan ikut terhapus, dan tindakan ini tidak dapat dibatalkan. '
                    .'Peserta yang sudah memegang sertifikat dilewati.')
                ->using(fn (Actions\DeleteBulkAction $action, EloquentCollection $records) => self::deleteSelected($action, $records)),
        ]);
    }

    /**
     * Hapus peserta yang dipilih, kecuali yang memegang sertifikat. Yang
     * dilewati disebut namanya di pemberitahuan, beserta jalan keluarnya.
     */
    private static function deleteSelected(Actions\DeleteBulkAction $action, EloquentCollection $records): void
    {
        $records->loadCount('certificates');
        $dilewati = [];

        foreach ($records as $participant) {
            if (! $participant->isDeletable()) {
                $dilewati[] = $participant->name;
                $action->reportBulkProcessingFailure('memegang-sertifikat', self::skippedMessage($dilewati));

                continue;
            }

            try {
                $participant->delete() || $action->reportBulkProcessingFailure();
            } catch (Throwable $exception) {
                $action->reportBulkProcessingFailure();
                report($exception);
            }
        }
    }

    /**
     * @param  list<string>  $nama
     */
    private static function skippedMessage(array $nama): string
    {
        $disebut = array_slice($nama, 0, 5);
        $sisa = count($nama) - count($disebut);

        return count($nama).' peserta dilewati karena sudah memegang sertifikat: '.implode(', ', $disebut)
            .($sisa > 0 ? ", dan {$sisa} lainnya" : '').'. Cabut sertifikatnya bila dokumennya memang harus dibatalkan.';
    }

    public static function getPages(): array { return ['index'=>Pages\ListParticipants::route('/'),'create'=>Pages\CreateParticipant::route('/create'),'edit'=>Pages\EditParticipant::route('/{record}/edit')]; }
}
