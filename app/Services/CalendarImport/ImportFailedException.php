<?php

namespace App\Services\CalendarImport;

use RuntimeException;

/** L'importazione è stata rifiutata al commit perché almeno una riga non è (più) valida. */
class ImportFailedException extends RuntimeException
{
    public function __construct(public readonly ImportPreview $preview)
    {
        parent::__construct("Importazione annullata: {$preview->errorCount()} righe con errori. Nessun evento è stato creato.");
    }
}
