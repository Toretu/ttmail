<?php
declare(strict_types=1);

namespace Mailings\Import;

use Mailings\Database;
use Mailings\Http\HttpException;
use Mailings\Schema;
use Mailings\Validator;

final class CompanyImporter
{
    public const MAX_ROWS = 2000;

    /** Accepted header names (English and Norwegian) per field, compared after normalisation. */
    private const ALIASES = [
        'name' => ['name', 'company', 'companyname', 'navn', 'firmanavn', 'firma', 'selskap', 'bedrift'],
        'org_number' => ['orgnumber', 'orgnr', 'organizationnumber', 'organisasjonsnummer', 'orgno'],
        'billing_address' => ['billingaddress', 'address', 'adresse', 'fakturaadresse', 'gateadresse'],
        'postal_code' => ['postalcode', 'postcode', 'zip', 'zipcode', 'postnr', 'postnummer'],
        'city' => ['city', 'by', 'sted', 'poststed'],
        'country' => ['country', 'land'],
        'website' => ['website', 'web', 'url', 'nettside', 'hjemmeside'],
        'category' => ['category', 'kategori', 'bransje', 'type'],
        'status' => ['status'],
        'notes' => ['notes', 'note', 'notater', 'merknad', 'kommentar'],
    ];

    /**
     * @param list<list<string>> $rows header row first
     * @return array{rows: list<array<string,mixed>>, ignored_columns: list<string>, summary: array<string,int>}
     */
    public function analyze(array $rows): array
    {
        if (count($rows) < 2) {
            throw new HttpException(422, 'The file must contain a header row and at least one data row');
        }
        if (count($rows) - 1 > self::MAX_ROWS) {
            throw new HttpException(422, 'The file has more than ' . self::MAX_ROWS . ' data rows; split it into smaller files');
        }

        [$columns, $ignored] = $this->mapHeader($rows[0]);
        if (!in_array('name', $columns, true)) {
            throw new HttpException(422, 'No company name column found. Accepted headers: ' . implode(', ', self::ALIASES['name']));
        }

        $rules = Schema::companyRules();
        $existing = $this->existingKeys();
        $seenInFile = [];
        $result = [];
        $summary = ['total' => 0, 'new' => 0, 'duplicate' => 0, 'invalid' => 0, 'imported' => 0];

        foreach (array_slice($rows, 1, null, true) as $index => $cells) {
            if (trim(implode('', $cells)) === '') {
                continue;
            }
            $summary['total']++;
            $line = $index + 1;

            $input = [];
            foreach ($columns as $position => $field) {
                if ($field !== null) {
                    $input[$field] = $cells[$position] ?? '';
                }
            }
            foreach (['status', 'country'] as $field) {
                if (isset($input[$field]) && $input[$field] !== '') {
                    $input[$field] = str_replace([' ', '-'], '_', strtolower(trim($input[$field])));
                }
            }

            try {
                $data = Validator::clean($input, $rules, false);
            } catch (HttpException $e) {
                $summary['invalid']++;
                $result[] = ['row' => $line, 'status' => 'invalid', 'messages' => $this->describe($e), 'data' => null];
                continue;
            }

            $keys = $this->keysFor($data);
            $duplicateOf = null;
            foreach ($keys as $key) {
                if (isset($existing[$key])) {
                    $duplicateOf = 'already exists in the database';
                } elseif (isset($seenInFile[$key])) {
                    $duplicateOf = "duplicate of row {$seenInFile[$key]} in this file";
                }
                if ($duplicateOf !== null) {
                    break;
                }
            }
            if ($duplicateOf !== null) {
                $summary['duplicate']++;
                $result[] = ['row' => $line, 'status' => 'duplicate', 'messages' => [$duplicateOf], 'data' => $data];
                continue;
            }

            foreach ($keys as $key) {
                $seenInFile[$key] = $line;
            }
            $summary['new']++;
            $result[] = ['row' => $line, 'status' => 'new', 'messages' => [], 'data' => $data];
        }

        if ($summary['total'] === 0) {
            throw new HttpException(422, 'The file contains no data rows');
        }
        return ['rows' => $result, 'ignored_columns' => $ignored, 'summary' => $summary];
    }

    /**
     * Inserts all rows with status "new" in one transaction.
     *
     * @param array{rows: list<array<string,mixed>>} $analysis
     */
    public function commit(array $analysis): int
    {
        $columns = array_keys(Schema::companyRules());
        $sql = sprintf(
            'INSERT INTO companies (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );
        $nulls = array_fill_keys($columns, null);

        $pdo = Database::connect();
        $stmt = $pdo->prepare($sql);
        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach ($analysis['rows'] as $row) {
                if ($row['status'] === 'new') {
                    $stmt->execute(array_merge($nulls, $row['data']));
                    $count++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $count;
    }

    /**
     * @param list<string> $header
     * @return array{0: list<?string>, 1: list<string>} field per column position, and ignored header labels
     */
    private function mapHeader(array $header): array
    {
        return HeaderMapper::map($header, self::ALIASES);
    }

    /** @return array<string,true> */
    private function existingKeys(): array
    {
        $keys = [];
        $stmt = Database::connect()->query('SELECT name, city, org_number FROM companies');
        foreach ($stmt as $row) {
            foreach ($this->keysFor($row) as $key) {
                $keys[$key] = true;
            }
        }
        return $keys;
    }

    /**
     * Identity keys: organisation number (digits only) and name + city.
     *
     * @param array<string,mixed> $company
     * @return list<string>
     */
    private function keysFor(array $company): array
    {
        $keys = [];
        $org = preg_replace('/\D/', '', (string) ($company['org_number'] ?? '')) ?? '';
        if ($org !== '') {
            $keys[] = 'org:' . $org;
        }
        $keys[] = 'name:' . mb_strtolower(trim((string) $company['name'])) . '|' . mb_strtolower(trim((string) ($company['city'] ?? '')));
        return $keys;
    }

    /** @return list<string> */
    private function describe(HttpException $e): array
    {
        $messages = [];
        foreach ($e->errors as $field => $message) {
            $messages[] = "{$field} {$message}";
        }
        return $messages === [] ? [$e->getMessage()] : $messages;
    }
}
