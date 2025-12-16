<?php

namespace App\Enums;

enum RequestStatus: string
{
    case INCOMING = 'incoming';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case NEED_REVISION = 'need_revision';
    case WAITING_HEAD_APPROVAL = 'waiting_head_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::INCOMING => 'Incoming',
            self::ASSIGNED => 'Assigned',
            self::IN_PROGRESS => 'In Progress',
            self::NEED_REVISION => 'Need Revision',
            self::WAITING_HEAD_APPROVAL => 'Waiting Head Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::INCOMING => 'info',
            self::ASSIGNED => 'primary',
            self::IN_PROGRESS => 'warning',
            self::NEED_REVISION => 'danger',
            self::WAITING_HEAD_APPROVAL => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::PUBLISHED => 'success',
            self::ARCHIVED => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::INCOMING => 'heroicon-o-inbox',
            self::ASSIGNED => 'heroicon-o-user',
            self::IN_PROGRESS => 'heroicon-o-clock',
            self::NEED_REVISION => 'heroicon-o-arrow-path',
            self::WAITING_HEAD_APPROVAL => 'heroicon-o-clock',
            self::APPROVED => 'heroicon-o-check-circle',
            self::REJECTED => 'heroicon-o-x-circle',
            self::PUBLISHED => 'heroicon-o-globe-alt',
            self::ARCHIVED => 'heroicon-o-archive-box',
        };
    }
}
