<?php

namespace App\Services\CalendarImport;

/**
 * Una cella significativa del foglio: un tavolo o una banda (festività/evento), già associata
 * alla sua serata (mese, giorno, giorno della settimana ISO).
 */
class ParsedCell
{
    public function __construct(
        public string $kind,      // 'table' | 'banner'
        public string $sheet,
        public int $row,          // numero di riga nel foglio
        public int $month,        // 1-12
        public int $day,
        public int $weekday,      // 1 = lunedì ... 7 = domenica
        public string $label,     // etichetta originale, es. "Giovedì 3"
        public string $text,      // testo della cella (con a capo originali)
    ) {
    }

    /** @return array<int, string> righe non vuote della cella */
    public function lines(): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $this->text) ?: []),
            fn ($l) => $l !== ''
        ));
    }
}
