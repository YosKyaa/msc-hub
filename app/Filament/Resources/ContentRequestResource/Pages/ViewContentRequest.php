<?php

namespace App\Filament\Resources\ContentRequestResource\Pages;

use App\Enums\CommentAuthorType;
use App\Enums\RequestStatus;
use App\Filament\Resources\ContentRequestResource;
use App\Models\ContentRequestComment;
use App\Models\Project;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewContentRequest extends ViewRecord
{
    protected static string $resource = ContentRequestResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Pemohon')
                ->schema([
                    TextEntry::make('request_code')
                        ->label('Kode Request')
                        ->badge()
                        ->color('primary'),
                    TextEntry::make('requester_name')
                        ->label('Nama'),
                    TextEntry::make('requester_email')
                        ->label('Email')
                        ->copyable(),
                    TextEntry::make('requester_type')
                        ->label('Tipe')
                        ->badge(),
                    TextEntry::make('unit')
                        ->label('Unit')
                        ->placeholder('-'),
                    TextEntry::make('phone')
                        ->label('Telepon')
                        ->placeholder('-'),
                ])
                ->columns(3),

            Section::make('Detail Permintaan')
                ->schema([
                    TextEntry::make('content_type')
                        ->label('Jenis Konten')
                        ->badge(),
                    TextEntry::make('platform_target')
                        ->label('Platform')
                        ->placeholder('-'),
                    TextEntry::make('event_date')
                        ->label('Tanggal Event')
                        ->date('d M Y')
                        ->placeholder('-'),
                    TextEntry::make('location')
                        ->label('Lokasi')
                        ->placeholder('-'),
                    TextEntry::make('deadline')
                        ->label('Deadline')
                        ->date('d M Y')
                        ->color(fn ($record) => $record->deadline?->isPast() ? 'danger' : null),
                    TextEntry::make('audience')
                        ->label('Target Audience')
                        ->placeholder('-'),
                    TextEntry::make('purpose')
                        ->label('Tujuan')
                        ->placeholder('-')
                        ->columnSpanFull(),
                    TextEntry::make('materials_link')
                        ->label('Link Materi')
                        ->url(fn ($record) => $record->materials_link)
                        ->openUrlInNewTab()
                        ->placeholder('-')
                        ->columnSpanFull(),
                    TextEntry::make('notes')
                        ->label('Catatan')
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])
                ->columns(3),

            Section::make('Status & Assignment')
                ->schema([
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge(),
                    TextEntry::make('assignedTo.name')
                        ->label('PIC')
                        ->placeholder('Belum ada'),
                    TextEntry::make('linkedProject.title')
                        ->label('Project')
                        ->placeholder('-'),
                    TextEntry::make('staff_approved_at')
                        ->label('Staff Approve')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('head_approved_at')
                        ->label('Head Approve')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('published_at')
                        ->label('Published')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                ])
                ->columns(3),

            Section::make('Hasil Publikasi')
                ->schema([
                    TextEntry::make('published_link')
                        ->label('Link Publikasi')
                        ->url(fn ($record) => $record->published_link)
                        ->openUrlInNewTab()
                        ->placeholder('-'),
                    TextEntry::make('source_link')
                        ->label('Link Source')
                        ->url(fn ($record) => $record->source_link)
                        ->openUrlInNewTab()
                        ->placeholder('-'),
                    TextEntry::make('createdAsset.title')
                        ->label('Asset Vault')
                        ->placeholder('-'),
                ])
                ->columns(3)
                ->visible(fn ($record) => $record->published_at !== null),

            Section::make('Rejection')
                ->schema([
                    TextEntry::make('reject_reason')
                        ->label('Alasan')
                        ->columnSpanFull(),
                    TextEntry::make('rejected_at')
                        ->label('Tanggal')
                        ->dateTime('d M Y H:i'),
                    TextEntry::make('rejectedByUser.name')
                        ->label('Ditolak oleh'),
                ])
                ->columns(2)
                ->visible(fn ($record) => $record->rejected_at !== null),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            // Assign PIC
            Actions\Action::make('assign')
                ->label('Assign PIC')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->visible(fn () => $this->record->canBeAssigned())
                ->form([
                    Select::make('assigned_to_user_id')
                        ->label('Pilih PIC')
                        ->options(User::role(['admin', 'staff_msc', 'head_msc'])->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
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
                ->visible(fn () => $this->record->canStartProgress())
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => RequestStatus::IN_PROGRESS]);
                    Notification::make()->title('Status diubah ke In Progress')->success()->send();
                }),

            // Staff Approve
            Actions\Action::make('staff_approve')
                ->label('Approve (Staff)')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->canStaffApprove() && auth()->user()->hasAnyRole(['admin', 'staff_msc', 'head_msc']))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'staff_approved_at' => now(),
                        'staff_approved_by' => auth()->id(),
                        'status' => RequestStatus::WAITING_HEAD_APPROVAL,
                    ]);
                    Notification::make()->title('Berhasil di-approve')->success()->send();
                }),

            // Head Approve
            Actions\Action::make('head_approve')
                ->label('Approve (Head)')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->canHeadApprove() && auth()->user()->hasAnyRole(['admin', 'head_msc']))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'head_approved_at' => now(),
                        'head_approved_by' => auth()->id(),
                        'status' => RequestStatus::APPROVED,
                    ]);
                    Notification::make()->title('Request approved!')->success()->send();
                }),

            // Publish
            Actions\Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-globe-alt')
                ->color('success')
                ->visible(fn () => $this->record->canPublish())
                ->form([
                    TextInput::make('published_link')
                        ->label('Link Hasil')
                        ->url()
                        ->required(),
                    TextInput::make('source_link')
                        ->label('Link Source')
                        ->url(),
                    Select::make('linked_project_id')
                        ->label('Link ke Project')
                        ->options(Project::where('status', 'active')->pluck('title', 'id'))
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'published_link' => $data['published_link'],
                        'source_link' => $data['source_link'] ?? null,
                        'linked_project_id' => $data['linked_project_id'] ?? null,
                        'published_at' => now(),
                        'status' => RequestStatus::PUBLISHED,
                    ]);
                    $this->record->createAssetInVault();
                    Notification::make()->title('Konten dipublikasi')->success()->send();
                }),

            // Add Comment
            Actions\Action::make('add_comment')
                ->label('Komentar')
                ->icon('heroicon-o-chat-bubble-left')
                ->color('gray')
                ->form([
                    Textarea::make('message')
                        ->label('Komentar')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    ContentRequestComment::create([
                        'content_request_id' => $this->record->id,
                        'author_type' => auth()->user()->hasRole('head_msc') ? CommentAuthorType::HEAD : CommentAuthorType::STAFF,
                        'user_id' => auth()->id(),
                        'message' => $data['message'],
                    ]);
                    Notification::make()->title('Komentar ditambahkan')->success()->send();
                }),
        ];
    }
}
