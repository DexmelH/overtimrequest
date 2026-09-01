<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Streams a CSV download and exits. Application detects this return type
 * instead of JSON-encoding the payload.
 */
final class CsvResponse
{
    /** @param list<string> $headers */
    /** @param list<list<string|int|float|null>> $rows */
    public function __construct(
        private string $filename,
        private array $headers,
        private array $rows
    ) {
    }

    public function send(): void
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $this->filename) ?: 'report.csv';
        if (!str_ends_with(strtolower($safeName), '.csv')) {
            $safeName .= '.csv';
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new \RuntimeException('Unable to open output stream for CSV.');
        }

        // Excel-friendly UTF-8 BOM
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $this->headers);
        foreach ($this->rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
    }
}
