<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonAggregate
{
    /**
     * @param iterable<mixed> $values
     */
    public static function jsonGroupArraySqlFunction(string $function, iterable $values): string|SQLiteBlobValue
    {
        $json = self::jsonGroupArray($values);
        if (strcasecmp($function, 'json_group_array') === 0) {
            return $json;
        }
        if (strcasecmp($function, 'jsonb_group_array') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_array or jsonb_group_array');
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($json)));
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function jsonGroupArraySqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue
    {
        return self::jsonGroupArraySqlFunction($function, $arguments);
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function jsonGroupArrayDistinctSqlFunction(string $function, iterable $values): string|SQLiteBlobValue
    {
        $json = self::jsonGroupArrayDistinct($values);
        if (strcasecmp($function, 'json_group_array') === 0) {
            return $json;
        }
        if (strcasecmp($function, 'jsonb_group_array') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_array or jsonb_group_array');
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($json)));
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $pairs
     */
    public static function jsonGroupObjectSqlFunction(string $function, iterable $pairs): string|SQLiteBlobValue
    {
        $json = self::jsonGroupObject($pairs);
        if (strcasecmp($function, 'json_group_object') === 0) {
            return $json;
        }
        if (strcasecmp($function, 'jsonb_group_object') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_object or jsonb_group_object');
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($json)));
    }

    /**
     * @param list<array{0:mixed,1:mixed}> $arguments
     */
    public static function jsonGroupObjectSqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue
    {
        return self::jsonGroupObjectSqlFunction($function, $arguments);
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function jsonGroupArray(iterable $values): string
    {
        $items = [];
        foreach ($values as $value) {
            $items[] = SQLiteJsonConstructor::jsonValue($value);
        }

        return '[' . implode(',', $items) . ']';
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function jsonGroupArrayDistinct(iterable $values): string
    {
        $items = [];
        $seen = [];
        foreach ($values as $value) {
            $key = self::distinctKey($value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = SQLiteJsonConstructor::jsonValue($value);
        }

        return '[' . implode(',', $items) . ']';
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $pairs
     */
    public static function jsonGroupObject(iterable $pairs): string
    {
        $members = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair) || !array_key_exists(0, $pair) || !array_key_exists(1, $pair)) {
                throw new \InvalidArgumentException('json_group_object() aggregate rows must be [label, value] pairs');
            }
            $members[] = SQLiteJsonConstructor::jsonObjectLabel($pair[0]) . ':' . SQLiteJsonConstructor::jsonValue($pair[1]);
        }

        return '{' . implode(',', $members) . '}';
    }

    private static function decodeAggregateJson(string $json): mixed
    {
        try {
            return json_decode($json, false, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('SQLite JSON aggregate output could not be encoded as JSONB', 0, $exception);
        }
    }

    private static function distinctKey(mixed $value): string
    {
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return 'json:' . $value->json;
        }
        if ($value instanceof SQLiteBlobValue) {
            SQLiteJsonConstructor::jsonValue($value);

            return 'blob:' . $value->bytes;
        }
        if ($value === null) {
            return 'null:';
        }
        if (is_bool($value)) {
            return 'integer:' . ($value ? '1' : '0');
        }
        if (is_int($value)) {
            return 'integer:' . $value;
        }
        if (is_float($value)) {
            return 'real:' . serialize($value);
        }
        if (is_string($value)) {
            return 'text:' . $value;
        }

        return 'json-value:' . SQLiteJsonConstructor::jsonValue($value);
    }
}
