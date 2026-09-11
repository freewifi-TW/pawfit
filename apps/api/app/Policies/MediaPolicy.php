<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function update(User $user, Media $media): bool
    {
        return $user->id === $media->owner_id;
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->id === $media->owner_id;
    }
}
