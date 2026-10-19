# Implementation Plan - Allineamento Docker Compose a unlimiteddb

## 1. Obiettivo
Usare `unlimiteddb/docker-compose.yml` (prod) e `unlimiteddb/docker-compose.dev.yml` (dev) come source of truth e allineare l'infra Docker di questo progetto: stesso schema di servizi, volumi, immagini e consegna degli asset, tenendo i valori specifici del progetto (nomi, DB, subnet, dominio).

## 2. Differenze rilevate (calendario -> unlimiteddb)
| Aspetto | calendario (prima) | unlimiteddb (target) |
|---|---|---|
| Immagine | `build: .` su app **e** worker (due build) | `build` solo su `app`, tag `:prod`/`:dev` condiviso dal worker |
| Asset in nginx | volume montato su `/var/www/html/build-assets` + `location ^~ /build/` con alias | volume `build_assets` montato su `public/build:ro`, nessuna location dedicata |
| Seed asset nell'immagine | `/opt/build-seed` (copiato solo all'avvio) | `/opt/build-assets` + copia anche in `public/build`; entrypoint con `cp -a` |
| `/favicon.ico` in nginx | alias su build-assets | `try_files /build/icons/favicon.ico =404` |
| `worker.conf` | solo `queue:work` | `queue:work` + `schedule:work` |
| `.dockerignore` | `.env .git node_modules vendor` | + `public/build`, `public/hot` |
| Compose dev | assente | `docker-compose.dev.yml` (bind mount `public/build`, porta host, DB esposto, `DB_PASSWORD` da `.env`) |

Nota importante: il comando `app:deactivate-groups` e' schedulato (15 agosto, `routes/console.php`) ma in produzione nessun processo eseguiva lo scheduler. Con `schedule:work` nel worker il job parte davvero.

## 3. Valori specifici del progetto (non copiati)
- Nomi container/immagine: `calendario-cavalcalupi_*`, `calendario-cavalcalupi:prod|dev`.
- DB `calendario_cavalcalupi`, utente `calendario_cavalcalupi`.
- Subnet prod `172.24.0.0/24` (invariata); dev `172.25.0.0/24` (quella di unlimiteddb dev, `172.30.0.0/24`, collide se i due progetti girano insieme).
- Dev: nginx su `localhost:8000`, MariaDB su `localhost:3307` (3306 e' gia' usata da unlimiteddb dev / MariaDB locale).
- Dominio Traefik `calendario-cavalcalupi.mandich.dev`.
- Non portati perche' specifici di unlimiteddb: `client_max_body_size`, location `/storage/` e mount `storage/app/public` (upload carte).

## 4. Passi
1. `docker-compose.yml`: allineare a prod di unlimiteddb.
2. `docker-compose.dev.yml` + `docker/mysql/init.dev.sql`: nuovi.
3. `Dockerfile` e `docker/entrypoint.sh`: `/opt/build-seed` -> `/opt/build-assets`, copia anche in `public/build`.
4. `docker/nginx/default.conf`: rimuovere alias `/build/`, favicon con `try_files`.
5. `docker/supervisor/worker.conf`: aggiungere `laravel-scheduler`.
6. `.dockerignore`: aggiungere `public/build`, `public/hot`.
7. `README.md` (sezione Docker, correzione percorsi icone) e `todo.md`.
8. Rinominare il piano in `implementationPlan-V-allineamentoDockerComposeAUnlimitedDB.md`.
