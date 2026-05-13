<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [
    ];

    protected $appends = ['abilities'];

    public function duties()
    {
        return $this->belongsToMany(Duty::class, 'duty_teacher', 'teacher_id', 'duty_id')
            ->withPivot(['assigned_by', 'start_date', 'end_date', 'order_index'])
            ->withTimestamps();
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasPermission($permissionName): bool
    {
        if ($this->isPrincipal()) {
            return true;
        }

        return $this->permissions()->where('name', $permissionName)->exists();
    }

    // tasks
    public function tasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'department',
        'phone',
        'avatar',
        'can_review_achievements',
        'is_vice_principal',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_review_achievements' => 'boolean',
            'is_vice_principal' => 'boolean',
        ];
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'teacher_id');
    }

    /**
     * Relationship to student profile (one-to-one)
     */
    public function student()
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Role helper methods
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isPrincipal(): bool
    {
        return $this->role === 'principal'
            || ($this->role === 'teacher' && $this->is_vice_principal);
    }

    /**
     * Frontend synchronized abilities
     */
    protected function abilities(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => [
                'students.manage'       => $this->can('create', Student::class),
                'fees.manage'           => $this->can('create', \App\Models\FeePayment::class),
                'subjects.manage'       => $this->can('create', \App\Models\Subject::class),
                'outpasses.manage'      => $this->can('create', \App\Models\Outpass::class),
                'medical.manage'        => $this->can('create', \App\Models\MedicalRecord::class),
                'cce.manage'            => $this->can('create', \App\Models\CCEWork::class),
                'announcements.manage'  => $this->can('create', \App\Models\Announcement::class),
                'duties.manage'         => $this->can('create', \App\Models\Duty::class),
                'tasks.manage'          => $this->can('create', \App\Models\Task::class),
                'reports.review'        => $this->isPrincipal(),
                'achievements.review'   => $this->hasPermission('review_achievements'),
                'issues.manage'         => $this->isPrincipal() || $this->role === 'manager',
            ]
        );
    }
}
