<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportAttachment extends Model
{
    protected $guarded = [];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
