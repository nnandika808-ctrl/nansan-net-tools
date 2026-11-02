<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratorVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['evidence' => 'array'];
    }
}
