<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Announcement extends Model
{
    protected $fillable = [
        'created_by',
        'title',
        'content',
        'audience_type',
        'target_type',
        'is_pinned',
        'published_at',
    ];

    protected $casts = [
        'is_pinned'    => 'boolean',
        'published_at' => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────────────

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetUsers()
    {
        return $this->belongsToMany(User::class, 'announcement_targets');
    }

    public function targetClasses()
    {
        return $this->belongsToMany(ClassRoom::class, 'announcement_class_targets', 'announcement_id', 'class_id');
    }

    public function attachments()
    {
        return $this->hasMany(AnnouncementAttachment::class);
    }

    public function reads()
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────────

    /**
     * Scope: only published announcements.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Scope for a given staff user: return all announcements visible to them.
     * Applies to audience_type = 'teachers'.
     */
    public function scopeVisibleToTeacher(Builder $query, User $user): Builder
    {
        return $query
            ->where('audience_type', 'teachers')
            ->where(function ($q) use ($user) {
                $q->where('target_type', 'all')
                  ->orWhere(function ($q2) use ($user) {
                      $q2->where('target_type', 'specific')
                         ->whereHas('targetUsers', fn ($q3) => $q3->where('user_id', $user->id));
                  });
            });
    }

    /**
     * Scope for a given student user: return all announcements visible to them.
     * Applies to audience_type = 'students'.
     */
    public function scopeVisibleToStudent(Builder $query, User $user): Builder
    {
        $student = $user->student;

        return $query
            ->where('audience_type', 'students')
            ->where(function ($q) use ($user, $student) {
                // All students
                $q->where('target_type', 'all')
                  // By class
                  ->orWhere(function ($q2) use ($student) {
                      $q2->where('target_type', 'class')
                         ->when($student, function ($q3) use ($student) {
                             $q3->whereHas('targetClasses', fn ($q4) => $q4->where('class_id', $student->class_id));
                         });
                  })
                  // Specific individual
                  ->orWhere(function ($q2) use ($user) {
                      $q2->where('target_type', 'specific')
                         ->whereHas('targetUsers', fn ($q3) => $q3->where('user_id', $user->id));
                  });
            });
    }
}
