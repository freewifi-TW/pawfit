<?php

namespace App\Policies;

use App\Models\CommissionKit;
use App\Models\User;

class CommissionKitPolicy
{
    public function view(User $user, CommissionKit $kit): bool
    {
        return $user->id === $kit->owner_id || $user->isAdmin();
    }

    public function update(User $user, CommissionKit $kit): bool
    {
        return $user->id === $kit->owner_id;
    }

    public function delete(User $user, CommissionKit $kit): bool
    {
        return $user->id === $kit->owner_id;
    }
}
