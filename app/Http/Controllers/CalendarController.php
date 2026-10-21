<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Group;
use App\Services\CalendarScheduler;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CalendarController extends Controller
{
    private const TYPE_LABELS = [
        'session' => 'Sessione',
        'assembly' => 'Assemblea',
        'association_event' => 'Evento associativo',
    ];

    private const TYPE_CLASSES = [
        'session' => 'fc-evt-session',
        'assembly' => 'fc-evt-assembly',
        'association_event' => 'fc-evt-association',
    ];

    /**
     * Pagina pubblica del calendario. Vista predefinita: calendario interattivo; `?view=list`: elenco a card.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $filter = $request->query('filter', 'all') === 'my-groups' ? 'my-groups' : 'all';
        $view = $request->query('view') === 'list' ? 'list' : 'calendar';

        $events = collect();
        if ($view === 'list') {
            $query = Event::with(['group', 'creator', 'swapRequests'])->orderBy('start_time');
            $this->applyFilter($query, $filter);
            $events = $query->get();
        }

        return view('calendar.index', [
            'events' => $events,
            'filter' => $filter,
            'view' => $view,
            'user' => $user,
            'activeGroups' => Group::active()->orderBy('name')->get(),
            'userActiveGroups' => $user ? $user->groups()->where('is_active', true)->orderBy('name')->get() : collect(),
            'canCreateSessions' => $user && ($user->isStaff() || $user->managedGroups()->exists()),
        ]);
    }

    /**
     * Feed JSON per FullCalendar (pubblico: solo dati già visibili nella pagina pubblica).
     */
    public function events(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'filter' => ['nullable', 'in:all,my-groups'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tz = config('app.timezone');
        $start = Carbon::parse($request->query('start'), $tz);
        $end = Carbon::parse($request->query('end'), $tz);

        if ($end < $start || $start->diffInDays($end) > 100) {
            return response()->json(['errors' => ['end' => ['Intervallo non valido (massimo 100 giorni).']]], 422);
        }

        $user = Auth::user();

        $query = Event::with(['group', 'swapRequests'])
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->orderBy('start_time');
        $this->applyFilter($query, $request->query('filter', 'all'));

        $items = $query->get()->map(function (Event $e) use ($user) {
            $canEdit = false;
            if ($user) {
                $canEdit = $e->type === 'session'
                    ? $user->can('update-session', $e)
                    : $user->can('manage-assemblies');
            }

            return [
                'id' => $e->id,
                // Se il titolo coincide con il nome del gruppo (sessioni importate) non si ripete
                'title' => $e->type === 'session' && $e->group && $e->group->name !== $e->title
                    ? $e->group->name.' · '.$e->title
                    : $e->title,
                'start' => $e->start_time->toIso8601String(),
                'end' => $e->end_time->toIso8601String(),
                'classNames' => [self::TYPE_CLASSES[$e->type] ?? 'fc-evt-session'],
                'extendedProps' => [
                    'type' => $e->type,
                    'typeLabel' => self::TYPE_LABELS[$e->type] ?? $e->type,
                    'group' => $e->group?->name,
                    'description' => $e->description,
                    'url' => $user ? route('events.show', $e) : null,
                    'editUrl' => $canEdit ? route('events.edit', $e) : null,
                    'swapPending' => $e->swapRequests->isNotEmpty(),
                ],
            ];
        });

        return response()->json($items->values());
    }

    /**
     * Disponibilità per giorno (pubblica): ?date=YYYY-MM-DD oppure ?start=...&end=... (max 62 giorni).
     */
    public function availability(Request $request, CalendarScheduler $scheduler): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['required_without_all:start,end', 'nullable', 'date'],
            'start' => ['required_without:date', 'nullable', 'date'],
            'end' => ['required_without:date', 'nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tz = config('app.timezone');
        if ($request->filled('date')) {
            $start = $end = Carbon::parse($request->query('date'), $tz);
        } else {
            $start = Carbon::parse($request->query('start'), $tz);
            $end = Carbon::parse($request->query('end'), $tz);
        }

        if ($end < $start || $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) > 62) {
            return response()->json(['errors' => ['end' => ['Intervallo non valido (massimo 62 giorni).']]], 422);
        }

        return response()->json($scheduler->dayAvailability($start, $end));
    }

    /**
     * Filtro "i miei gruppi": sessioni dei propri gruppi attivi + eventi istituzionali.
     * La condizione è raggruppata in una closure per non interferire con altri where.
     */
    private function applyFilter($query, string $filter): void
    {
        $user = Auth::user();
        if ($filter !== 'my-groups' || ! $user) {
            return;
        }

        $groupIds = $user->groups()->where('groups.is_active', true)->pluck('groups.id')->all();

        $query->where(function ($q) use ($groupIds) {
            $q->where(fn ($s) => $s->where('type', 'session')->whereIn('group_id', $groupIds))
                ->orWhereIn('type', ['assembly', 'association_event']);
        });
    }
}
