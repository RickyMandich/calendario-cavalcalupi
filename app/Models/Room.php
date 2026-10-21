<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Room extends Model
{
    protected $fillable = ['name', 'capacity', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    /**
     * Solo le stanze attive.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Giorni della settimana in cui la stanza è utilizzabile.
     */
    public function weekdays(): HasMany
    {
        return $this->hasMany(RoomWeekday::class);
    }

    /**
     * Stanze utilizzabili in un dato giorno ISO (1 = lunedì ... 7 = domenica).
     */
    public function scopeUsableOn(Builder $query, int $isoWeekday): Builder
    {
        return $query->whereHas('weekdays', fn ($q) => $q->where('weekday', $isoWeekday));
    }

    /**
     * Sostituisce i giorni utilizzabili (accetta solo interi 1-7, senza duplicati).
     *
     * @param  array<int|string>  $days
     */
    public function syncWeekdays(array $days): void
    {
        $days = collect($days)
            ->map(fn ($d) => (int) $d)
            ->filter(fn ($d) => $d >= 1 && $d <= 7)
            ->unique()
            ->values();

        DB::transaction(function () use ($days) {
            $this->weekdays()->delete();
            foreach ($days as $day) {
                $this->weekdays()->create(['weekday' => $day]);
            }
        });
    }
}
