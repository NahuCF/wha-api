<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class WabaIdScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $wabaId = app('waba_id');

        if ($wabaId && $this->hasWabaIdColumn($model)) {
            $builder->where($model->getTable().'.waba_id', $wabaId);
        }
    }

    /**
     * Check if the model's table has waba_id column
     */
    private function hasWabaIdColumn(Model $model): bool
    {
        return in_array('waba_id', $model->getFillable()) ||
               property_exists($model, 'useWabaId') && $model->useWabaId;
    }
}
