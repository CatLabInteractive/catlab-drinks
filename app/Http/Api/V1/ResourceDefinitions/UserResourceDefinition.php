<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\User;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class UserResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class UserResourceDefinition extends ResourceDefinition
{
    /**
     * UserResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(User::class);

        $this
            ->identifier('id')
            ->int();

        $this->field('name')
            ->required()
            ->visible(true)
            ->writeable();

        $this->field('email')
            ->visible(true);
    }
}