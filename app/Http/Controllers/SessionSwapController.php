<?php

namespace App\Http\Controllers;

use App\Exceptions\SchedulingException;
use App\Models\Event;
use App\Models\SessionSwapRequest;
use App\Services\SessionSwapService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionSwapController extends Controller
{
    public function __construct(private SessionSwapService $service)
    {
    }

    /**
     * Elenco delle richieste pendenti (visibile a ogni utente autenticato).
     */
    public function index(): View
    {
        $requests = SessionSwapRequest::pending()
            ->with(['event.group', 'group', 'requester'])
            ->orderBy('target_date')
            ->get();

        return view('swap-requests.index', [
            'requests' => $requests,
            'service' => $this->service,
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'target_date' => ['required', 'date'],
        ]);

        try {
            $result = $this->service->request(
                $event,
                Carbon::parse($validated['target_date'], config('app.timezone')),
                $request->user()
            );
        } catch (SchedulingException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        if ($result['status'] === 'conflict') {
            return back()->withErrors(['target_date' => $result['message']]);
        }

        return redirect()->route('events.show', $event)->with('success', $result['message']);
    }

    public function destroy(Request $request, SessionSwapRequest $swapRequest): RedirectResponse
    {
        try {
            $this->service->cancel($swapRequest, $request->user());
        } catch (AuthorizationException) {
            abort(403);
        } catch (SchedulingException $e) {
            return back()->withErrors(['target_date' => $e->getMessage()]);
        }

        return back()->with('success', 'Richiesta di scambio annullata.');
    }
}
