<?php

namespace App\Services\CalendarImport;

use Illuminate\Support\Collection;

class ImportPreview
{
    /**
     * @param  Collection<int, ImportRow>  $rows
     * @param  array<string, array{key:string,name:string,count:int,selected:string,inactive:bool}>  $groups  un elemento per nome distinto
     * @param  array<string, array{key:string,text:string,count:int,action:string}>  $banners  un elemento per testo distinto
     */
    public function __construct(
        public Collection $rows,
        public array $groups = [],
        public array $banners = [],
    ) {
    }

    /** Righe che verrebbero importate (escluse quelle ignorate). */
    public function activeRows(): Collection
    {
        return $this->rows->reject(fn (ImportRow $r) => $r->isIgnored());
    }

    public function validCount(): int
    {
        return $this->activeRows()->filter(fn (ImportRow $r) => $r->isValid())->count();
    }

    public function errorCount(): int
    {
        return $this->activeRows()->count() - $this->validCount();
    }

    /** Nomi di gruppo per cui l'utente non ha ancora scelto cosa fare. */
    public function unresolvedGroups(): array
    {
        return array_filter($this->groups, fn (array $g) => $g['selected'] === '');
    }

    /** Importabile solo con almeno una riga attiva e nessun errore (tutto o niente). */
    public function canCommit(): bool
    {
        return $this->activeRows()->isNotEmpty() && $this->errorCount() === 0;
    }
}
