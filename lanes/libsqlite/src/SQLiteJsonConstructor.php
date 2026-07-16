<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonConstructor
{
    public static function jsonArraySqlFunction(string $function, mixed ...$values): string|SQLiteBlobValue
    {
        $normalized = strtolower($function);
        $json = match ($normalized) {
            'json_array', 'jsonb_array' => self::jsonArray(...$values),
            default => throw new \InvalidArgumentException('SQLite JSON array constructor function must be json_array or jsonb_array'),
        };

        return $normalized === 'jsonb_array'
            ? new SQLiteBlobValue(SQLiteJsonB::encode(SQLiteJson5Parser::decode($json)))
            : $json;
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function jsonArraySqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue
    {
        return self::jsonArraySqlFunction($function, ...$arguments);
    }

    public static function jsonObjectSqlFunction(string $function, mixed ...$pairs): string|SQLiteBlobValue
    {
        $normalized = strtolower($function);
        $json = match ($normalized) {
            'json_object', 'jsonb_object' => self::jsonObject(...$pairs),
            default => throw new \InvalidArgumentException('SQLite JSON object constructor function must be json_object or jsonb_object'),
        };

        return $normalized === 'jsonb_object'
            ? new SQLiteBlobValue(SQLiteJsonB::encode(SQLiteJson5Parser::decode($json)))
            : $json;
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function jsonObjectSqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue
    {
        return self::jsonObjectSqlFunction($function, ...$arguments);
    }

    public static function jsonArray(mixed ...$values): string
    {
        $items = [];
        foreach ($values as $value) {
            $items[] = self::jsonValue($value);
        }

        return '[' . implode(',', $items) . ']';
    }

    public static function jsonObject(mixed ...$pairs): string
    {
        if (count($pairs) % 2 !== 0) {
            throw new \InvalidArgumentException('json_object() requires an even number of arguments');
        }

        $members = [];
        for ($offset = 0; $offset < count($pairs); $offset += 2) {
            $members[] = self::jsonObjectLabel($pairs[$offset]) . ':' . self::jsonValue($pairs[$offset + 1]);
        }

        return '{' . implode(',', $members) . '}';
    }

    public static function jsonObjectLabel(mixed $label): string
    {
        if ($label instanceof SQLiteJsonSubtypeValue) {
            return SQLiteJsonQuote::jsonQuote($label->json);
        }
        if (!is_string($label)) {
            throw new \InvalidArgumentException('json_object() labels must be TEXT');
        }

        return SQLiteJsonQuote::jsonQuote($label);
    }

    public static function jsonValue(mixed $value): string
    {
        if (
            $value === null
            || is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value instanceof SQLiteBlobValue
            || $value instanceof SQLiteJsonSubtypeValue
        ) {
            return SQLiteJsonQuote::jsonQuote($value);
        }

        throw new \InvalidArgumentException('SQLite JSON constructor value must be a SQL scalar, JSON subtype value, or BLOB value');
    }
}
