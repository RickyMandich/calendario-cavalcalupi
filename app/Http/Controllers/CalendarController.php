<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Display the calendar and list of events.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $filter = $request->query('filter', 'all');

        $activeGroupIds = $user ? $user->groups()->where('is_active', true)->pluck('groups.id')->toArray() : [];

        $query = Event::with(['group', 'creator'])->orderBy('start_time');

        if ($filter === 'my-groups' && $user) {
            $query->where(function ($q) use ($activeGroupIds) {
                $q->where('type', 'session')->whereIn('group_id', $activeGroupIds);
            })->orWhereIn('type', ['assembly', 'association_event']);
        }

        $events = $query->get();

        $activeGroups = Group::active()->orderBy('name')->get();
        $userActiveGroups = $user ? $user->groups()->where('is_active', true)->orderBy('name')->get() : collect();

        return view('calendar.index', [
            'events' => $events,
            'filter' => $filter,
            'user' => $user,
            'activeGroups' => $activeGroups,
            'userActiveGroups' => $userActiveGroups,
        ]);
    }
}
