<?php

namespace App\Services\CalendarImport;

use App\Exceptions\SchedulingException;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use App\Services\CalendarScheduler;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Importazione del calendario in due passi: anteprima (errori riga per riga, scelta dei gruppi e delle bande)
 * e commit tutto-o-niente. Applica le stesse regole del form tramite CalendarScheduler.
 */
class CalendarImportService
{
    private const WEEKDAY_NAMES = [1 => 'lunedì', 2 => 'martedì', 3 => 'mercoledì', 4 => 'giovedì', 5 => 'venerdì', 6 => 'sabato', 7 => 'domenica'];

    public const BANNER_ACTIONS = ['association_event', 'assembly', ImportRow::ACTION_IGNORE];

    public function __construct(
        private CalendarImportParser $parser,
        private CalendarScheduler $scheduler,
    ) {
    }

    /** Chiave stabile (usata nei campi del modulo) per un nome di gruppo o un testo di banda. */
    public static function key(string $text): string
    {
        return sha1(self::normalize($text));
    }

    public static function normalize(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
    }

    /**
     * @throws ImportException
     */
    public function preview(string $path, ImportOptions $options): ImportPreview
    {
        return $this->analyze($this->parser->parse($path), $options);
    }

    /**
     * Crea tutti gli eventi (e gli eventuali gruppi nuovi), oppure nessuno. La validazione viene RIPETUTA dentro il
     * lock perché il DB può essere cambiato dopo l'anteprima.
     *
     * @return int numero di eventi creati
     *
     * @throws ImportException
     * @throws ImportFailedException
     */
    public function commit(string $path, ImportOptions $options, User $by): int
    {
        $cells = $this->parser->parse($path);

        return $this->scheduler->withLock(function () use ($cells, $options, $by) {
            $preview = $this->analyze($cells, $options);

            if (! $preview->canCommit() || $preview->unresolvedGroups()) {
                throw new ImportFailedException($preview);
            }

            // Gruppi nuovi (uno per nome distinto)
            $groupIds = [];
            foreach ($preview->groups as $key => $choice) {
                if ($choice['selected'] === 'new') {
                    $groupIds[$key] = Group::create(['name' => $choice['name'], 'is_active' => true])->id;
                } else {
                    $groupIds[$key] = (int) $choice['selected'];
                }
            }

            $created = 0;
            foreach ($preview->activeRows() as $row) {
                $attributes = $row->attributes;
                if ($row->kind === 'table') {
                    $attributes['group_id'] = $groupIds[$row->groupKey];
                }
                Event::create(array_merge($attributes, ['created_by' => $by->id]));
                $created++;
            }

            return $created;
        });
    }

    /**
     * @param  Collection<int, ParsedCell>  $cells
     */
    public function analyze(Collection $cells, ImportOptions $options): ImportPreview
    {
        $tz = config('app.timezone');
        $allGroups = Group::all();
        $byName = $allGroups->groupBy(fn (Group $g) => self::normalize($g->name));
        $activeIds = $allGroups->where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $extra = [];
        $seen = [];
        $rows = collect();
        $groupChoices = [];
        $bannerChoices = [];

        foreach ($cells as $cell) {
            $lines = $cell->lines();
            if (! $lines) {
                continue;
            }

            $row = new ImportRow($cell->row, $cell->sheet, $cell->kind, $cell->label, implode(' / ', $lines));

            // --- Tipo di riga, gruppo o banda -------------------------------------------------------------
            if ($cell->kind === 'table') {
                $name = count($lines) >= 2 ? implode(' ', array_slice($lines, 1)) : $lines[0];
                $row->groupName = $name;
                $row->groupKey = self::key($name);
                $row->system = count($lines) >= 2 ? $lines[0] : null;
                $row->action = 'session';

                if (! isset($groupChoices[$row->groupKey])) {
                    $matches = $byName->get(self::normalize($name), collect());
                    $active = $matches->where('is_active', true);
                    $auto = $active->count() === 1 ? (string) $active->first()->id : '';

                    $groupChoices[$row->groupKey] = [
                        'key' => $row->groupKey,
                        'name' => $name,
                        'count' => 0,
                        'selected' => $options->groupMap[$row->groupKey] ?? $auto,
                        'inactive' => $matches->isNotEmpty() && $active->isEmpty(),
                    ];
                }
                $groupChoices[$row->groupKey]['count']++;

                $selected = $groupChoices[$row->groupKey]['selected'];
                if ($selected !== '' && $selected !== 'new' && ! in_array($selected, $activeIds, true)) {
                    $row->errors[] = 'Il gruppo scelto non esiste o non è attivo.';
                }
            } else {
                $text = implode(' ', $lines);
                $row->bannerKey = self::key($text);

                $default = preg_match('/gioco\s+libero/iu', $text) ? ImportRow::ACTION_IGNORE : 'association_event';
                $action = $options->bannerActions[$row->bannerKey] ?? $default;
                $row->action = in_array($action, self::BANNER_ACTIONS, true) ? $action : $default;

                if (! isset($bannerChoices[$row->bannerKey])) {
                    $bannerChoices[$row->bannerKey] = ['key' => $row->bannerKey, 'text' => $text, 'count' => 0, 'action' => $row->action];
                }
                $bannerChoices[$row->bannerKey]['count']++;

                if (mb_strlen($text) > 255) {
                    $row->errors[] = 'Testo troppo lungo (massimo 255 caratteri).';
                }
            }

            // --- Data ------------------------------------------------------------------------------------
            $date = null;
            $year = $options->yearFor($cell->month);
            if (! checkdate($cell->month, $cell->day, $year)) {
                $row->errors[] = "La data «{$cell->label}» non esiste nel mese indicato.";
            } else {
                $date = Carbon::create($year, $cell->month, $cell->day, 0, 0, 0, $tz);
                $row->date = $date->toDateString();
                if ($date->dayOfWeekIso !== $cell->weekday) {
                    $actual = self::WEEKDAY_NAMES[$date->dayOfWeekIso];
                    $row->errors[] = "«{$cell->label}» non corrisponde al {$date->format('d/m/Y')}, che è un {$actual}: controlla l'anno della stagione.";
                    $date = null;
                }
            }

            // --- Orari -----------------------------------------------------------------------------------
            $start = $end = null;
            if ($date) {
                [$from, $to] = $cell->kind === 'table'
                    ? [$options->sessionStart, $options->sessionEnd]
                    : [$options->eventStart, $options->eventEnd];

                $start = $date->copy()->setTimeFromTimeString($from);
                $end = $date->copy()->setTimeFromTimeString($to);
                if ($end <= $start) {
                    $end->addDay(); // es. 21:30 -> 00:00 = mezzanotte del giorno dopo
                }
            }

            $type = $cell->kind === 'table' ? 'session' : $row->action;
            $row->attributes = [
                'type' => $type,
                'group_id' => null,
                'title' => $cell->kind === 'table' ? $row->groupName : $row->text,
                'description' => $row->system ? "Sistema: {$row->system}" : null,
                'start_time' => $start,
                'end_time' => $end,
            ];

            // --- Regole di calendario --------------------------------------------------------------------
            if (! $row->isIgnored() && ! $row->errors && $start && $end) {
                $key = $type.'|'.mb_strtolower($row->attributes['title']).'|'.$start->format('Y-m-d H:i');

                if (isset($seen[$key]) || Event::where('type', $type)->where('title', $row->attributes['title'])->where('start_time', $start)->exists()) {
                    $row->errors[] = 'Evento già presente (stesso tipo, titolo e orario di inizio).';
                } else {
                    try {
                        $this->scheduler->validate(['type' => $type, 'start_time' => $start, 'end_time' => $end], null, $extra);
                        $extra[] = ['type' => $type, 'start' => $start, 'end' => $end];
                        $seen[$key] = true;
                    } catch (SchedulingException $e) {
                        $row->errors[] = $e->getMessage();
                    }
                }
            }

            // Le righe ignorate non producono errori
            if ($row->isIgnored()) {
                $row->errors = [];
            }

            $rows->push($row);
        }

        return new ImportPreview($rows, $groupChoices, $bannerChoices);
    }
}
