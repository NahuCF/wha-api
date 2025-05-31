<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $with = ['buttons'];

    public function buttons()
    {
        return $this->hasMany(TemplateButton::class);
    }
}
