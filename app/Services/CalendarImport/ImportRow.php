<?php

namespace App\Services\CalendarImport;

/**
 * Una riga dell'anteprima: un tavolo (sessione) o una banda (evento) di una serata.
 */
class ImportRow
{
    public const ACTION_IGNORE = 'ignore';

    /**
     * @param  array<string, mixed>  $attributes  attributi dell'evento da creare (group_id escluso: si risolve alla conferma)
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public int $number,
        public string $sheet,
        public string $kind,                // 'table' | 'banner'
        public string $label,               // "Giovedì 3"
        public string $text,                // testo su una riga, per la visualizzazione
        public ?string $date = null,        // Y-m-d
        public ?string $groupKey = null,    // solo tavoli
        public ?string $groupName = null,
        public ?string $system = null,
        public ?string $bannerKey = null,   // solo bande
        public string $action = '',         // 'session' | 'association_event' | 'assembly' | 'ignore'
        public array $attributes = [],
        public array $errors = [],
    ) {
    }

    public function isIgnored(): bool
    {
        return $this->action === self::ACTION_IGNORE;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
