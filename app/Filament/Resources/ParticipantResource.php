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
use UnitEnum;

class ParticipantResource extends Resource
{
    use AuthorizesCertificateModule;

    protected static ?string $model = Participant::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|UnitEnum|null $navigationGroup = 'Sertifikat';
    protected static ?string $navigationLabel = 'Master Participant';
    protected static ?string $recordTitleAttribute = 'name';

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
        ])->actions([Actions\EditAction::make(), Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index'=>Pages\ListParticipants::route('/'),'create'=>Pages\CreateParticipant::route('/create'),'edit'=>Pages\EditParticipant::route('/{record}/edit')]; }
}
