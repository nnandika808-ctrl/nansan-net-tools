<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FeedbackReport extends Model { protected $guarded = ['id']; public function attachments() { return $this->belongsToMany(MediaFile::class, 'feedback_attachments'); } }
