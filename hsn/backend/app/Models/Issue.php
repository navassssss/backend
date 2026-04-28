<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    

    // guarded
    protected $guarded = [];
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function relatedTeacher()
    {
        return $this->belongsTo(User::class, 'related_teacher_id');
    }

    public function duty()
    {
        return $this->belongsTo(Duty::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function comments()
    {
        return $this->hasMany(IssueComment::class);
    }

    public function actions()
    {
        return $this->hasMany(IssueAction::class)->orderBy('created_at');
    }
    public function category(){
        return $this->belongsTo(IssueCategory::class);
    }
}
