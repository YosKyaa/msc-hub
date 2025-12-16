<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\ContentType;
use App\Enums\Platform;
use App\Enums\RequestStatus;
use App\Enums\RequesterType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ContentRequest extends Model
{
    protected $fillable = [
        'request_code',
        'requester_name',
        'requester_email',
        'requester_google_id',
        'requester_type',
        'unit',
        'phone',
        'content_type',
        'platform_target',
        'purpose',
        'audience',
        'event_date',
        'location',
        'deadline',
        'materials_link',
        'notes',
        'status',
        'assigned_to_user_id',
        'staff_approved_at',
        'staff_approved_by',
        'head_approved_at',
        'head_approved_by',
        'reject_reason',
        'rejected_at',
        'rejected_by',
        'published_link',
        'source_link',
        'published_at',
        'archived_at',
        'linked_project_id',
        'created_asset_id',
    ];

    protected $casts = [
        'requester_type' => RequesterType::class,
        'content_type' => ContentType::class,
        'status' => RequestStatus::class,
        'event_date' => 'date',
        'deadline' => 'date',
        'staff_approved_at' => 'datetime',
        'head_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    // Relationships
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function staffApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_approved_by');
    }

    public function headApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function linkedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'linked_project_id');
    }

    public function createdAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'created_asset_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ContentRequestComment::class)->orderBy('created_at', 'asc');
    }

    // Generate unique request code
    public static function generateRequestCode(): string
    {
        $year = now()->year;

        return DB::transaction(function () use ($year) {
            // Get or create sequence for this year with lock
            $sequence = DB::table('content_request_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                $nextNumber = $sequence->last_number + 1;
                DB::table('content_request_sequences')
                    ->where('year', $year)
                    ->update([
                        'last_number' => $nextNumber,
                        'updated_at' => now(),
                    ]);
            } else {
                $nextNumber = 1;
                DB::table('content_request_sequences')->insert([
                    'year' => $year,
                    'last_number' => $nextNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return sprintf('CR-%d-%04d', $year, $nextNumber);
        });
    }

    // Create Asset in Vault
    public function createAssetInVault(): ?Asset
    {
        if ($this->created_asset_id) {
            return $this->createdAsset;
        }

        // Map content_type to asset_type
        $assetType = $this->mapContentTypeToAssetType();
        
        // Map platform_target to platform
        $platform = $this->mapPlatformTarget();

        $asset = Asset::create([
            'project_id' => $this->linked_project_id,
            'title' => $this->generateAssetTitle(),
            'asset_type' => $assetType,
            'platform' => $platform,
            'source_link' => $this->source_link,
            'output_link' => $this->published_link,
            'happened_at' => $this->event_date ?? now(),
            'year' => ($this->event_date ?? now())->year,
            'status' => AssetStatus::PUBLISHED,
            'pic_user_id' => $this->assigned_to_user_id,
            'created_by' => auth()->id(),
            'notes' => "Auto-created from Content Request: {$this->request_code}",
            'is_featured' => false,
        ]);

        // Add tags
        $contentRequestTag = Tag::firstOrCreate(
            ['slug' => 'content-request'],
            ['name' => 'Content Request']
        );
        $asset->tags()->attach($contentRequestTag->id);

        // Add platform tag if exists
        if ($platform) {
            $platformTag = Tag::firstOrCreate(
                ['slug' => strtolower($platform->value)],
                ['name' => ucfirst($platform->value)]
            );
            $asset->tags()->attach($platformTag->id);
        }

        // Update this request with created asset
        $this->update(['created_asset_id' => $asset->id]);

        return $asset;
    }

    protected function generateAssetTitle(): string
    {
        $contentLabel = $this->content_type?->getLabel() ?? 'Content';
        $date = $this->event_date?->format('d M Y') ?? now()->format('d M Y');
        return "{$contentLabel} - {$this->requester_name} ({$date})";
    }

    protected function mapContentTypeToAssetType(): AssetType
    {
        return match ($this->content_type) {
            ContentType::PHOTO_DOCUMENTATION => AssetType::PHOTO,
            ContentType::VIDEO_DOCUMENTATION, ContentType::VIDEO_PROFILE, ContentType::VIDEO_TEASER, ContentType::LIVE_STREAMING => AssetType::VIDEO,
            ContentType::DESIGN_POSTER, ContentType::DESIGN_FLYER => AssetType::DESIGN,
            ContentType::DESIGN_BANNER => AssetType::BANNER,
            ContentType::SOCIAL_MEDIA_POST => AssetType::POST,
            ContentType::WEBSITE_NEWS => AssetType::DOCUMENT,
            default => AssetType::OTHER,
        };
    }

    protected function mapPlatformTarget(): ?Platform
    {
        if (!$this->platform_target) {
            return null;
        }

        $target = strtolower($this->platform_target);

        return match (true) {
            str_contains($target, 'instagram') => Platform::INSTAGRAM,
            str_contains($target, 'youtube') => Platform::YOUTUBE,
            str_contains($target, 'tiktok') => Platform::TIKTOK,
            str_contains($target, 'facebook') => Platform::FACEBOOK,
            str_contains($target, 'website'), str_contains($target, 'web') => Platform::WEBSITE,
            default => Platform::OTHER,
        };
    }

    // Status helpers
    public function canBeAssigned(): bool
    {
        return $this->status === RequestStatus::INCOMING;
    }

    public function canStartProgress(): bool
    {
        return in_array($this->status, [RequestStatus::ASSIGNED, RequestStatus::NEED_REVISION]);
    }

    public function canRequestRevision(): bool
    {
        return $this->status === RequestStatus::IN_PROGRESS;
    }

    public function canStaffApprove(): bool
    {
        return $this->status === RequestStatus::IN_PROGRESS && $this->staff_approved_at === null;
    }

    public function canHeadApprove(): bool
    {
        return $this->status === RequestStatus::WAITING_HEAD_APPROVAL && $this->staff_approved_at !== null;
    }

    public function canReject(): bool
    {
        return in_array($this->status, [
            RequestStatus::INCOMING,
            RequestStatus::ASSIGNED,
            RequestStatus::IN_PROGRESS,
            RequestStatus::WAITING_HEAD_APPROVAL,
        ]);
    }

    public function canPublish(): bool
    {
        return $this->status === RequestStatus::APPROVED;
    }

    public function canArchive(): bool
    {
        return $this->status === RequestStatus::PUBLISHED;
    }
}
