<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class BasePolicy
 * @package App\Policies
 */
class BasePolicy
{
    /**
     * @param User|null $user
     * @return bool
     */
    public function isAdmin(User $user = null)
    {
        return in_array($user->id, config('admin.admin_user_ids'));
    }
}