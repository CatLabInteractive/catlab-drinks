<?php

namespace App\Http\DeviceApi\V1\Transformers;

use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Transformers\DateTransformer;
use Illuminate\Support\Facades\Crypt;
use LogicException;

class SecretKeyTransformer extends DateTransformer
{
	public function toResourceValue($value, Context $context)
	{
		if ($value === null) {
			return null;
		}

		// Authorize that we can do this
		if (!$context->getParameter('can_view_secret')) {
			return null;
		}

		// Do not expose the secret key to the outside world.
		return Crypt::decryptString($value);
	}

	/**
	 * @param mixed $value 
	 * @param Context $context 
	 * @return void
	 * @throws LogicException 
	 */
	public function toEntityValue($value, Context $context)
	{
		throw new LogicException('Setting secret key is not allowed.');
	}

	/**
	 * @param mixed $value 
	 * @return mixed 
	 * @throws LogicException 
	 */
	public function toParameterValue($value)
	{
		throw new LogicException('Setting secret key is not allowed.');
	}
}