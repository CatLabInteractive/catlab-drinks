<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Patron;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Class PatronPolicy
 * @package App\Policies
 */
class PatronPolicy extends BasePolicy
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
     * @param Patron $patron
     * @return bool
     */
    public function view(?Authorizable $user, Patron $patron)
    {
        return $this->isMyEvent($user, $patron->event, true);
    }

    /**
     * @param Authorizable|null $user
     * @param Patron $patron
     * @return bool
     */
    public function edit(?Authorizable $user, Patron $patron)
    {
        return $this->isMyEvent($user, $patron->event, true);
    }

    /**
     * @param Authorizable|null $user
     * @param Patron $patron
     * @return bool
     */
    public function destroy(?Authorizable $user, Patron $patron)
    {
        return $this->isMyEvent($user, $patron->event);
    }
}
