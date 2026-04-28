<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $guarded = [];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function duty()
    {
        return $this->belongsTo(Duty::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments()
    {
        return $this->hasMany(ReportAttachment::class);
    }

    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        if (! $this->attachment) {
            return null;
        }

        return asset('storage/report-attachments/'.$this->attachment);
    }

    public function comments()
    {
        return $this->hasMany(ReportComment::class)->latest();
    }

    public function parent()
    {
        return $this->belongsTo(Report::class, 'parent_report_id');
    }

    public function children()
    {
        return $this->hasMany(Report::class, 'parent_report_id');
    }
}
