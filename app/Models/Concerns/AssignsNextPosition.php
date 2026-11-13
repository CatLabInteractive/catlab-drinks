<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Gives a new row the next position within its event. Soft deleted rows count,
 * so a new row never takes the position of a deleted one.
 */
trait AssignsNextPosition
{
    protected static function bootAssignsNextPosition()
    {
        static::creating(function (Model $model) {
            if (empty($model->position)) {
                $model->position = (int) static::withTrashed()
                    ->where('event_id', $model->event_id)
                    ->max('position') + 1;
            }
        });
    }
}
