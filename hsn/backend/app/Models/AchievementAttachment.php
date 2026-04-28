<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AchievementAttachment extends Model
{
    protected $fillable = [
        'achievement_id',
        'file_path',
        'file_name',
        'mime_type',
    ];

    public function achievement()
    {
        return $this->belongsTo(Achievement::class);
    }
}
