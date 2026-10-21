@extends('layouts.app', ['title' => 'Richieste di Scambio'])

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-3xl font-extrabold text-brand-950 tracking-tight">Richieste di Scambio</h1>
        <p class="text-xs text-gray-500 mt-1 max-w-2xl">
            Due gruppi che si sono accordati (ad esempio su Telegram) inviano ciascuno una richiesta dalla pagina della propria sessione.
            Quando le due richieste combaciano, i giorni si scambiano in automatico; orari e durate restano quelli di ciascuna sessione.
        </p>
    </div>

    @if($requests->isEmpty())
        <div class="panel p-8 rounded-3xl text-center text-sm text-gray-500">Nessuna richiesta in attesa.</div>
    @else
        <div class="panel rounded-3xl overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-brand-100 text-gray-500 uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-3">Gruppo</th>
                        <th class="py-3 px-3">Sessione</th>
                        <th class="py-3 px-3">Da</th>
                        <th class="py-3 px-3">A</th>
                        <th class="py-3 px-3">Richiesta da</th>
                        <th class="py-3 px-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-100">
                    @foreach($requests as $r)
                        <tr>
                            <td class="py-3 px-3 font-medium">{{ $r->group->name ?? '-' }}</td>
                            <td class="py-3 px-3">
                                <a class="text-brand-700 hover:underline" href="{{ route('events.show', $r->event_id) }}">{{ $r->event->title ?? '-' }}</a>
                            </td>
                            <td class="py-3 px-3 font-mono">{{ $r->from_date->format('d/m/Y') }}</td>
                            <td class="py-3 px-3 font-mono">{{ $r->target_date->format('d/m/Y') }}</td>
                            <td class="py-3 px-3">{{ $r->requester->name ?? '-' }}</td>
                            <td class="py-3 px-3 text-right">
                                @if($service->canCancel($r, auth()->user()))
                                    <form method="POST" action="{{ route('swap-requests.destroy', $r) }}" onsubmit="return confirm('Annullare la richiesta?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-secondary">Annulla</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
