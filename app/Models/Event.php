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
