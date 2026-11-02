<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CmsPage extends Model { protected $guarded = ['id']; protected function casts(): array { return ['owner_review_required'=>'boolean']; } public function getRouteKeyName(): string { return 'slug'; } }
