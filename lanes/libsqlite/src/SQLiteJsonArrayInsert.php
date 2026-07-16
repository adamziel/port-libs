<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonArrayInsert
{
    /**
     * @param list<mixed> $arguments
     */
    public static function arrayInsertSqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue|null
    {
        if (count($arguments) < 3 || count($arguments) % 2 !== 1) {
            throw new \InvalidArgumentException('SQLite json_array_insert() expects JSON plus path/value pairs');
        }

        $value = array_shift($arguments);
        $path = array_shift($arguments);
        if (!is_string($path)) {
            throw new \InvalidArgumentException('SQLite JSON array insert path must be a string');
        }

        return self::arrayInsertSqlFunction($function, $value, $path, array_shift($arguments), ...$arguments);
    }

    public static function arrayInsertSqlFunction(
        string $function,
        string|SQLiteBlobValue|null $value,
        string $path,
        mixed $replacement,
        mixed ...$pathValuePairs,
    ): string|SQLiteBlobValue|null {
        if (strcasecmp($function, 'json_array_insert') !== 0 && strcasecmp($function, 'jsonb_array_insert') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON array insert function must be json_array_insert or jsonb_array_insert');
        }
        if ($value === null) {
            return null;
        }
        if (count($pathValuePairs) % 2 !== 0) {
            throw new \InvalidArgumentException('SQLite JSON array insert requires path/value pairs');
        }

        $normalizedPairs = [];
        for ($offset = 0; $offset < count($pathValuePairs); $offset += 2) {
            $nextPath = $pathValuePairs[$offset];
            if (!is_string($nextPath)) {
                throw new \InvalidArgumentException('SQLite JSON array insert path must be a string');
            }

            $normalizedPairs[] = $nextPath;
            $normalizedPairs[] = self::jsonArrayInsertValue($pathValuePairs[$offset + 1]);
        }

        $mutated = SQLiteJsonB::arrayInsert(
            self::jsonbBytes($value),
            $path,
            self::jsonArrayInsertValue($replacement),
            ...$normalizedPairs,
        );

        if (strcasecmp($function, 'jsonb_array_insert') === 0) {
            return new SQLiteBlobValue($mutated);
        }

        return SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decodeForJsonEncoding($mutated));
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

    private static function jsonArrayInsertValue(mixed $value): mixed
    {
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return self::decodeJsonText($value->json);
        }
        if ($value instanceof SQLiteBlobValue) {
            if (!SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
                throw new \InvalidArgumentException('JSON cannot hold BLOB values');
            }

            return SQLiteJsonB::decode($value->bytes);
        }
        if ($value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite JSON array insert value must be a SQL scalar, JSON subtype value, or JSONB BLOB value');
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
