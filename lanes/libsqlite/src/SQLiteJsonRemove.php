<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonRemove
{
    public static function removeSqlFunction(
        string $function,
        string|int|float|bool|SQLiteBlobValue|null $value,
        ?string ...$paths,
    ): string|SQLiteBlobValue|null
    {
        if (strcasecmp($function, 'json_remove') === 0) {
            return self::remove($value, ...$paths);
        }
        if (strcasecmp($function, 'jsonb_remove') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON remove function must be json_remove or jsonb_remove');
        }
        if ($value === null) {
            return null;
        }
        if (in_array(null, $paths, true)) {
            return null;
        }

        $jsonb = self::jsonbBytes($value);
        $removed = $paths === []
            ? $jsonb
            : SQLiteJsonB::remove($jsonb, ...$paths);

        return $removed === null ? null : new SQLiteBlobValue($removed);
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function removeSqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue|null
    {
        if ($arguments === []) {
            throw new \InvalidArgumentException('SQLite json_remove() expects JSON plus zero or more paths');
        }

        $value = array_shift($arguments);
        if (!$value instanceof SQLiteBlobValue && !is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value) && $value !== null) {
            throw new \InvalidArgumentException('SQLite json_remove() JSON argument must be text, numeric, BLOB, or NULL');
        }

        foreach ($arguments as $path) {
            if ($path !== null && !is_string($path)) {
                throw new \InvalidArgumentException('SQLite json_remove() path arguments must be text');
            }
        }

        return self::removeSqlFunction($function, $value, ...$arguments);
    }

    public static function remove(string|int|float|bool|SQLiteBlobValue|null $value, ?string ...$paths): ?string
    {
        if ($value === null) {
            return null;
        }
        if (in_array(null, $paths, true)) {
            return null;
        }

        $jsonb = self::jsonbBytes($value);
        $removed = $paths === []
            ? $jsonb
            : SQLiteJsonB::remove($jsonb, ...$paths);

        return $removed === null ? null : SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decodeForJsonEncoding($removed));
    }

    private static function jsonbBytes(string|int|float|bool|SQLiteBlobValue $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            if (SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
                SQLiteJsonB::decode($value->bytes);

                return $value->bytes;
            }

            return SQLiteJsonB::encode(self::decodeJsonText($value->bytes));
        }

        return SQLiteJsonB::encode(self::decodeJsonInput($value));
    }

    private static function decodeJsonInput(string|int|float|bool $json): mixed
    {
        if (is_int($json)) {
            return $json;
        }
        if (is_float($json)) {
            if (!is_finite($json)) {
                throw new \InvalidArgumentException('SQLite JSON numeric input must be finite');
            }

            return $json;
        }
        if (is_bool($json)) {
            return $json ? 1 : 0;
        }

        return self::decodeJsonText($json);
    }

    private static function decodeJsonText(string $json): mixed
    {
        try {
            return json_decode($json, false, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return SQLiteJson5Parser::decode($json);
        }
    }
}
