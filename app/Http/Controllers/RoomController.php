<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoomController extends Controller
{
    private const WEEKDAYS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'];

    public function index(): View
    {
        Gate::authorize('manage-rooms');

        $rooms = Room::with('weekdays')->orderBy('name')->get();

        // Capienza per giorno della settimana (solo stanze attive)
        $capacity = array_fill(1, 7, 0);
        foreach ($rooms->where('is_active', true) as $room) {
            foreach ($room->weekdays as $w) {
                $capacity[$w->weekday] += $room->capacity;
            }
        }

        return view('admin.rooms.index', [
            'rooms' => $rooms,
            'weekdays' => self::WEEKDAYS,
            'capacity' => $capacity,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-rooms');

        $data = $this->validated($request);
        $room = Room::create([
            'name' => $data['name'],
            'capacity' => $data['capacity'],
            'is_active' => $request->boolean('is_active', true),
        ]);
        $room->syncWeekdays($data['weekdays'] ?? []);

        return redirect()->route('admin.rooms.index')->with('success', "Stanza '{$room->name}' creata.");
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        Gate::authorize('manage-rooms');

        $data = $this->validated($request);
        $room->update([
            'name' => $data['name'],
            'capacity' => $data['capacity'],
            'is_active' => $request->boolean('is_active'),
        ]);
        $room->syncWeekdays($data['weekdays'] ?? []);

        return redirect()->route('admin.rooms.index')->with('success', "Stanza '{$room->name}' aggiornata.");
    }

    public function destroy(Room $room): RedirectResponse
    {
        Gate::authorize('manage-rooms');

        $name = $room->name;
        $room->delete();

        return redirect()->route('admin.rooms.index')
            ->with('success', "Stanza '{$name}' eliminata: la capienza dei suoi giorni è diminuita. Per interventi temporanei è meglio disattivarla.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
        ]);
    }
}
