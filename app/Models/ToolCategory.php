<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ToolCategory extends Model { protected $guarded = ['id']; public function tools() { return $this->hasMany(Tool::class); } }
