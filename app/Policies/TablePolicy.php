<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Table;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Class TablePolicy
 * @package App\Policies
 */
class TablePolicy extends BasePolicy
{
    /**
     * @param Authorizable|null $user
     * @param Event $event
     * @return bool
     */
    public function index(?Authorizable $user, Event $event)
    {
        return $this->isMyEvent($user, $event, true);
    }

    /**
     * @param Authorizable|null $user
     * @param Event $event
     * @return bool
     */
    public function create(?Authorizable $user, Event $event)
    {
        return $this->isMyEvent($user, $event, true);
    }

    /**
     * @param Authorizable|null $user
     * @param Table $table
     * @return bool
     */
    public function view(?Authorizable $user, Table $table)
    {
        return $this->isMyEvent($user, $table->event, true);
    }

    /**
     * @param Authorizable|null $user
     * @param Table $table
     * @return bool
     */
    public function edit(?Authorizable $user, Table $table)
    {
        return $this->isMyEvent($user, $table->event, true);
    }

    /**
     * @param Authorizable|null $user
     * @param Table $table
     * @return bool
     */
    public function destroy(?Authorizable $user, Table $table)
    {
        return $this->isMyEvent($user, $table->event);
    }
}
