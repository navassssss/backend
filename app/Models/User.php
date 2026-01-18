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

    public function duties()
    {
        return $this->belongsToMany(Duty::class, 'duty_teacher', 'teacher_id', 'duty_id')
            ->withPivot(['assigned_by', 'start_date', 'end_date', 'order_index'])
            ->withTimestamps();
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
        'email',
        'password',
        'role',
        'department',
        'phone',
        'avatar',
        'can_review_achievements',
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
        return in_array($this->role, ['principal', 'manager']);
    }
}
