<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use UnitEnum;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Permission';

    protected static ?string $modelLabel = 'Permission';

    protected static ?string $pluralModelLabel = 'Permission';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('permissions.view') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Permission')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Permission')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Format: module.action (contoh: users.create, assets.delete)'),

                        TextInput::make('guard_name')
                            ->label('Guard')
                            ->default('web')
                            ->required()
                            ->helperText('Biarkan default "web" untuk aplikasi web'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Permission')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function (string $state): string {
                        $parts = explode('.', $state);
                        if (count($parts) === 2) {
                            return ucfirst($parts[0]) . ' - ' . ucfirst($parts[1]);
                        }
                        return $state;
                    }),

                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('roles_count')
                    ->label('Digunakan Role')
                    ->counts('roles')
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('module')
                    ->label('Modul')
                    ->options(function () {
                        return Permission::all()
                            ->map(fn ($p) => explode('.', $p->name)[0] ?? $p->name)
                            ->unique()
                            ->mapWithKeys(fn ($m) => [$m => ucfirst($m)])
                            ->toArray();
                    })
                    ->query(fn ($query, array $data) => 
                        $data['value'] ? $query->where('name', 'like', $data['value'] . '.%') : $query
                    ),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->before(function (Permission $record) {
                        if ($record->roles()->count() > 0) {
                            throw new \Exception('Permission ini masih digunakan oleh ' . $record->roles()->count() . ' role.');
                        }
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->roles()->count() > 0) {
                                    throw new \Exception('Permission "' . $record->name . '" masih digunakan oleh role.');
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
