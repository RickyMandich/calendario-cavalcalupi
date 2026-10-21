<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionSwapRequest extends Model
{
    protected $fillable = [
        'event_id', 'group_id', 'requested_by', 'from_date', 'target_date',
        'status', 'matched_request_id', 'completed_at', 'cancelled_at',
        'cancelled_by', 'cancel_reason', 'pending_flag',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'target_date' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function matchedRequest(): BelongsTo
    {
        return $this->belongsTo(self::class, 'matched_request_id');
    }

    public function markCompleted(self $other): void
    {
        $this->forceFill([
            'status' => 'completed',
            'matched_request_id' => $other->id,
            'completed_at' => now(),
            'pending_flag' => null,
        ])->save();
    }

    public function markCancelled(?User $by, string $reason): void
    {
        $this->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $by?->id,
            'cancel_reason' => $reason,
            'pending_flag' => null,
        ])->save();
    }
}
