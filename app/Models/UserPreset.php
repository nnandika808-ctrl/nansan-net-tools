<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserPreset extends Model { protected $guarded = ['id']; protected function casts(): array { return ['input'=>'array']; } public function tool() { return $this->belongsTo(Tool::class); } }
