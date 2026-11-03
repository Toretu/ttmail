<?php
declare(strict_types=1);

namespace Mailings\Import;

use Mailings\Http\HttpException;

/** Reads the first sheet of a CSV or XLSX file into rows of strings. Row index 0 is the header row. */
final class SpreadsheetReader
{
    private const MAX_XML_BYTES = 25_000_000;
    private const MAX_SHEET_ROWS = 100_000;
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @return list<list<string>> */
    public static function read(string $path, string $extension): array
    {
        return match ($extension) {
            'csv' => self::readCsv($path),
            'xlsx' => self::readXlsx($path),
            default => throw new HttpException(415, 'Only .csv and .xlsx files are supported'),
        };
    }

    /** @return list<list<string>> */
    private static function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new HttpException(400, 'The uploaded file could not be read');
        }
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        // Excel on Windows often saves CSV as Windows-1252 rather than UTF-8.
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = ',';
        $best = -1;
        foreach ([',', ';', "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $best) {
                $best = $count;
                $delimiter = $candidate;
            }
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];
        while (($record = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = $record === [null] ? [] : array_map(static fn ($v): string => (string) $v, $record);
        }
        fclose($handle);
        return $rows;
    }

    /** @return list<list<string>> */
    private static function readXlsx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP zip extension is required to read .xlsx files');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new HttpException(422, 'The file is not a valid .xlsx workbook');
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheet = self::xml($zip, self::firstSheetPath($zip));

            $byRow = [];
            $maxRow = 0;
            foreach ($sheet->sheetData->row as $row) {
                $number = (int) $row['r'];
                if ($number < 1 || $number > self::MAX_SHEET_ROWS) {
                    throw new HttpException(422, 'The worksheet has too many rows');
                }
                $cells = [];
                $maxCol = -1;
                foreach ($row->c as $cell) {
                    $col = self::columnIndex((string) $cell['r']);
                    $cells[$col] = match ((string) $cell['t']) {
                        's' => $shared[(int) $cell->v] ?? '',
                        'inlineStr' => self::richText($cell->is),
                        'b' => ((string) $cell->v === '1') ? 'TRUE' : 'FALSE',
                        default => (string) $cell->v,
                    };
                    $maxCol = max($maxCol, $col);
                }
                $values = [];
                for ($i = 0; $i <= $maxCol; $i++) {
                    $values[] = $cells[$i] ?? '';
                }
                $byRow[$number] = $values;
                $maxRow = max($maxRow, $number);
            }

            $rows = [];
            for ($n = 1; $n <= $maxRow; $n++) {
                $rows[] = $byRow[$n] ?? [];
            }
            return $rows;
        } finally {
            $zip->close();
        }
    }

    private static function xml(\ZipArchive $zip, string $name): \SimpleXMLElement
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            throw new HttpException(422, "The workbook is missing {$name}");
        }
        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new HttpException(422, 'The workbook is too large');
        }
        $data = $zip->getFromName($name);
        $xml = $data === false ? false : simplexml_load_string($data, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            throw new HttpException(422, "The workbook part {$name} is not valid XML");
        }
        return $xml;
    }

    private static function firstSheetPath(\ZipArchive $zip): string
    {
        $workbook = self::xml($zip, 'xl/workbook.xml');
        $first = $workbook->sheets->sheet[0] ?? null;
        if ($first === null) {
            throw new HttpException(422, 'The workbook contains no worksheets');
        }
        $relationshipId = (string) $first->attributes(self::NS_REL)['id'];
        $rels = self::xml($zip, 'xl/_rels/workbook.xml.rels');
        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $relationshipId) {
                $target = (string) $rel['Target'];
                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            }
        }
        throw new HttpException(422, 'The first worksheet could not be located');
    }

    /** @return list<string> */
    private static function sharedStrings(\ZipArchive $zip): array
    {
        if ($zip->statName('xl/sharedStrings.xml') === false) {
            return [];
        }
        $strings = [];
        foreach (self::xml($zip, 'xl/sharedStrings.xml')->si as $si) {
            $strings[] = self::richText($si);
        }
        return $strings;
    }

    private static function richText(\SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }
        return $text;
    }

    private static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '';
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }
        return max(0, $index - 1);
    }
}
