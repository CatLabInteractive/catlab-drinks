<?php


namespace App\Http\Shared\V1\Transformers;

/**
 * Class DateTransformer
 * @package App\Http\ManagementApi\V1\Transformers
 */
class DateTransformer extends \CatLab\Charon\Transformers\DateTransformer
{
    protected $format = 'Y-m-d';
}
