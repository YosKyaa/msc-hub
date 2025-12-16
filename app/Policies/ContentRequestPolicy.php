<?php

namespace App\Policies;

use App\Models\ContentRequest;
use App\Models\User;

class ContentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function view(User $user, ContentRequest $contentRequest): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function update(User $user, ContentRequest $contentRequest): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function delete(User $user, ContentRequest $contentRequest): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, ContentRequest $contentRequest): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, ContentRequest $contentRequest): bool
    {
        return $user->hasRole('admin');
    }
}
