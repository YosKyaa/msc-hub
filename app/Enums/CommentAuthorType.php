<?php

namespace App\Enums;

enum CommentAuthorType: string
{
    case REQUESTER = 'requester';
    case STAFF = 'staff';
    case HEAD = 'head';

    public function getLabel(): string
    {
        return match ($this) {
            self::REQUESTER => 'Requester',
            self::STAFF => 'Staff MSC',
            self::HEAD => 'Head MSC',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::REQUESTER => 'info',
            self::STAFF => 'primary',
            self::HEAD => 'success',
        };
    }
}
