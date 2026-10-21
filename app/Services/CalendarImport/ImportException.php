<?php

namespace App\Services\CalendarImport;

use RuntimeException;

/** Errore globale del file (intestazioni mancanti, troppe righe, file illeggibile). */
class ImportException extends RuntimeException
{
}
