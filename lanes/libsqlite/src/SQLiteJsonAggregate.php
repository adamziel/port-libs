<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonAggregate
{
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
}
