<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Tool extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['ros_versions'=>'array','featured'=>'boolean']; }
    public function getRouteKeyName(): string { return 'slug'; }
    public function category() { return $this->belongsTo(ToolCategory::class, 'tool_category_id'); }
    public function synonyms() { return $this->hasMany(ToolSynonym::class); }
    public function versions() { return $this->hasMany(GeneratorVersion::class); }
}
