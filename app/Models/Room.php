<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = ['name', 'capacity', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    /**
     * Scope a query to only include active rooms.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the weekdays associated with the room.
     */
    public function weekdays(): HasMany
    {
        return $this->hasMany(RoomWeekday::class);
    }

    /**
     * Scope to rooms usable on a given ISO weekday (1 = Monday ... 7 = Sunday).
     */
    public function scopeUsableOn($query, int $isoWeekday)
    {
        return $query->whereHas('weekdays', fn($q) => $q->where('weekday', $isoWeekday));
    }

    /**
     * Sync the weekdays for the room.
     *
     * @param array $days Array of integers 1-7 representing weekdays.
     */
    public function syncWeekdays(array $days): void
    {
        // Ensure unique, valid values
        $days = array_unique(array_filter($days, fn($d) => $d >= 1 && $d <= 7));
        // Use a transaction to replace existing rows
        \DB::transaction(function () use ($days) {
            $this->weekdays()->delete();
            foreach ($days as $day) {
                $this->weekdays()->create(['weekday' => $day]);
            }
        });
    }
}
?>
