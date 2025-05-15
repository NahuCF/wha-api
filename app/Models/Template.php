<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $guarded = [];

    protected $with = ['buttons'];

    public function buttons()
    {
        return $this->hasMany(TemplateButton::class);
    }
}
