<?php

namespace App\Models;

use App\Core\Enums\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'api';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'is_new_user' => 'boolean',
            'last_seen_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ---------- JWT ----------
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /** Claims read by the Node realtime service too. */
    public function getJWTCustomClaims(): array
    {
        return [
            'roles' => $this->getRoleNames()->values()->all(),
            'name' => $this->name,
            'new' => $this->is_new_user,
        ];
    }

    // ---------- relations ----------
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function interests(): HasMany
    {
        return $this->hasMany(UserExamInterest::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /** Courses this staff/teacher is assigned to (manage without buying). */
    public function managedCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_staff')->withPivot('role')->withTimestamps();
    }

    // ---------- helpers ----------
    public function isPanelUser(): bool
    {
        return $this->hasAnyRole(Role::panelRoles()) || $this->roles()->where('name', '!=', Role::Student->value)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole([Role::SuperAdmin->value, Role::Admin->value]);
    }

    public function isTeacher(): bool
    {
        return $this->hasRole(Role::Teacher->value);
    }

    public function primaryRole(): string
    {
        foreach ([Role::SuperAdmin, Role::Admin, Role::Staff, Role::Teacher, Role::Student] as $r) {
            if ($this->hasRole($r->value)) {
                return $r->value;
            }
        }

        return $this->getRoleNames()->first() ?? Role::Student->value;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2)))) ?: 'U';
    }

    public function age(): ?int
    {
        return $this->dob?->age;
    }
}
