<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssueAction extends Model
{
     protected $guarded = [];

    public function issue()
    {
        return $this->belongsTo(Issue::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
