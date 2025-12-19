<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssueCategory extends Model
{
     protected $guarded = [];

    public function issues()
    {
        return $this->hasMany(Issue::class, 'category_id');
    }
}
