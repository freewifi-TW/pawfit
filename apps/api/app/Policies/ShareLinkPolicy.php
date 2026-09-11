<?php

namespace App\Policies;

use App\Models\ShareLink;
use App\Models\User;

class ShareLinkPolicy
{
    public function update(User $user, ShareLink $link): bool
    {
        return $user->id === $link->fursona->owner_id;
    }

    public function delete(User $user, ShareLink $link): bool
    {
        return $user->id === $link->fursona->owner_id;
    }
}
