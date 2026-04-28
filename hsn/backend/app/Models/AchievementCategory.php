<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AchievementCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'points',
        'applies_to_class',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'applies_to_class' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function achievements()
    {
        return $this->hasMany(Achievement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
