<?php
namespace App\Shared;

final class Input
{
    public static function validate(array $body, array $rules, bool $create = true): array
    {
        $values = [];
        foreach ($rules as $key => $rule) {
            [$type, $required, $default] = array_pad($rule, 3, null);
            if (!array_key_exists($key, $body)) {
                if (!$create) continue;
                if ($required) self::fail($key);
                $value = $default;
            } else {
                $value = $body[$key];
            }
            if ($value === null || $value === '') {
                if ($required) self::fail($key);
                $values[$key] = $value === '' && $type === 'string' ? '' : null;
                continue;
            }
            if (is_array($type)) {
                if (!is_string($value) || !in_array($value, $type, true)) self::fail($key);
            } elseif ($type === 'id' || $type === 'integer') {
                if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < ($type === 'id' ? 1 : 0)) self::fail($key);
                $value = (int)$value;
            } elseif ($type === 'number') {
                if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0) self::fail($key);
                $value = (float)$value;
            } elseif ($type === 'array') {
                if (!is_array($value) || !array_is_list($value)) self::fail($key);
            } elseif ($type === 'bool') {
                if (!in_array($value, [true, false, 0, 1], true)) self::fail($key);
                $value = (int)$value;
            } else {
                if (!is_string($value)) self::fail($key);
                if ($type !== 'password') $value = trim($value);
                if ($required && $value === '') self::fail($key);
                if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) self::fail($key);
                if ($type === 'date' && !self::date($value)) self::fail($key);
                if ($type === 'time' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $value)) self::fail($key);
                if ($type === 'time' && strlen($value) === 5) $value .= ':00';
                if ($type === 'password' && (strlen($value) < 6 || strlen($value) > 72)) self::fail($key);
            }
            $values[$key] = $value;
        }
        return $values;
    }

    public static function date(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    public static function fail(string $field): never
    {
        throw new ApiException(400, "Trường '$field' không hợp lệ hoặc còn thiếu.");
    }

    public static function pair(array $data, string $start, string $end): void
    {
        if (isset($data[$start], $data[$end]) && $data[$end] <= $data[$start]) self::fail($end);
    }

    public static function credentials(array $data): void
    {
        if (isset($data['username']) !== isset($data['password'])) self::fail('username/password');
    }

    public static function set(mixed $value, array $allowed): string
    {
        $items = is_array($value) ? $value : explode(',', (string)$value);
        if (!$items || count(array_diff($items, $allowed))) self::fail('SET');
        return implode(',', array_unique($items));
    }
}
