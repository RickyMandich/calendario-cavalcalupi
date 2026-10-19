<?php

namespace App\Models;

use App\Observers\EventObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[ObservedBy([EventObserver::class])]
class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_time',
        'end_time',
        'type',
        'group_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Event $event) {
            if ($event->type === 'session') {
                if (!$event->group_id) {
                    throw new InvalidArgumentException('Una sessione deve essere associata a un gruppo.');
                }
                $group = Group::find($event->group_id);
                if (!$group || !$group->is_active) {
                    throw new InvalidArgumentException('La sessione può essere associata solo a un gruppo attivo.');
                }
            } else {
                $event->group_id = null;
            }
        });
    }

    /**
     * Group associated with this event (if session).
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * User who created this event.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Change logs for this event.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(EventLog::class)->latest();
    }
}
