import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import itLocale from '@fullcalendar/core/locales/it';

const el = document.getElementById('calendar');

if (el) {
    const NARROW = 640;
    const isNarrow = () => window.innerWidth < NARROW;

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        locale: itLocale,
        firstDay: 1,
        initialView: isNarrow() ? 'listWeek' : 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek',
        },
        height: 'auto',
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        events: {
            url: el.dataset.eventsUrl,
            extraParams: () => ({ filter: el.dataset.filter || 'all' }),
        },
        eventClick: (info) => {
            info.jsEvent.preventDefault();
            openEventModal(info.event);
        },
        datesSet: (info) => loadAvailability(info),
        windowResize: () => {
            const view = calendar.view.type;
            if (isNarrow() && view !== 'listWeek') calendar.changeView('listWeek');
            if (!isNarrow() && view === 'listWeek') calendar.changeView('dayGridMonth');
        },
    });

    calendar.render();

    // ---- Capienza / assemblee per giorno ----
    let availabilityToken = 0;

    async function loadAvailability(info) {
        const token = ++availabilityToken;
        const start = info.start.toISOString().slice(0, 10);
        // `end` di FullCalendar è esclusivo
        const endDate = new Date(info.end.getTime() - 24 * 3600 * 1000);
        const end = endDate.toISOString().slice(0, 10);

        // La vista elenco non ha celle giornaliere
        if (info.view.type === 'listWeek') return;

        try {
            const res = await fetch(`${el.dataset.availabilityUrl}?start=${start}&end=${end}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok || token !== availabilityToken) return;
            const days = await res.json();
            applyAvailability(days);
        } catch (e) {
            // la disponibilità è solo un'informazione in più: in caso di errore il calendario resta utilizzabile
        }
    }

    function applyAvailability(days) {
        el.querySelectorAll('.fc-day-capacity').forEach((n) => n.remove());
        el.querySelectorAll('.has-assembly').forEach((n) => n.classList.remove('has-assembly'));

        days.forEach((d) => {
            const cell = el.querySelector(`[data-date="${d.date}"].fc-daygrid-day, [data-date="${d.date}"].fc-timegrid-col`);
            if (!cell) return;

            if (d.has_assembly) cell.classList.add('has-assembly');

            if (d.capacity > 0) {
                const frame = cell.querySelector('.fc-daygrid-day-frame') || cell;
                const badge = document.createElement('span');
                badge.className = 'fc-day-capacity';
                if (d.over_capacity) badge.classList.add('is-over');
                else if (d.free === 0) badge.classList.add('is-full');

                const full = d.over_capacity ? 'sovraffollato' : d.free === 0 ? 'pieno' : '';
                badge.textContent = `${d.occupied}/${d.capacity}` + (full ? ` ${full === 'pieno' ? 'Pieno' : '!'}` : '');
                const label = `${d.occupied} posti occupati su ${d.capacity}` + (full ? ` (${full})` : '');
                badge.title = label;
                badge.setAttribute('aria-label', label);
                frame.appendChild(badge);
            }
        });
    }

    // ---- Modale di dettaglio (solo textContent: mai innerHTML con dati utente) ----
    const modal = document.getElementById('eventModal');

    function openEventModal(ev) {
        if (!modal) return;
        const p = ev.extendedProps;
        const fmt = new Intl.DateTimeFormat('it-IT', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
        const tfmt = new Intl.DateTimeFormat('it-IT', { hour: '2-digit', minute: '2-digit', hour12: false });

        document.getElementById('eventModalBadge').textContent = p.typeLabel + (p.swapPending ? ' · 🔁 scambio richiesto' : '');
        document.getElementById('eventModalTitle').textContent = ev.title;
        document.getElementById('eventModalWhen').textContent =
            fmt.format(ev.start) + ' · ' + tfmt.format(ev.start) + (ev.end ? ' – ' + tfmt.format(ev.end) : '');
        document.getElementById('eventModalGroup').textContent = p.group ? '👥 ' + p.group : '';
        document.getElementById('eventModalDesc').textContent = p.description || '';

        const details = document.getElementById('eventModalDetails');
        const edit = document.getElementById('eventModalEdit');
        details.classList.toggle('hidden', !p.url);
        if (p.url) details.href = p.url;
        edit.classList.toggle('hidden', !p.editUrl);
        if (p.editUrl) edit.href = p.editUrl;

        modal.classList.remove('hidden');
    }

    function closeEventModal() {
        if (modal) modal.classList.add('hidden');
    }

    if (modal) {
        document.getElementById('eventModalClose').addEventListener('click', closeEventModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeEventModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeEventModal();
        });
    }
}
