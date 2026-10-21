{{-- Suggerimento di disponibilità: solo informativo, il server rifà tutti i controlli. --}}
<script>
    (function () {
        const box = document.getElementById('availabilityBox');
        const input = document.getElementById('start_time');
        if (!box || !input) return;

        const days = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];

        async function refresh() {
            const value = input.value;
            if (!value) { box.classList.add('hidden'); return; }
            const date = value.slice(0, 10);
            try {
                const res = await fetch(box.dataset.url + '?date=' + encodeURIComponent(date), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { box.classList.add('hidden'); return; }
                const data = await res.json();
                const d = data[0];
                if (!d) { box.classList.add('hidden'); return; }
                const dt = new Date(date + 'T12:00:00');
                const label = days[dt.getDay()] + ' ' + date.split('-').reverse().slice(0, 2).join('/');
                let text;
                if (d.capacity === 0) text = label + ': nessuna stanza in questo giorno (le sessioni non sono possibili).';
                else if (d.free === 0) text = label + ': giorno pieno (' + d.occupied + '/' + d.capacity + ' posti occupati).';
                else text = label + ': ' + d.occupied + '/' + d.capacity + ' posti occupati.';
                if (d.has_assembly) text += ' Assemblea in programma.';
                box.textContent = text;
                box.classList.remove('hidden');
            } catch (e) {
                box.classList.add('hidden');
            }
        }

        input.addEventListener('change', refresh);
        document.addEventListener('DOMContentLoaded', refresh);
        refresh();
    })();
</script>
