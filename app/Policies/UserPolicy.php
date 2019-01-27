<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class UserPolicy
 * @package App\Policies
 */
class UserPolicy extends BasePolicy
{
    /**
     * @param User|null $user
     * @return bool
     */
    public function index(User $user = null)
    {
        return $this->isAdmin($user);
    }

    /**
     * @param User $user
     * @param $targetUser
     * @return bool
     */
    public function show(?User $user, $targetUser)
    {
        return $this->isAdmin($user) || $user->id === $targetUser->id;
    }
}