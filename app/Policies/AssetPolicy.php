<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->hasAnyRole(['admin', 'staff_msc', 'head_msc']);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
