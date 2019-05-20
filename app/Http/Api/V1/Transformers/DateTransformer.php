<?php


namespace App\Http\Api\V1\Transformers;

/**
 * Class DateTransformer
 * @package App\Http\Api\V1\Transformers
 */
class DateTransformer extends \CatLab\Charon\Transformers\DateTransformer
{
    protected $format = 'Y-m-d';
}