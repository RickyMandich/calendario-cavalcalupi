<?php

namespace App\Services\CalendarImport;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Legge il foglio mensile dell'associazione (vedi implementationPlan-importazioneCalendarioFoglioMensile.md).
 * Sola lettura: le formule non vengono mai valutate.
 */
class CalendarImportParser
{
    public const MAX_CELLS = 600;

    /** Una banda è una cella unita su almeno questo numero di colonne. */
    public const BANNER_MIN_WIDTH = 3;

    private const MONTHS = [
        'gennaio' => 1, 'febbraio' => 2, 'marzo' => 3, 'aprile' => 4, 'maggio' => 5, 'giugno' => 6,
        'luglio' => 7, 'agosto' => 8, 'settembre' => 9, 'ottobre' => 10, 'novembre' => 11, 'dicembre' => 12,
    ];

    private const WEEKDAYS = [
        'lunedi' => 1, 'martedi' => 2, 'mercoledi' => 3, 'giovedi' => 4, 'venerdi' => 5, 'sabato' => 6, 'domenica' => 7,
    ];

    /**
     * @return Collection<int, ParsedCell>
     *
     * @throws ImportException
     */
    public function parse(string $path): Collection
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            // Le celle unite si leggono solo con readDataOnly = false
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
        } catch (Throwable $e) {
            throw new ImportException('File non leggibile: usa un file Excel (.xlsx) valido.', 0, $e);
        }

        $out = collect();
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $this->parseSheet($sheet, $out);

            if ($out->count() > self::MAX_CELLS) {
                throw new ImportException('Il file supera il limite di '.self::MAX_CELLS.' celle importabili.');
            }
        }

        if ($out->isEmpty()) {
            throw new ImportException('Nessuna serata riconosciuta: servono righe con etichette come «Giovedì 3» sotto il titolo del mese.');
        }

        return $out;
    }

    private function parseSheet(Worksheet $sheet, Collection $out): void
    {
        $table = $sheet->toArray(null, false, false, false);

        $merges = [];
        foreach ($sheet->getMergeCells() as $range) {
            [[$c1, $r1], [$c2, $r2]] = Coordinate::rangeBoundaries($range);
            $merges[] = [(int) $c1, (int) $r1, (int) $c2, (int) $r2];
        }

        $month = $this->monthFrom($sheet->getTitle());
        $lastDay = null;

        foreach ($table as $i => $cells) {
            $rowNumber = $i + 1;
            $label = $this->dayLabel($cells);

            if (! $label) {
                foreach ($cells as $value) {
                    $m = $this->monthFrom($this->clean($value));
                    if ($m) {
                        $month = $m;
                        $lastDay = null;
                        break;
                    }
                }

                continue;
            }

            if ($month === null) {
                throw new ImportException("Foglio «{$sheet->getTitle()}», riga {$rowNumber}: manca il titolo del mese sopra «{$label['text']}».");
            }

            // Il "Venerdì 1" in coda a dicembre è di gennaio: il giorno "torna indietro"
            if ($lastDay !== null && $label['day'] < $lastDay && $lastDay >= 24 && $label['day'] <= 7) {
                $month = $month % 12 + 1;
            }
            $lastDay = $label['day'];

            foreach ($cells as $col => $value) {
                if ($col <= $label['col']) {
                    continue;
                }

                $text = $this->clean($value);
                if ($text === '') {
                    continue;
                }

                $merge = $this->mergeAt($merges, $col + 1, $rowNumber);
                $width = $merge ? $merge[2] - $merge[0] + 1 : 1;

                if ($width < self::BANNER_MIN_WIDTH) {
                    $out->push(new ParsedCell('table', $sheet->getTitle(), $rowNumber, $month, $label['day'], $label['weekday'], $label['text'], $text));

                    continue;
                }

                // Banda: vale per tutte le serate che la cella unita copre in verticale
                foreach (range($merge[1], $merge[3]) as $r) {
                    $covered = $r === $rowNumber ? $label : $this->dayLabel($table[$r - 1] ?? []);
                    if (! $covered) {
                        continue;
                    }
                    $coveredMonth = $covered['day'] < $label['day'] ? $month % 12 + 1 : $month;
                    $out->push(new ParsedCell('banner', $sheet->getTitle(), $r, $coveredMonth, $covered['day'], $covered['weekday'], $covered['text'], $text));
                }
            }
        }
    }

    /** @return array{col:int, weekday:int, day:int, text:string}|null */
    private function dayLabel(array $cells): ?array
    {
        foreach ($cells as $col => $value) {
            $text = $this->clean($value);
            if ($text === '') {
                continue;
            }

            if (preg_match('/^([\p{L}]+)\s*(\d{1,2})$/u', $text, $m)) {
                $name = $this->fold($m[1]);
                if (isset(self::WEEKDAYS[$name])) {
                    return ['col' => $col, 'weekday' => self::WEEKDAYS[$name], 'day' => (int) $m[2], 'text' => $text];
                }
            }
        }

        return null;
    }

    private function monthFrom(string $text): ?int
    {
        if (preg_match('/^([\p{L}]+)(\s+\d{4})?$/u', trim($text), $m)) {
            return self::MONTHS[$this->fold($m[1])] ?? null;
        }

        return null;
    }

    /** minuscolo e senza accenti sulle vocali (giovedì -> giovedi) */
    private function fold(string $s): string
    {
        return strtr(mb_strtolower($s), ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
    }

    private function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = str_replace("\u{00A0}", ' ', (string) $value);

        return trim($text);
    }

    /** @return array{0:int,1:int,2:int,3:int}|null [colIniziale, rigaIniziale, colFinale, rigaFinale] */
    private function mergeAt(array $merges, int $col, int $row): ?array
    {
        foreach ($merges as $m) {
            if ($col >= $m[0] && $col <= $m[2] && $row >= $m[1] && $row <= $m[3]) {
                return $m;
            }
        }

        return null;
    }
}
