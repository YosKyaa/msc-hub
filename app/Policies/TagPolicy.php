<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->hasRole('admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Tag $tag): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Tag $tag): bool
    {
        return $user->hasRole('admin');
    }
}
