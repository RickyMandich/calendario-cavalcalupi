<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (empty($user->ics_token)) {
                $user->ics_token = Str::random(64);
            }
        });
    }

    /**
     * Get ics_token, automatically generating and saving one if null.
     */
    public function getIcsTokenAttribute(?string $value): string
    {
        if (empty($value)) {
            $value = Str::random(64);
            $this->forceFill(['ics_token' => $value])->saveQuietly();
        }

        return $value;
    }

    /**
     * Groups this user belongs to.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('can_manage_sessions')->withTimestamps();
    }

    /**
     * Events created by this user.
     */
    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    /**
     * Event logs performed by this user.
     */
    public function eventLogs(): HasMany
    {
        return $this->hasMany(EventLog::class);
    }

    /**
     * Determine if the user is staff (ADMIN or CD).
     */
    /**
     * Groups where the user can manage sessions (staff or has can_manage_sessions true).
     */
    public function managedGroups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->wherePivot('can_manage_sessions', true)
            ->where('groups.is_active', true);
    }

    /**
     * Determine if the user can manage sessions of a given group.
     */
    public function canManageSessionsOf(Group|int $group): bool
    {
        if ($this->isStaff()) {
            return true;
        }
        $groupId = $group instanceof Group ? $group->id : $group;

        return $this->managedGroups()->where('groups.id', $groupId)->exists();
    }

    /**
     * Determine if the user is staff (ADMIN or CD).
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(['ADMIN', 'CD']);
    }
}
