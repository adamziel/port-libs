<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonValidity
{
    public const FLAG_STRICT_TEXT = 0x01;
    public const FLAG_JSON5_TEXT = 0x02;
    public const FLAG_SUPERFICIAL_JSONB = 0x04;
    public const FLAG_STRICT_JSONB = 0x08;

    public static function jsonValidSqlFunction(
        string $function,
        string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value,
        string|int|float|bool|SQLiteBlobValue|null $flags = self::FLAG_STRICT_TEXT,
    ): ?bool {
        if (strcasecmp($function, 'json_valid') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON validity function must be json_valid');
        }

        return self::jsonValid($value, self::coerceFlags($flags));
    }

    /**
     * @param list<string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null> $arguments
     */
    public static function jsonValidSqlFunctionArguments(string $function, array $arguments): ?bool
    {
        $count = count($arguments);
        if ($count < 1 || $count > 2) {
            throw new \InvalidArgumentException('SQLite json_valid() expects one or two arguments');
        }

        $flags = array_key_exists(1, $arguments) ? $arguments[1] : self::FLAG_STRICT_TEXT;

        return self::jsonValidSqlFunction($function, $arguments[0], $flags);
    }

    public static function jsonValid(string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, int $flags = self::FLAG_STRICT_TEXT): ?bool
    {
        if ($flags < 1 || $flags > 15) {
            throw new \InvalidArgumentException('FLAGS parameter to json_valid() must be between 1 and 15');
        }
        if ($value === null) {
            return null;
        }

        if ($value instanceof SQLiteBlobValue) {
            return self::blobValid($value->bytes, $flags);
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return self::subtypeValid($value->json, $flags);
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return self::textValid(self::sqlScalarText($value), $flags);
        }

        return self::textValid($value, $flags);
    }

    public static function textValid(string $json, int $flags = self::FLAG_STRICT_TEXT): bool
    {
        if ($flags < 1 || $flags > 15) {
            throw new \InvalidArgumentException('FLAGS parameter to json_valid() must be between 1 and 15');
        }
        if (($flags & (self::FLAG_STRICT_TEXT | self::FLAG_JSON5_TEXT)) === 0) {
            return false;
        }
        if (($flags & self::FLAG_JSON5_TEXT) !== 0) {
            return self::json5TextValid($json);
        }

        return self::strictTextValid($json);
    }

    public static function strictTextValid(string $json): bool
    {
        if (!self::strictStringEscapesValid($json)) {
            return false;
        }

        try {
            json_decode($json, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);

            return true;
        } catch (\JsonException) {
            return false;
        }
    }

    private static function strictStringEscapesValid(string $json): bool
    {
        $length = strlen($json);
        $inString = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if (!$inString) {
                if ($char === '"') {
                    $inString = true;
                }
                continue;
            }

            if ($char === '"') {
                $inString = false;
                continue;
            }
            if ($char !== '\\') {
                continue;
            }

            if ($i + 1 >= $length) {
                return false;
            }

            $escape = $json[++$i];
            if (str_contains('"/\\bfnrt', $escape)) {
                continue;
            }
            if ($escape !== 'u') {
                return false;
            }
            if ($i + 4 >= $length) {
                return false;
            }
            $hex = substr($json, $i + 1, 4);
            if (preg_match('/\A[0-9A-Fa-f]{4}\z/', $hex) !== 1) {
                return false;
            }
            $i += 4;
        }

        return true;
    }

    public static function json5TextValid(string $json): bool
    {
        try {
            SQLiteJson5Parser::decode($json);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private static function blobValid(string $bytes, int $flags): bool
    {
        if (SQLiteJsonB::isSuperficiallyJsonB($bytes)) {
            if (($flags & self::FLAG_SUPERFICIAL_JSONB) !== 0) {
                return true;
            }
            if (($flags & self::FLAG_STRICT_JSONB) !== 0) {
                return SQLiteJsonB::isStrictlyWellFormed($bytes);
            }

            return false;
        }

        return self::textValid($bytes, $flags);
    }

    private static function subtypeValid(string $json, int $flags): bool
    {
        if (($flags & (self::FLAG_STRICT_TEXT | self::FLAG_JSON5_TEXT)) === 0) {
            return false;
        }

        return self::strictTextValid($json);
    }

    private static function coerceFlags(string|int|float|bool|SQLiteBlobValue|null $flags): int
    {
        if ($flags === null) {
            throw new \InvalidArgumentException('FLAGS parameter to json_valid() must be between 1 and 15');
        }
        if ($flags instanceof SQLiteBlobValue) {
            $flags = $flags->bytes;
        }
        if (is_bool($flags)) {
            return $flags ? 1 : 0;
        }
        if (is_int($flags)) {
            return $flags;
        }
        if (is_float($flags)) {
            return (int) $flags;
        }

        $trimmed = ltrim($flags);
        if ($trimmed === '') {
            return 0;
        }
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $trimmed, $matches) !== 1) {
            return 0;
        }

        return (int) (float) $matches[0];
    }

    private static function sqlScalarText(int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (!is_finite($value)) {
            return $value > 0 ? 'Inf' : '-Inf';
        }

        return json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
