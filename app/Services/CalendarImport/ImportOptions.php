<?php

namespace App\Services\CalendarImport;

use Carbon\Carbon;

/**
 * Parametri scelti dall'utente per un'importazione (modulo di caricamento + scelte nell'anteprima).
 */
class ImportOptions
{
    /**
     * @param  array<string, string>  $groupMap  chiave nome gruppo => id gruppo | 'new' | ''
     * @param  array<string, string>  $bannerActions  chiave testo banda => association_event | assembly | ignore
     */
    public function __construct(
        public int $seasonYear,
        public string $sessionStart = '21:30',
        public string $sessionEnd = '00:00',
        public string $eventStart = '21:30',
        public string $eventEnd = '00:00',
        public array $groupMap = [],
        public array $bannerActions = [],
    ) {
    }

    /** Anno di inizio della stagione corrente: da agosto in poi l'anno corrente, prima quello precedente. */
    public static function defaultSeasonYear(): int
    {
        $now = Carbon::now(config('app.timezone'));

        return $now->month >= 8 ? $now->year : $now->year - 1;
    }

    public static function fromInput(array $input): self
    {
        return new self(
            seasonYear: (int) ($input['season_year'] ?? self::defaultSeasonYear()),
            sessionStart: (string) ($input['session_start'] ?? '21:30'),
            sessionEnd: (string) ($input['session_end'] ?? '00:00'),
            eventStart: (string) ($input['event_start'] ?? '21:30'),
            eventEnd: (string) ($input['event_end'] ?? '00:00'),
            groupMap: array_map('strval', (array) ($input['group_map'] ?? [])),
            bannerActions: array_map('strval', (array) ($input['banner_action'] ?? [])),
        );
    }

    /** Settembre-dicembre appartengono all'anno di stagione, gennaio-agosto a quello successivo. */
    public function yearFor(int $month): int
    {
        return $month >= 9 ? $this->seasonYear : $this->seasonYear + 1;
    }
}
