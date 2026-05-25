<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonRemove
{
    public static function removeSqlFunction(string $function, string|SQLiteBlobValue|null $value, string ...$paths): string|SQLiteBlobValue|null
    {
        if ($function === 'json_remove') {
            return self::remove($value, ...$paths);
        }
        if ($function !== 'jsonb_remove') {
            throw new \InvalidArgumentException('SQLite JSON remove function must be json_remove or jsonb_remove');
        }
        if ($value === null) {
            return null;
        }

        $jsonb = self::jsonbBytes($value);
        $removed = $paths === []
            ? $jsonb
            : SQLiteJsonB::remove($jsonb, ...$paths);

        return $removed === null ? null : new SQLiteBlobValue($removed);
    }

    public static function remove(string|SQLiteBlobValue|null $value, string ...$paths): ?string
    {
        if ($value === null) {
            return null;
        }

        $jsonb = self::jsonbBytes($value);
        $removed = $paths === []
            ? $jsonb
            : SQLiteJsonB::remove($jsonb, ...$paths);

        return $removed === null ? null : SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decode($removed));
    }

    private static function jsonbBytes(string|SQLiteBlobValue $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            if (SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
                SQLiteJsonB::decode($value->bytes);

                return $value->bytes;
            }

            return SQLiteJsonB::encode(self::decodeJsonText($value->bytes));
        }

        return SQLiteJsonB::encode(self::decodeJsonText($value));
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
