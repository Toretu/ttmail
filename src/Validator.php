<?php
declare(strict_types=1);

namespace Mailings;

use Mailings\Http\HttpException;

final class Validator
{
    /**
     * Rules per field: required, max, enum, format (email|url|int|decimal|country|bool), min (int), default.
     * Unknown input fields are ignored; only fields defined in $rules are returned.
     * format bool accepts true/false, 1/0 and "1"/"0" and returns the integer 1 or 0.
     *
     * @param array<string,mixed> $input
     * @param array<string,array<string,mixed>> $rules
     * @return array<string,mixed>
     */
    public static function clean(array $input, array $rules, bool $partial): array
    {
        $data = [];
        $errors = [];

        foreach ($rules as $field => $rule) {
            if (!array_key_exists($field, $input)) {
                if (!$partial && !empty($rule['required'])) {
                    $errors[$field] = 'is required';
                } elseif (!$partial && array_key_exists('default', $rule)) {
                    $data[$field] = $rule['default'];
                }
                continue;
            }

            $value = $input[$field];
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                if (!empty($rule['required'])) {
                    $errors[$field] = 'must not be empty';
                } elseif (array_key_exists('default', $rule)) {
                    $data[$field] = $rule['default'];
                } else {
                    $data[$field] = null;
                }
                continue;
            }

            $format = $rule['format'] ?? null;
            if ($format === 'bool') {
                $flag = match (true) {
                    $value === true, $value === 1, $value === '1', $value === 'true' => 1,
                    $value === false, $value === 0, $value === '0', $value === 'false' => 0,
                    default => null,
                };
                if ($flag === null) {
                    $errors[$field] = 'must be true or false';
                    continue;
                }
                $data[$field] = $flag;
                continue;
            }
            if ($format === 'int') {
                if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
                    $errors[$field] = 'must be an integer';
                    continue;
                }
                if ((int) $value < ($rule['min'] ?? 0)) {
                    $errors[$field] = 'must be at least ' . ($rule['min'] ?? 0);
                    continue;
                }
                $data[$field] = (int) $value;
                continue;
            }
            if ($format === 'decimal') {
                $text = is_string($value) ? str_replace(',', '.', $value) : (is_int($value) || is_float($value) ? (string) $value : '');
                if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $text)) {
                    $errors[$field] = 'must be a non-negative amount with at most 2 decimals';
                    continue;
                }
                $data[$field] = number_format((float) $text, 2, '.', '');
                continue;
            }

            if (!is_string($value)) {
                $errors[$field] = 'must be a string';
                continue;
            }
            $max = $rule['max'] ?? 255;
            if (mb_strlen($value) > $max) {
                $errors[$field] = "must be at most {$max} characters";
                continue;
            }
            if (isset($rule['enum']) && !in_array($value, $rule['enum'], true)) {
                $errors[$field] = 'must be one of: ' . implode(', ', $rule['enum']);
                continue;
            }
            if ($format === 'email') {
                $value = mb_strtolower($value);
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[$field] = 'must be a valid email address';
                    continue;
                }
            }
            if ($format === 'url' && (!preg_match('#^https?://#i', $value) || filter_var($value, FILTER_VALIDATE_URL) === false)) {
                $errors[$field] = 'must be a valid http(s) URL';
                continue;
            }
            if ($format === 'country') {
                $value = strtoupper($value);
                if (!preg_match('/^[A-Z]{2}$/', $value)) {
                    $errors[$field] = 'must be a two-letter country code';
                    continue;
                }
            }
            $data[$field] = $value;
        }

        if ($errors !== []) {
            throw new HttpException(422, 'Validation failed', $errors);
        }
        if ($partial && $data === []) {
            throw new HttpException(422, 'No updatable fields supplied');
        }
        return $data;
    }
}
