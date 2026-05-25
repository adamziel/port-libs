<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonPatch
{
    public static function patchSqlFunction(
        string $function,
        string|SQLiteBlobValue|null $target,
        string|SQLiteBlobValue|null $patch,
    ): string|SQLiteBlobValue|null {
        if ($function === 'json_patch') {
            return self::patch($target, $patch);
        }
        if ($function !== 'jsonb_patch') {
            throw new \InvalidArgumentException('SQLite JSON patch function must be json_patch or jsonb_patch');
        }
        if ($target === null || $patch === null) {
            return null;
        }

        return new SQLiteBlobValue(SQLiteJsonB::patch(self::jsonbBytes($target), self::jsonbBytes($patch)));
    }

    public static function patch(string|SQLiteBlobValue|null $target, string|SQLiteBlobValue|null $patch): ?string
    {
        if ($target === null || $patch === null) {
            return null;
        }

        return SQLiteJsonCanonical::encodeDecodedJson(
            SQLiteJsonB::decode(SQLiteJsonB::patch(self::jsonbBytes($target), self::jsonbBytes($patch))),
        );
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
