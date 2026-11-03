<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Import\CompanyImporter;
use Mailings\Import\ContactImporter;
use Mailings\Import\SpreadsheetReader;

final class ImportController
{
    private const MAX_BYTES = 2_000_000;
    private const MAX_REPORTED_ROWS = 500;

    /**
     * POST /api/companies/import?mode=preview|commit with multipart field "file" (.csv or .xlsx).
     * The default mode is preview, which never writes to the database.
     *
     * @return array<string,mixed>
     */
    public function companies(): array
    {
        return $this->run(new CompanyImporter());
    }

    /** POST /api/contacts/import, same contract as the company import. */
    public function contacts(): array
    {
        return $this->run(new ContactImporter());
    }

    /** @return array<string,mixed> */
    private function run(CompanyImporter|ContactImporter $importer): array
    {
        $mode = (string) ($_GET['mode'] ?? 'preview');
        if (!in_array($mode, ['preview', 'commit'], true)) {
            throw new HttpException(400, 'mode must be "preview" or "commit"');
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new HttpException(400, 'Upload a .csv or .xlsx file in the "file" field');
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > self::MAX_BYTES) {
            throw new HttpException(413, 'The file is larger than 2 MB');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new HttpException(400, 'The file upload failed; try again');
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $rows = SpreadsheetReader::read($file['tmp_name'], $extension);

        $analysis = $importer->analyze($rows);
        if ($mode === 'commit') {
            $analysis['summary']['imported'] = $importer->commit($analysis);
        }

        return ['data' => [
            'mode' => $mode,
            'summary' => $analysis['summary'],
            'ignored_columns' => $analysis['ignored_columns'],
            'rows' => array_slice($analysis['rows'], 0, self::MAX_REPORTED_ROWS),
            'rows_truncated' => count($analysis['rows']) > self::MAX_REPORTED_ROWS,
        ]];
    }
}
