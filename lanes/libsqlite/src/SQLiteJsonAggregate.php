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
     * @param list<mixed> $arguments
     */
    public static function jsonGroupArrayDistinctSqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue
    {
        return self::jsonGroupArrayDistinctSqlFunction($function, $arguments);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     */
    public static function jsonGroupArrayOrderBySqlFunction(string $function, iterable $rows): string|SQLiteBlobValue
    {
        $json = self::jsonGroupArrayOrderBy($rows);
        if (strcasecmp($function, 'json_group_array') === 0) {
            return $json;
        }
        if (strcasecmp($function, 'jsonb_group_array') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_array or jsonb_group_array');
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($json)));
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     */
    public static function jsonGroupArrayDistinctOrderBySqlFunction(string $function, iterable $rows): string|SQLiteBlobValue
    {
        $json = self::jsonGroupArrayDistinctOrderBy($rows);
        if (strcasecmp($function, 'json_group_array') === 0) {
            return $json;
        }
        if (strcasecmp($function, 'jsonb_group_array') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_array or jsonb_group_array');
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($json)));
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     */
    public static function jsonGroupArrayFilterSqlFunction(string $function, iterable $rows): string|SQLiteBlobValue
    {
        $json = self::jsonGroupArrayFilter($rows);
        if (strcasecmp($function, 'json_group_array') === 0) {
            return $json;
        }
        if (strcasecmp($function, 'jsonb_group_array') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_array or jsonb_group_array');
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($json)));
    }

    /**
     * @param iterable<mixed> $values
     * @return list<string|SQLiteBlobValue>
     */
    public static function jsonGroupArrayWindowSqlFunction(string $function, iterable $values, int $preceding, int $following = 0): array
    {
        $frames = self::jsonGroupArrayWindow($values, $preceding, $following);
        if (strcasecmp($function, 'json_group_array') === 0) {
            return $frames;
        }
        if (strcasecmp($function, 'jsonb_group_array') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_array or jsonb_group_array');
        }

        $jsonbFrames = [];
        foreach ($frames as $frame) {
            $jsonbFrames[] = new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($frame)));
        }

        return $jsonbFrames;
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     * @return list<string|SQLiteBlobValue>
     */
    public static function jsonGroupArrayOrderByWindowSqlFunction(string $function, iterable $rows, int $preceding, int $following = 0): array
    {
        $frames = self::jsonGroupArrayOrderByWindow($rows, $preceding, $following);
        if (strcasecmp($function, 'json_group_array') === 0) {
            return $frames;
        }
        if (strcasecmp($function, 'jsonb_group_array') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_array or jsonb_group_array');
        }

        $jsonbFrames = [];
        foreach ($frames as $frame) {
            $jsonbFrames[] = new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($frame)));
        }

        return $jsonbFrames;
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
     * @param iterable<array{0:mixed,1:mixed}> $pairs
     */
    public static function jsonGroupObjectDistinctSqlFunction(string $function, iterable $pairs): string|SQLiteBlobValue
    {
        $json = self::jsonGroupObjectDistinct($pairs);
        if (strcasecmp($function, 'json_group_object') === 0) {
            return $json;
        }
        if (strcasecmp($function, 'jsonb_group_object') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_object or jsonb_group_object');
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($json)));
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed}> $rows
     */
    public static function jsonGroupObjectOrderBySqlFunction(string $function, iterable $rows): string|SQLiteBlobValue
    {
        $json = self::jsonGroupObjectOrderBy($rows);
        if (strcasecmp($function, 'json_group_object') === 0) {
            return $json;
        }
        if (strcasecmp($function, 'jsonb_group_object') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_object or jsonb_group_object');
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($json)));
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed}> $rows
     */
    public static function jsonGroupObjectDistinctOrderBySqlFunction(string $function, iterable $rows): string|SQLiteBlobValue
    {
        $json = self::jsonGroupObjectDistinctOrderBy($rows);
        if (strcasecmp($function, 'json_group_object') === 0) {
            return $json;
        }
        if (strcasecmp($function, 'jsonb_group_object') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_object or jsonb_group_object');
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($json)));
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed}> $rows
     */
    public static function jsonGroupObjectFilterSqlFunction(string $function, iterable $rows): string|SQLiteBlobValue
    {
        $json = self::jsonGroupObjectFilter($rows);
        if (strcasecmp($function, 'json_group_object') === 0) {
            return $json;
        }
        if (strcasecmp($function, 'jsonb_group_object') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_object or jsonb_group_object');
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($json)));
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $pairs
     * @return list<string|SQLiteBlobValue>
     */
    public static function jsonGroupObjectWindowSqlFunction(string $function, iterable $pairs, int $preceding, int $following = 0): array
    {
        $frames = self::jsonGroupObjectWindow($pairs, $preceding, $following);
        if (strcasecmp($function, 'json_group_object') === 0) {
            return $frames;
        }
        if (strcasecmp($function, 'jsonb_group_object') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_object or jsonb_group_object');
        }

        $jsonbFrames = [];
        foreach ($frames as $frame) {
            $jsonbFrames[] = new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($frame)));
        }

        return $jsonbFrames;
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed}> $rows
     * @return list<string|SQLiteBlobValue>
     */
    public static function jsonGroupObjectOrderByWindowSqlFunction(string $function, iterable $rows, int $preceding, int $following = 0): array
    {
        $frames = self::jsonGroupObjectOrderByWindow($rows, $preceding, $following);
        if (strcasecmp($function, 'json_group_object') === 0) {
            return $frames;
        }
        if (strcasecmp($function, 'jsonb_group_object') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_object or jsonb_group_object');
        }

        $jsonbFrames = [];
        foreach ($frames as $frame) {
            $jsonbFrames[] = new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($frame)));
        }

        return $jsonbFrames;
    }

    /**
     * @param list<array{0:mixed,1:mixed}> $arguments
     */
    public static function jsonGroupObjectSqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue
    {
        return self::jsonGroupObjectSqlFunction($function, $arguments);
    }

    /**
     * @param list<array{0:mixed,1:mixed}> $arguments
     */
    public static function jsonGroupObjectDistinctSqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue
    {
        return self::jsonGroupObjectDistinctSqlFunction($function, $arguments);
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
     * @param iterable<array{0:mixed,1:mixed}> $rows
     */
    public static function jsonGroupArrayOrderBy(iterable $rows): string
    {
        $ordered = [];
        $position = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists(0, $row) || !array_key_exists(1, $row)) {
                throw new \InvalidArgumentException('json_group_array() ORDER BY rows must be [value, orderKey] pairs');
            }
            $ordered[] = [
                'value' => $row[0],
                'orderKey' => $row[1],
                'position' => $position++,
            ];
        }

        usort($ordered, static function (array $left, array $right): int {
            $comparison = self::compareOrderKeys($left['orderKey'], $right['orderKey']);
            if ($comparison === 0) {
                return $left['position'] <=> $right['position'];
            }

            return $comparison;
        });

        $values = [];
        foreach ($ordered as $row) {
            $values[] = $row['value'];
        }

        return self::jsonGroupArray($values);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     */
    public static function jsonGroupArrayDistinctOrderBy(iterable $rows): string
    {
        $ordered = [];
        $position = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists(0, $row) || !array_key_exists(1, $row)) {
                throw new \InvalidArgumentException('json_group_array() DISTINCT ORDER BY rows must be [value, orderKey] pairs');
            }
            $ordered[] = [
                'value' => $row[0],
                'orderKey' => $row[1],
                'position' => $position++,
            ];
        }

        usort($ordered, static function (array $left, array $right): int {
            $comparison = self::compareOrderKeys($left['orderKey'], $right['orderKey']);
            if ($comparison === 0) {
                return $left['position'] <=> $right['position'];
            }

            return $comparison;
        });

        $values = [];
        $seen = [];
        foreach ($ordered as $row) {
            $key = self::distinctKey($row['value']);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $values[] = $row['value'];
        }

        return self::jsonGroupArray($values);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     */
    public static function jsonGroupArrayFilter(iterable $rows): string
    {
        $values = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists(0, $row) || !array_key_exists(1, $row)) {
                throw new \InvalidArgumentException('json_group_array() FILTER rows must be [value, filter] pairs');
            }
            if (!self::sqlFilterPasses($row[1])) {
                continue;
            }

            $values[] = $row[0];
        }

        return self::jsonGroupArray($values);
    }

    /**
     * @param iterable<mixed> $values
     * @return list<string>
     */
    public static function jsonGroupArrayWindow(iterable $values, int $preceding, int $following = 0): array
    {
        self::assertWindowBounds($preceding, $following);

        $rows = array_values(is_array($values) ? $values : iterator_to_array($values, false));
        $frames = [];
        $lastIndex = count($rows) - 1;
        foreach ($rows as $position => $_value) {
            $start = max(0, $position - $preceding);
            $end = min($lastIndex, $position + $following);
            $frames[] = self::jsonGroupArray(array_slice($rows, $start, $end - $start + 1));
        }

        return $frames;
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     * @return list<string>
     */
    public static function jsonGroupArrayOrderByWindow(iterable $rows, int $preceding, int $following = 0): array
    {
        self::assertWindowBounds($preceding, $following);

        $ordered = [];
        $position = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists(0, $row) || !array_key_exists(1, $row)) {
                throw new \InvalidArgumentException('json_group_array() ORDER BY window rows must be [value, orderKey] pairs');
            }
            $ordered[] = [
                'value' => $row[0],
                'orderKey' => $row[1],
                'position' => $position++,
            ];
        }

        usort($ordered, static function (array $left, array $right): int {
            $comparison = self::compareOrderKeys($left['orderKey'], $right['orderKey']);
            if ($comparison === 0) {
                return $left['position'] <=> $right['position'];
            }

            return $comparison;
        });

        $values = [];
        foreach ($ordered as $row) {
            $values[] = $row['value'];
        }

        return self::jsonGroupArrayWindow($values, $preceding, $following);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2?:mixed}> $rows
     * @return list<string>
     */
    public static function jsonGroupArrayWindowFrameRows(iterable $rows, int $preceding, int $following = 0, string $exclude = 'NO OTHERS'): array
    {
        $frames = [];
        foreach (self::jsonWindowFrameRows($rows, $preceding, $following, $exclude, 'json_group_array() window frame rows must be [value, orderKey] or [value, orderKey, filter] tuples') as $frame) {
            $values = [];
            foreach ($frame as $row) {
                $values[] = $row['value'];
            }
            $frames[] = self::jsonGroupArray($values);
        }

        return $frames;
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2?:mixed}> $rows
     * @return list<string|SQLiteBlobValue>
     */
    public static function jsonGroupArrayWindowFrameRowsSqlFunction(string $function, iterable $rows, int $preceding, int $following = 0, string $exclude = 'NO OTHERS'): array
    {
        $frames = self::jsonGroupArrayWindowFrameRows($rows, $preceding, $following, $exclude);
        if (strcasecmp($function, 'json_group_array') === 0) {
            return $frames;
        }
        if (strcasecmp($function, 'jsonb_group_array') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_array or jsonb_group_array');
        }

        $jsonbFrames = [];
        foreach ($frames as $frame) {
            $jsonbFrames[] = new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($frame)));
        }

        return $jsonbFrames;
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

    /**
     * @param iterable<array{0:mixed,1:mixed}> $pairs
     */
    public static function jsonGroupObjectDistinct(iterable $pairs): string
    {
        $members = [];
        $seen = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair) || !array_key_exists(0, $pair) || !array_key_exists(1, $pair)) {
                throw new \InvalidArgumentException('json_group_object() DISTINCT rows must be [label, value] pairs');
            }
            $key = self::distinctObjectKey($pair[0], $pair[1]);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $members[] = SQLiteJsonConstructor::jsonObjectLabel($pair[0]) . ':' . SQLiteJsonConstructor::jsonValue($pair[1]);
        }

        return '{' . implode(',', $members) . '}';
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed}> $rows
     */
    public static function jsonGroupObjectOrderBy(iterable $rows): string
    {
        $ordered = self::orderedObjectRows($rows, 'json_group_object() ORDER BY rows must be [label, value, orderKey] triples');

        return self::jsonGroupObject(array_map(
            static fn (array $row): array => [$row['label'], $row['value']],
            $ordered,
        ));
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed}> $rows
     */
    public static function jsonGroupObjectDistinctOrderBy(iterable $rows): string
    {
        $ordered = self::orderedObjectRows($rows, 'json_group_object() DISTINCT ORDER BY rows must be [label, value, orderKey] triples');
        $pairs = [];
        $seen = [];
        foreach ($ordered as $row) {
            $key = self::distinctObjectKey($row['label'], $row['value']);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $pairs[] = [$row['label'], $row['value']];
        }

        return self::jsonGroupObject($pairs);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed}> $rows
     */
    public static function jsonGroupObjectFilter(iterable $rows): string
    {
        $pairs = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists(0, $row) || !array_key_exists(1, $row) || !array_key_exists(2, $row)) {
                throw new \InvalidArgumentException('json_group_object() FILTER rows must be [label, value, filter] triples');
            }
            if (!self::sqlFilterPasses($row[2])) {
                continue;
            }

            $pairs[] = [$row[0], $row[1]];
        }

        return self::jsonGroupObject($pairs);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $pairs
     * @return list<string>
     */
    public static function jsonGroupObjectWindow(iterable $pairs, int $preceding, int $following = 0): array
    {
        self::assertWindowBounds($preceding, $following);

        $rows = array_values(is_array($pairs) ? $pairs : iterator_to_array($pairs, false));
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists(0, $row) || !array_key_exists(1, $row)) {
                throw new \InvalidArgumentException('json_group_object() window rows must be [label, value] pairs');
            }
        }

        $frames = [];
        $lastIndex = count($rows) - 1;
        foreach ($rows as $position => $_row) {
            $start = max(0, $position - $preceding);
            $end = min($lastIndex, $position + $following);
            $frames[] = self::jsonGroupObject(array_slice($rows, $start, $end - $start + 1));
        }

        return $frames;
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed}> $rows
     * @return list<string>
     */
    public static function jsonGroupObjectOrderByWindow(iterable $rows, int $preceding, int $following = 0): array
    {
        self::assertWindowBounds($preceding, $following);
        $ordered = self::orderedObjectRows($rows, 'json_group_object() ORDER BY window rows must be [label, value, orderKey] triples');

        return self::jsonGroupObjectWindow(array_map(
            static fn (array $row): array => [$row['label'], $row['value']],
            $ordered,
        ), $preceding, $following);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed,3?:mixed}> $rows
     * @return list<string>
     */
    public static function jsonGroupObjectWindowFrameRows(iterable $rows, int $preceding, int $following = 0, string $exclude = 'NO OTHERS'): array
    {
        $windowRows = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists(0, $row) || !array_key_exists(1, $row) || !array_key_exists(2, $row)) {
                throw new \InvalidArgumentException('json_group_object() window frame rows must be [label, value, orderKey] or [label, value, orderKey, filter] tuples');
            }
            $windowRows[] = array_key_exists(3, $row) ? [[$row[0], $row[1]], $row[2], $row[3]] : [[$row[0], $row[1]], $row[2]];
        }

        $frames = [];
        foreach (self::jsonWindowFrameRows($windowRows, $preceding, $following, $exclude, 'json_group_object() window frame rows must be [label, value, orderKey] or [label, value, orderKey, filter] tuples') as $frame) {
            $pairs = [];
            foreach ($frame as $row) {
                $pairs[] = $row['value'];
            }
            $frames[] = self::jsonGroupObject($pairs);
        }

        return $frames;
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed,3?:mixed}> $rows
     * @return list<string|SQLiteBlobValue>
     */
    public static function jsonGroupObjectWindowFrameRowsSqlFunction(string $function, iterable $rows, int $preceding, int $following = 0, string $exclude = 'NO OTHERS'): array
    {
        $frames = self::jsonGroupObjectWindowFrameRows($rows, $preceding, $following, $exclude);
        if (strcasecmp($function, 'json_group_object') === 0) {
            return $frames;
        }
        if (strcasecmp($function, 'jsonb_group_object') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate function must be json_group_object or jsonb_group_object');
        }

        $jsonbFrames = [];
        foreach ($frames as $frame) {
            $jsonbFrames[] = new SQLiteBlobValue(SQLiteJsonB::encode(self::decodeAggregateJson($frame)));
        }

        return $jsonbFrames;
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

    private static function distinctObjectKey(mixed $label, mixed $value): string
    {
        return SQLiteJsonConstructor::jsonObjectLabel($label) . "\0" . self::distinctKey($value);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed}> $rows
     * @return list<array{label:mixed,value:mixed,orderKey:mixed,position:int}>
     */
    private static function orderedObjectRows(iterable $rows, string $error): array
    {
        $ordered = [];
        $position = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists(0, $row) || !array_key_exists(1, $row) || !array_key_exists(2, $row)) {
                throw new \InvalidArgumentException($error);
            }
            $ordered[] = [
                'label' => $row[0],
                'value' => $row[1],
                'orderKey' => $row[2],
                'position' => $position++,
            ];
        }

        usort($ordered, static function (array $left, array $right): int {
            $comparison = self::compareOrderKeys($left['orderKey'], $right['orderKey']);
            if ($comparison === 0) {
                return $left['position'] <=> $right['position'];
            }

            return $comparison;
        });

        return $ordered;
    }

    private static function compareOrderKeys(mixed $left, mixed $right): int
    {
        if ($left === null || $right === null) {
            return $left === $right ? 0 : ($left === null ? -1 : 1);
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function sqlFilterPasses(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return false;
            }

            return (float) $trimmed != 0.0;
        }

        return true;
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2?:mixed}> $rows
     * @return list<list<array{value:mixed,orderKey:mixed,position:int}>>
     */
    private static function jsonWindowFrameRows(iterable $rows, int $preceding, int $following, string $exclude, string $rowError): array
    {
        self::assertWindowBounds($preceding, $following);
        $excludeMode = strtoupper(trim($exclude));
        if ($excludeMode === '') {
            $excludeMode = 'NO OTHERS';
        }
        if (!in_array($excludeMode, ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'], true)) {
            throw new \InvalidArgumentException('SQLite JSON aggregate window EXCLUDE mode must be NO OTHERS, CURRENT ROW, GROUP, or TIES');
        }

        $ordered = [];
        $position = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists(0, $row) || !array_key_exists(1, $row)) {
                throw new \InvalidArgumentException($rowError);
            }
            $ordered[] = [
                'value' => $row[0],
                'orderKey' => $row[1],
                'filter' => array_key_exists(2, $row) ? $row[2] : true,
                'position' => $position++,
            ];
        }

        usort($ordered, static function (array $left, array $right): int {
            $comparison = self::compareOrderKeys($left['orderKey'], $right['orderKey']);
            if ($comparison === 0) {
                return $left['position'] <=> $right['position'];
            }

            return $comparison;
        });

        $frames = [];
        $lastIndex = count($ordered) - 1;
        foreach ($ordered as $position => $current) {
            $start = max(0, $position - $preceding);
            $end = min($lastIndex, $position + $following);
            $frame = [];
            for ($index = $start; $index <= $end; $index++) {
                $candidate = $ordered[$index];
                $isCurrent = $index === $position;
                $isPeer = self::compareOrderKeys($candidate['orderKey'], $current['orderKey']) === 0;
                if ($excludeMode === 'CURRENT ROW' && $isCurrent) {
                    continue;
                }
                if ($excludeMode === 'GROUP' && $isPeer) {
                    continue;
                }
                if ($excludeMode === 'TIES' && $isPeer && !$isCurrent) {
                    continue;
                }
                if (!self::sqlFilterPasses($candidate['filter'])) {
                    continue;
                }
                $frame[] = $candidate;
            }
            $frames[] = $frame;
        }

        return $frames;
    }

    private static function assertWindowBounds(int $preceding, int $following): void
    {
        if ($preceding < 0 || $following < 0) {
            throw new \InvalidArgumentException('SQLite JSON aggregate window frame bounds must be non-negative');
        }
    }
}
