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

    /**
     * Contesto extra scritto nel log della prossima modifica (es. scambio). Proprietà reale,
     * non è un attributo e non viene salvata.
     */
    public ?array $logContext = null;

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
        // NB: qui NON si applicano capienza/assemblee: stanno in App\Services\CalendarScheduler,
        // così seeder, test e tinker non vengono bloccati. Solo coerenza type/group_id.
        static::saving(function (Event $event) {
            if ($event->type === 'session') {
                if (! $event->group_id) {
                    throw new InvalidArgumentException('Una sessione deve essere associata a un gruppo.');
                }
                $group = Group::find($event->group_id);
                if (! $group || ! $group->is_active) {
                    throw new InvalidArgumentException('La sessione può essere associata solo a un gruppo attivo.');
                }
            } else {
                $event->group_id = null;
            }
        });
    }

    /**
     * Durata in forma leggibile: "2 ore e 30 min", "2 ore", "1 ora", "45 min".
     * Si parte dai minuti interi (diffInHours() di Carbon 3 restituisce un float: 2.5).
     */
    public function durationLabel(): string
    {
        $minutes = (int) round(abs($this->start_time->diffInMinutes($this->end_time)));
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours === 1 ? '1 ora' : "{$hours} ore";
        }
        if ($rest > 0) {
            $parts[] = "{$rest} min";
        }

        return $parts ? implode(' e ', $parts) : '0 min';
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(EventLog::class)->latest();
    }

    public function swapRequests(): HasMany
    {
        return $this->hasMany(SessionSwapRequest::class)->where('status', 'pending');
    }
}
