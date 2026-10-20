<?php

namespace App\Exceptions;

use DomainException;

class SchedulingException extends DomainException
{
    public readonly string $field;

    public function __construct(string $message, string $field = 'start_time')
    {
        parent::__construct($message);
        $this->field = $field;
    }
}
?>
