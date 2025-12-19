<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassRoom extends Model
{
    protected $fillable = ['name', 'department', 'total_points'];

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }
}
