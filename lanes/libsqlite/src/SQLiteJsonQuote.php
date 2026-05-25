<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonQuote
{
    public static function jsonQuoteSqlFunction(string $function, string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value): string
    {
        if (strcasecmp($function, 'json_quote') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON quote function must be json_quote');
        }

        return self::jsonQuote($value);
    }

    /**
     * @param list<string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null> $arguments
     */
    public static function jsonQuoteSqlFunctionArguments(string $function, array $arguments): string
    {
        if (count($arguments) !== 1) {
            throw new \InvalidArgumentException('SQLite json_quote() expects one argument');
        }

        return self::jsonQuoteSqlFunction($function, $arguments[0]);
    }

    public static function jsonQuote(string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return $value->json;
        }
        if ($value instanceof SQLiteBlobValue) {
            return self::quoteBlob($value->bytes);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return self::quoteFloat($value);
        }

        return self::encodeJsonValue($value);
    }

    private static function quoteBlob(string $bytes): string
    {
        if (!SQLiteJsonB::isSuperficiallyJsonB($bytes)) {
            throw new \InvalidArgumentException('JSON cannot hold BLOB values');
        }

        try {
            return self::encodeJsonValue(SQLiteJsonB::decode($bytes));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException('malformed JSON', 0, $exception);
        }
    }

    private static function quoteFloat(float $value): string
    {
        if (is_nan($value)) {
            return 'null';
        }
        if ($value === INF) {
            return '9.0e+999';
        }
        if ($value === -INF) {
            return '-9.0e+999';
        }

        $encoded = json_encode($value, JSON_PRESERVE_ZERO_FRACTION);
        if (!is_string($encoded)) {
            throw new \InvalidArgumentException('SQLite JSON real value could not be encoded');
        }

        return $encoded;
    }

    private static function encodeJsonValue(mixed $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
        if (!is_string($encoded)) {
            throw new \InvalidArgumentException('SQLite JSON value could not be encoded');
        }

        return $encoded;
    }
}
