<?php
declare(strict_types=1);

namespace Mailings\Import;

final class HeaderMapper
{
    /**
     * Maps header labels to fields using normalised aliases (case, spaces and punctuation ignored).
     *
     * @param list<string> $header
     * @param array<string,list<string>> $aliases field => accepted normalised names
     * @return array{0: list<?string>, 1: list<string>} field per column position, and ignored header labels
     */
    public static function map(array $header, array $aliases): array
    {
        $lookup = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $name) {
                $lookup[$name] = $field;
            }
        }

        $columns = [];
        $ignored = [];
        $used = [];
        foreach ($header as $label) {
            $normalised = preg_replace('/[^a-z0-9æøå]/u', '', mb_strtolower(trim($label))) ?? '';
            $field = $lookup[$normalised] ?? null;
            if ($field === null || isset($used[$field])) {
                $columns[] = null;
                if (trim($label) !== '') {
                    $ignored[] = trim($label);
                }
                continue;
            }
            $used[$field] = true;
            $columns[] = $field;
        }
        return [$columns, $ignored];
    }
}
