<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonPatch
{
    /**
     * @param list<mixed> $arguments
     */
    public static function patchSqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue|null
    {
        if (count($arguments) !== 2) {
            throw new \InvalidArgumentException('SQLite json_patch() expects exactly two arguments');
        }

        return self::patchSqlFunction($function, $arguments[0], $arguments[1]);
    }

    public static function patchSqlFunction(
        string $function,
        string|int|float|bool|SQLiteBlobValue|null $target,
        string|int|float|bool|SQLiteBlobValue|null $patch,
    ): string|SQLiteBlobValue|null {
        if (strcasecmp($function, 'json_patch') === 0) {
            return self::patch($target, $patch);
        }
        if (strcasecmp($function, 'jsonb_patch') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON patch function must be json_patch or jsonb_patch');
        }
        if ($target === null || $patch === null) {
            return null;
        }

        return new SQLiteBlobValue(SQLiteJsonB::patch(self::jsonbBytes($target), self::jsonbBytes($patch)));
    }

    public static function patch(
        string|int|float|bool|SQLiteBlobValue|null $target,
        string|int|float|bool|SQLiteBlobValue|null $patch,
    ): ?string
    {
        if ($target === null || $patch === null) {
            return null;
        }

        return SQLiteJsonCanonical::encodeDecodedJson(
            SQLiteJsonB::patchDecoded(self::jsonbBytes($target), self::jsonbBytes($patch)),
        );
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
            return json_decode($json, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return SQLiteJson5Parser::decode($json);
        }
    }
}
