<?php
declare(strict_types=1);

namespace Mailings\Import;

use Mailings\Database;
use Mailings\Http\HttpException;
use Mailings\Schema;
use Mailings\Validator;

/** Imports contact persons and links them to existing companies by org number or exact name. */
final class ContactImporter
{
    public const MAX_ROWS = 2000;

    private const ALIASES = [
        'company' => ['company', 'companyname', 'firma', 'firmanavn', 'selskap', 'bedrift'],
        'org_number' => ['orgnumber', 'orgnr', 'organizationnumber', 'organisasjonsnummer', 'orgno'],
        'first_name' => ['firstname', 'givenname', 'fornavn'],
        'last_name' => ['lastname', 'surname', 'familyname', 'etternavn'],
        'full_name' => ['name', 'fullname', 'contact', 'contactperson', 'navn', 'kontakt', 'kontaktperson'],
        'job_title' => ['jobtitle', 'title', 'role', 'stilling', 'tittel', 'rolle'],
        'email' => ['email', 'emailaddress', 'mail', 'epost', 'epostadresse'],
        'phone' => ['phone', 'telephone', 'mobile', 'telefon', 'tlf', 'mobil'],
        'consent_status' => ['consentstatus', 'consent', 'samtykke'],
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

        [$columns, $ignored] = HeaderMapper::map($rows[0], self::ALIASES);
        if (!in_array('email', $columns, true)) {
            throw new HttpException(422, 'No email column found. Accepted headers: ' . implode(', ', self::ALIASES['email']));
        }
        if (!in_array('company', $columns, true) && !in_array('org_number', $columns, true)) {
            throw new HttpException(422, 'A company name or org number column is required to link each contact to a company');
        }

        $rules = Schema::contactRules();
        [$byOrg, $byName] = $this->companyIndex();
        $existingEmails = array_fill_keys(Database::connect()->query('SELECT email FROM contacts')->fetchAll(\PDO::FETCH_COLUMN), true);
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
                    $input[$field] = trim($cells[$position] ?? '');
                }
            }

            if (($input['first_name'] ?? '') === '' && ($input['last_name'] ?? '') === '' && ($input['full_name'] ?? '') !== '') {
                $parts = preg_split('/\s+/u', $input['full_name']) ?: [];
                $input['last_name'] = count($parts) > 1 ? (string) array_pop($parts) : '';
                $input['first_name'] = implode(' ', $parts);
            }
            if (isset($input['consent_status']) && $input['consent_status'] !== '') {
                $input['consent_status'] = strtolower($input['consent_status']);
            }

            $companyId = $this->resolveCompany($input, $byOrg, $byName, $problem);
            if ($companyId === null) {
                $summary['invalid']++;
                $result[] = ['row' => $line, 'status' => 'invalid', 'messages' => [$problem], 'data' => null];
                continue;
            }
            $input['company_id'] = (string) $companyId;

            try {
                $data = Validator::clean(array_diff_key($input, ['company' => 1, 'org_number' => 1, 'full_name' => 1]), $rules, false);
            } catch (HttpException $e) {
                $summary['invalid']++;
                $messages = [];
                foreach ($e->errors as $field => $message) {
                    $messages[] = "{$field} {$message}";
                }
                $result[] = ['row' => $line, 'status' => 'invalid', 'messages' => $messages ?: [$e->getMessage()], 'data' => null];
                continue;
            }

            $email = $data['email'];
            $reason = isset($existingEmails[$email]) ? 'email already exists in the database'
                : (isset($seenInFile[$email]) ? "duplicate of row {$seenInFile[$email]} in this file" : null);
            if ($reason !== null) {
                $summary['duplicate']++;
                $result[] = ['row' => $line, 'status' => 'duplicate', 'messages' => [$reason], 'data' => $data];
                continue;
            }

            $seenInFile[$email] = $line;
            $summary['new']++;
            $result[] = ['row' => $line, 'status' => 'new', 'messages' => [], 'data' => $data];
        }

        if ($summary['total'] === 0) {
            throw new HttpException(422, 'The file contains no data rows');
        }
        return ['rows' => $result, 'ignored_columns' => $ignored, 'summary' => $summary];
    }

    /** @param array{rows: list<array<string,mixed>>} $analysis */
    public function commit(array $analysis): int
    {
        $columns = array_keys(Schema::contactRules());
        $sql = sprintf(
            'INSERT INTO contacts (%s) VALUES (%s)',
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

    /** @return array{0: array<string,int>, 1: array<string,list<int>>} */
    private function companyIndex(): array
    {
        $byOrg = [];
        $byName = [];
        foreach (Database::connect()->query('SELECT id, name, org_number FROM companies') as $company) {
            $org = preg_replace('/\D/', '', (string) $company['org_number']) ?? '';
            if ($org !== '') {
                $byOrg[$org] = (int) $company['id'];
            }
            $byName[mb_strtolower(trim((string) $company['name']))][] = (int) $company['id'];
        }
        return [$byOrg, $byName];
    }

    /**
     * @param array<string,string> $input
     * @param array<string,int> $byOrg
     * @param array<string,list<int>> $byName
     */
    private function resolveCompany(array $input, array $byOrg, array $byName, ?string &$problem): ?int
    {
        $org = preg_replace('/\D/', '', $input['org_number'] ?? '') ?? '';
        if ($org !== '') {
            if (isset($byOrg[$org])) {
                return $byOrg[$org];
            }
            $problem = "no company with org number {$input['org_number']}; import companies first";
            return null;
        }
        $name = mb_strtolower($input['company'] ?? '');
        if ($name === '') {
            $problem = 'company is required';
            return null;
        }
        $matches = $byName[$name] ?? [];
        if (count($matches) === 1) {
            return $matches[0];
        }
        $problem = $matches === []
            ? "no company named \"{$input['company']}\"; import companies first"
            : "several companies are named \"{$input['company']}\"; use the org number column";
        return null;
    }
}
