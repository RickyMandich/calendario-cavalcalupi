<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Response;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event as IcsEvent;

class IcsCalendarController extends Controller
{
    /**
     * Download customized ICS feed for a user.
     */
    public function __invoke(string $token): Response
    {
        $user = User::where('ics_token', $token)->first();

        if (!$user) {
            abort(404, 'Feed calendario non trovato.');
        }

        $activeGroupIds = $user->groups()
            ->where('is_active', true)
            ->pluck('groups.id')
            ->toArray();

        $events = Event::with('group')
            ->where(function ($query) use ($activeGroupIds) {
                $query->where('type', 'session')
                    ->whereIn('group_id', $activeGroupIds);
            })
            ->orWhereIn('type', ['assembly', 'association_event'])
            ->orderBy('start_time')
            ->get();

        $calendar = Calendar::create('Calendario Cavalcalupi - ' . $user->name);

        foreach ($events as $event) {
            $icsEvent = IcsEvent::create($event->title)
                ->uniqueIdentifier('cavalcalupi-event-' . $event->id)
                ->startsAt($event->start_time)
                ->endsAt($event->end_time);

            $descriptionParts = [];
            if ($event->description) {
                $descriptionParts[] = $event->description;
            }

            if ($event->type === 'session' && $event->group) {
                $descriptionParts[] = 'Gruppo: ' . $event->group->name;
            } else {
                $typeLabels = [
                    'assembly' => 'Tipologia: Assemblea',
                    'association_event' => 'Tipologia: Evento Associativo',
                ];
                if (isset($typeLabels[$event->type])) {
                    $descriptionParts[] = $typeLabels[$event->type];
                }
            }

            if (!empty($descriptionParts)) {
                $icsEvent->description(implode("\n\n", $descriptionParts));
            }

            $calendar->event($icsEvent);
        }

        return response($calendar->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cavalcalupi-calendar.ics"',
        ]);
    }
}
