# Implementation Plan - Palette verde/bianco e icone del sito

## 1. Obiettivo
1. Sostituire la palette attuale (tema scuro slate + indigo/violet) con una palette **verde e bianco** (tema chiaro).
2. Replicare nell'infra Docker di questo sito la generazione delle icone (favicon, apple-touch, android-chrome, `site.webmanifest`) a partire da un unico SVG sorgente, come già fatto in `unlimiteddb` (stage `icon-builder` del Dockerfile + `docker/icons/generate-icons.sh`).
3. Aggiornare la view `layouts.app` per usare le icone generate.

## 2. Analisi dello stato attuale
- `docker-compose.yml` di `unlimiteddb`: la generazione delle icone **non vive nel compose** ma nel `Dockerfile` (stage `icon-builder`). Le icone viaggiano con gli asset Vite: `/opt/build-assets/icons` -> `public/build/icons`, rinfrescate dall'entrypoint a ogni avvio.
- In questo progetto l'entrypoint copia già `/opt/build-seed` in `public/build` (volume `build_assets`) e nginx serve `/build/` da quel volume (`alias /var/www/html/build-assets/`). Mettendo le icone in `/opt/build-seed/icons` arrivano quindi su `/build/icons/*` **senza modificare `docker-compose.yml`**.
- Non esiste ancora un SVG sorgente: va creato `public/icon.svg` (lupo bianco su quadrato verde, sostituibile con il logo definitivo senza toccare altro).
- Il layout usa classi `slate-*`/`indigo-*`/`violet-*` ripetute in ogni vista e una classe `glass-panel` definita inline nel layout.

## 3. Palette
Verde "brand" definito una sola volta in `resources/css/app.css` (`@theme`, scala 50-950 = verde Tailwind `green`), usato come `bg-brand-600`, `text-brand-800`, ecc.

| Uso | Colore |
|---|---|
| Sfondo pagina / pannelli | bianco |
| Sfondo di sezioni secondarie | `brand-50` |
| Bordi | `brand-100` / `brand-200` |
| Azione primaria | `brand-600` (hover `brand-700`) |
| Titoli | `brand-950` |
| Testo / testo secondario | `gray-900` / `gray-500` |
| Footer, theme-color, sfondo icona | `brand-800` / `brand-700` |
| Tipi evento | sessione = verde; assemblea = rose; evento associativo = amber (restano colori semantici distinti) |
| Ruoli | Admin = red, CD = amber, Membro = verde |

Per evitare di ripetere le stesse classi in ogni vista, in `app.css` (`@layer components`) si definiscono: `.panel`, `.field`, `.field-label`, `.btn-primary`, `.btn-secondary`. Cambiare palette in futuro = cambiare `@theme`.

## 4. Passi
1. `resources/css/app.css`: aggiungere scala `brand` e componenti.
2. `public/icon.svg`: SVG sorgente (512x512, lupo bianco su quadrato verde `#15803d`).
3. `docker/icons/generate-icons.sh`: script di generazione (come in unlimiteddb, default adattati: nome "Calendario Cavalcalupi", theme/sfondo apple `#15803d`).
4. `Dockerfile`: nuovo stage `icon-builder` (debian + `librsvg2-bin` + `imagemagick`) e `COPY --from=icon-builder /out/icons /opt/build-seed/icons` nello stage finale.
5. `docker/nginx/default.conf`: `/favicon.ico` servito da `build-assets/icons/favicon.ico`.
6. `resources/views/layouts/favicons.blade.php`: partial con i `<link>` delle icone (+ SVG per dev locale senza Docker).
7. `resources/views/layouts/app.blade.php`: `@include('layouts.favicons')`, `theme-color`, tema chiaro, logo da `icon.svg`, footer verde.
8. Viste (`calendar/index`, `events/*`, `admin/groups/index`, `auth/*`): migrazione a palette verde/bianco e ai componenti di `app.css`. `welcome.blade.php` (template Laravel di default, non usato) non viene toccato.
9. `README.md` (palette, icone, struttura Docker) e `todo.md`.
10. Rinominare questo piano in `implementationPlan-V-paletteVerdeBiancoEIcone.md`.

## 5. Note
- `docker-compose.yml` invariato: nessun nuovo servizio/volume necessario.
- Per cambiare icona basta sostituire `public/icon.svg` e ricostruire l'immagine (`docker compose build`); lo stage viene invalidato solo se cambiano SVG o script.
- Verifica dopo il build: `/build/icons/favicon.ico`, `/build/icons/site.webmanifest` e `/icon.svg` rispondono 200.
