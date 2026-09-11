<?php

namespace App\Policies;

use App\Models\Fursona;
use App\Models\User;

class FursonaPolicy
{
    /** 擁有者專用的完整檢視（管理後台預覽走 admin Gate）。 */
    public function view(User $user, Fursona $fursona): bool
    {
        return $user->id === $fursona->owner_id || $user->isAdmin();
    }

    public function update(User $user, Fursona $fursona): bool
    {
        return $user->id === $fursona->owner_id;
    }

    public function delete(User $user, Fursona $fursona): bool
    {
        return $user->id === $fursona->owner_id;
    }
}
