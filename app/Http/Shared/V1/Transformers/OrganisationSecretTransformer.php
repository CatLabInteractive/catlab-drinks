<?php

namespace App\Http\Shared\V1\Transformers;

use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\Transformer;
use LogicException;

/**
 * Only exposes the organisation secret when the controller explicitly
 * marks the context as allowed to see it (see DeviceApi OrganisationController).
 */
class OrganisationSecretTransformer implements Transformer
{
    const CONTEXT_PARAMETER = 'can_view_organisation_secret';

    public function toResourceValue($value, Context $context)
    {
        if ($value === null) {
            return null;
        }

        if (!$context->getParameter(self::CONTEXT_PARAMETER)) {
            return null;
        }

        return $value;
    }

    public function toEntityValue($value, Context $context)
    {
        throw new LogicException('Setting the organisation secret is not allowed.');
    }

    public function toParameterValue($value)
    {
        throw new LogicException('Setting the organisation secret is not allowed.');
    }
}
