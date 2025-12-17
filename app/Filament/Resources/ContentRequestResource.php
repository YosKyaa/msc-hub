<?php

namespace App\Filament\Resources;

use App\Enums\CommentAuthorType;
use App\Enums\ContentType;
use App\Enums\RequestStatus;
use App\Enums\RequesterType;
use App\Filament\Resources\ContentRequestResource\Pages;
use App\Models\ContentRequest;
use App\Models\ContentRequestComment;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use UnitEnum;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContentRequestResource extends Resource
{
    protected static ?string $model = ContentRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static string|UnitEnum|null $navigationGroup = 'Content Request';

    protected static ?string $navigationLabel = 'Inbox';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'request_code';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('content_requests.view') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereIn('status', [
            RequestStatus::INCOMING,
            RequestStatus::WAITING_HEAD_APPROVAL,
        ])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Informasi Pemohon')
                ->schema([
                    TextInput::make('request_code')
                        ->label('Kode Request')
                        ->disabled(),
                    TextInput::make('requester_name')
                        ->label('Nama Pemohon')
                        ->required(),
                    TextInput::make('requester_email')
                        ->label('Email')
                        ->email()
                        ->disabled(),
                    Select::make('requester_type')
                        ->label('Tipe Pemohon')
                        ->options(RequesterType::class),
                    TextInput::make('unit')
                        ->label('Unit/Fakultas'),
                    TextInput::make('phone')
                        ->label('Telepon'),
                ])
                ->columns(2),

            Section::make('Detail Permintaan')
                ->schema([
                    Select::make('content_type')
                        ->label('Jenis Konten')
                        ->options(ContentType::class)
                        ->required(),
                    TextInput::make('platform_target')
                        ->label('Platform Target'),
                    Textarea::make('purpose')
                        ->label('Tujuan')
                        ->rows(2),
                    TextInput::make('audience')
                        ->label('Target Audience'),
                    DatePicker::make('event_date')
                        ->label('Tanggal Event'),
                    TextInput::make('location')
                        ->label('Lokasi'),
                    DatePicker::make('deadline')
                        ->label('Deadline')
                        ->required(),
                    TextInput::make('materials_link')
                        ->label('Link Materi')
                        ->url(),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Status & Assignment')
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options(RequestStatus::class)
                        ->disabled(),
                    Select::make('assigned_to_user_id')
                        ->label('PIC')
                        ->relationship('assignedTo', 'name')
                        ->searchable()
                        ->preload(),
                    Select::make('linked_project_id')
                        ->label('Project Terkait')
                        ->relationship('linkedProject', 'title')
                        ->searchable()
                        ->preload(),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('requester_name')
                    ->label('Pemohon')
                    ->searchable()
                    ->description(fn ($record) => $record->requester_email),
                TextColumn::make('content_type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('platform_target')
                    ->label('Platform')
                    ->placeholder('-'),
                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn ($record) => $record->deadline?->isPast() ? 'danger' : null),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('assignedTo.name')
                    ->label('PIC')
                    ->placeholder('Belum ada'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(RequestStatus::class)
                    ->multiple(),
                SelectFilter::make('content_type')
                    ->label('Jenis Konten')
                    ->options(ContentType::class),
                SelectFilter::make('assigned_to_user_id')
                    ->label('PIC')
                    ->relationship('assignedTo', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('deadline_passed')
                    ->label('Deadline Terlewat')
                    ->query(fn (Builder $query) => $query->where('deadline', '<', now())),
                Filter::make('created_this_month')
                    ->label('Bulan Ini')
                    ->query(fn (Builder $query) => $query->whereMonth('created_at', now()->month)),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    
                    // Assign PIC
                    Actions\Action::make('assign')
                        ->label('Assign PIC')
                        ->icon('heroicon-o-user-plus')
                        ->color('primary')
                        ->visible(fn ($record) => $record->canBeAssigned())
                        ->form([
                            Select::make('assigned_to_user_id')
                                ->label('Pilih PIC')
                                ->options(User::role(['admin', 'staff_msc', 'head_msc'])->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'assigned_to_user_id' => $data['assigned_to_user_id'],
                                'status' => RequestStatus::ASSIGNED,
                            ]);
                            Notification::make()->title('PIC berhasil di-assign')->success()->send();
                        }),

                    // Start Progress
                    Actions\Action::make('start_progress')
                        ->label('Mulai Kerjakan')
                        ->icon('heroicon-o-play')
                        ->color('warning')
                        ->visible(fn ($record) => $record->canStartProgress())
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update(['status' => RequestStatus::IN_PROGRESS]);
                            Notification::make()->title('Status diubah ke In Progress')->success()->send();
                        }),

                    // Request Revision
                    Actions\Action::make('need_revision')
                        ->label('Minta Revisi')
                        ->icon('heroicon-o-arrow-path')
                        ->color('danger')
                        ->visible(fn ($record) => $record->canRequestRevision())
                        ->form([
                            Textarea::make('message')
                                ->label('Catatan Revisi')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update(['status' => RequestStatus::NEED_REVISION]);
                            
                            ContentRequestComment::create([
                                'content_request_id' => $record->id,
                                'author_type' => auth()->user()->hasRole('head_msc') ? CommentAuthorType::HEAD : CommentAuthorType::STAFF,
                                'user_id' => auth()->id(),
                                'message' => '[REVISI] ' . $data['message'],
                            ]);
                            
                            Notification::make()->title('Request revisi dikirim')->success()->send();
                        }),

                    // Staff Approve
                    Actions\Action::make('staff_approve')
                        ->label('Approve (Staff)')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn ($record) => $record->canStaffApprove() && auth()->user()->hasAnyRole(['admin', 'staff_msc', 'head_msc']))
                        ->requiresConfirmation()
                        ->modalHeading('Approve Request (Staff)')
                        ->modalDescription('Request akan diteruskan ke Head MSC untuk approval final.')
                        ->action(function ($record) {
                            $record->update([
                                'staff_approved_at' => now(),
                                'staff_approved_by' => auth()->id(),
                                'status' => RequestStatus::WAITING_HEAD_APPROVAL,
                            ]);
                            Notification::make()->title('Berhasil di-approve, menunggu Head MSC')->success()->send();
                        }),

                    // Head Approve
                    Actions\Action::make('head_approve')
                        ->label('Approve (Head)')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn ($record) => $record->canHeadApprove() && auth()->user()->hasAnyRole(['admin', 'head_msc']))
                        ->requiresConfirmation()
                        ->modalHeading('Approve Request (Head MSC)')
                        ->modalDescription('Request akan di-approve dan siap dipublikasi.')
                        ->action(function ($record) {
                            $record->update([
                                'head_approved_at' => now(),
                                'head_approved_by' => auth()->id(),
                                'status' => RequestStatus::APPROVED,
                            ]);
                            Notification::make()->title('Request approved!')->success()->send();
                        }),

                    // Reject
                    Actions\Action::make('reject')
                        ->label('Tolak')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->canReject())
                        ->form([
                            Textarea::make('reject_reason')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'reject_reason' => $data['reject_reason'],
                                'rejected_at' => now(),
                                'rejected_by' => auth()->id(),
                                'status' => RequestStatus::REJECTED,
                            ]);
                            Notification::make()->title('Request ditolak')->warning()->send();
                        }),

                    // Publish
                    Actions\Action::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-globe-alt')
                        ->color('success')
                        ->visible(fn ($record) => $record->canPublish())
                        ->form([
                            TextInput::make('published_link')
                                ->label('Link Hasil Publikasi')
                                ->url()
                                ->required()
                                ->placeholder('https://instagram.com/p/...'),
                            TextInput::make('source_link')
                                ->label('Link Source (opsional)')
                                ->url()
                                ->placeholder('https://drive.google.com/...'),
                            Select::make('linked_project_id')
                                ->label('Link ke Project (opsional)')
                                ->options(Project::where('status', 'active')->pluck('title', 'id'))
                                ->searchable()
                                ->helperText('Pilih project untuk auto-create asset di Asset Vault'),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'published_link' => $data['published_link'],
                                'source_link' => $data['source_link'] ?? null,
                                'linked_project_id' => $data['linked_project_id'] ?? null,
                                'published_at' => now(),
                                'status' => RequestStatus::PUBLISHED,
                            ]);
                            
                            // Create asset in vault
                            $record->createAssetInVault();
                            
                            Notification::make()->title('Konten dipublikasi & asset dibuat di Vault')->success()->send();
                        }),

                    // Archive
                    Actions\Action::make('archive')
                        ->label('Arsipkan')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->visible(fn ($record) => $record->canArchive())
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update([
                                'archived_at' => now(),
                                'status' => RequestStatus::ARCHIVED,
                            ]);
                            Notification::make()->title('Request diarsipkan')->success()->send();
                        }),

                    // Add Comment
                    Actions\Action::make('add_comment')
                        ->label('Tambah Komentar')
                        ->icon('heroicon-o-chat-bubble-left')
                        ->color('gray')
                        ->form([
                            Textarea::make('message')
                                ->label('Komentar')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function ($record, array $data) {
                            ContentRequestComment::create([
                                'content_request_id' => $record->id,
                                'author_type' => auth()->user()->hasRole('head_msc') ? CommentAuthorType::HEAD : CommentAuthorType::STAFF,
                                'user_id' => auth()->id(),
                                'message' => $data['message'],
                            ]);
                            Notification::make()->title('Komentar ditambahkan')->success()->send();
                        }),

                    Actions\DeleteAction::make()
                        ->visible(fn () => auth()->user()->hasRole('admin')),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentRequests::route('/'),
            'create' => Pages\CreateContentRequest::route('/create'),
            'view' => Pages\ViewContentRequest::route('/{record}'),
            'edit' => Pages\EditContentRequest::route('/{record}/edit'),
        ];
    }
}
