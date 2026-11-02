<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratorTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['defaults' => 'array', 'is_active' => 'boolean'];
    }
}
