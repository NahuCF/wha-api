<?php

namespace App\Traits;

use App\Models\Scopes\WabaIdScope;

trait HasWabaId
{
    /**
     * Boot the has waba id trait for a model.
     *
     * @return void
     */
    public static function bootHasWabaId()
    {
        static::addGlobalScope(new WabaIdScope);

        static::creating(function ($model) {
            if (! $model->waba_id) {
                $model->waba_id = app('waba_id');
            }
        });
    }

    /**
     * Initialize the has waba id trait for an instance.
     *
     * @return void
     */
    public function initializeHasWabaId()
    {
        if (! isset($this->fillable)) {
            $this->fillable = [];
        }

        if (! in_array('meta_waba_i', $this->fillable)) {
            $this->fillable[] = 'waba_id';
        }
    }

    /**
     * Scope a query to only include records for current waba_id
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCurrentWaba($query)
    {
        return $query->where('waba_id', app('waba_id'));
    }

    /**
     * Scope a query to a specific waba_id
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $wabaId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForWaba($query, $wabaId)
    {
        return $query->where('waba_id', $wabaId);
    }
}
