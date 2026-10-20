<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope to only active groups.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Users associated with this group.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('can_manage_sessions')->withTimestamps();
    }

    /**
     * Events/Sessions of this group.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Assign a user to the group with validation.
     */
    public function addUser(User|int $user): void
    {
        if (!$this->is_active) {
            throw new DomainException('Impossibile assegnare un utente a un gruppo non attivo.');
        }

        $userId = $user instanceof User ? $user->id : $user;
        $this->users()->syncWithoutDetaching([$userId]);
    }
    /**
     * Users who can manage sessions for this group.
     */
    public function managers(): BelongsToMany
    {
        return $this->users()->wherePivot('can_manage_sessions', true);
    }

    /**
     * Set or unset a user as session manager for this group.
     */
    public function setSessionManager(User|int $user, bool $value): void
    {
        if (!$this->is_active) {
            throw new DomainException('Impossibile modificare i responsabili di un gruppo non attivo.');
        }
        $userId = $user instanceof User ? $user->id : $user;
        if (!$this->users()->where('users.id', $userId)->exists()) {
            throw new DomainException('L\'utente non appartiene al gruppo.');
        }
        $this->users()->updateExistingPivot($userId, ['can_manage_sessions' => $value]);
    }

}

