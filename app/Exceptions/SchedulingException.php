<?php

namespace App\Exceptions;

use DomainException;

/**
 * Violazione di una regola del calendario (capienza, assemblee, passato...).
 * I controller la convertono in un errore di validazione sul campo indicato.
 */
class SchedulingException extends DomainException
{
    public function __construct(
        string $message,
        public readonly string $field = 'start_time',
        public readonly string $reason = '',
    ) {
        parent::__construct($message);
    }
}
