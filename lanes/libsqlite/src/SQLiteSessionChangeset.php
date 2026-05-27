<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSessionChangeset
{
    private const TYPE_UNDEFINED = 0;
    private const TYPE_NULL = 1;
    private const TYPE_INT = 2;
    private const TYPE_FLOAT = 3;
    private const TYPE_TEXT = 4;
    private const TYPE_BLOB = 5;

    /**
     * @param list<string> $columns
     * @param list<string> $primaryKey
     * @param list<array<string, mixed>> $beforeRows
     * @param list<array<string, mixed>> $afterRows
     * @return array{table:string, columns:list<string>, primary_key:list<string>, changes:list<array<string, mixed>>}
     */
    public static function diff(string $table, array $columns, array $primaryKey, array $beforeRows, array $afterRows): array
    {
        self::assertTableShape($table, $columns, $primaryKey);
        $beforeByKey = self::indexRows($beforeRows, $primaryKey);
        $afterByKey = self::indexRows($afterRows, $primaryKey);
        $changes = [];

        foreach ($beforeByKey as $key => $before) {
            if (!isset($afterByKey[$key])) {
                $changes[] = ['op' => 'delete', 'old' => self::projectRow($before, $columns)];
                continue;
            }

            $after = $afterByKey[$key];
            $old = [];
            $new = [];
            foreach ($columns as $column) {
                $beforeValue = $before[$column] ?? null;
                $afterValue = $after[$column] ?? null;
                if ($beforeValue !== $afterValue) {
                    $old[$column] = $beforeValue;
                    $new[$column] = $afterValue;
                } else {
                    $old[$column] = ['undefined' => true];
                    $new[$column] = ['undefined' => true];
                }
            }

            if (self::hasDefinedValue($old)) {
                foreach ($primaryKey as $column) {
                    $old[$column] = $before[$column] ?? null;
                    $new[$column] = $after[$column] ?? null;
                }
                $changes[] = ['op' => 'update', 'old' => $old, 'new' => $new];
            }
        }

        foreach ($afterByKey as $key => $after) {
            if (!isset($beforeByKey[$key])) {
                $changes[] = ['op' => 'insert', 'new' => self::projectRow($after, $columns)];
            }
        }

        return [
            'table' => $table,
            'columns' => array_values($columns),
            'primary_key' => array_values($primaryKey),
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array{table:string, columns:list<string>, primary_key:list<string>, changes:list<array<string, mixed>>}> $tables
     */
    public static function encode(array $tables): string
    {
        $bytes = '';
        foreach ($tables as $table) {
            self::assertTableShape($table['table'], $table['columns'], $table['primary_key']);
            $pkMap = array_fill_keys($table['primary_key'], true);
            $bytes .= 'T' . SQLiteVarint::encode(count($table['columns']));
            foreach ($table['columns'] as $column) {
                $bytes .= isset($pkMap[$column]) ? "\x01" : "\x00";
            }
            $bytes .= SQLiteVarint::encode(strlen($table['table'])) . $table['table'];

            foreach ($table['changes'] as $change) {
                $op = $change['op'] ?? null;
                if ($op === 'insert') {
                    $bytes .= 'I' . self::encodeRecord($table['columns'], $change['new'] ?? []);
                } elseif ($op === 'delete') {
                    $bytes .= 'D' . self::encodeRecord($table['columns'], $change['old'] ?? []);
                } elseif ($op === 'update') {
                    $bytes .= 'U' . self::encodeRecord($table['columns'], $change['old'] ?? []) . self::encodeRecord($table['columns'], $change['new'] ?? []);
                } else {
                    throw new \InvalidArgumentException('Unsupported changeset operation');
                }
            }
        }

        return $bytes;
    }

    /**
     * @return list<array{table:string, columns:list<string>, primary_key:list<string>, changes:list<array<string, mixed>>}>
     */
    public static function decode(string $bytes): array
    {
        $offset = 0;
        $tables = [];
        $current = null;

        while ($offset < strlen($bytes)) {
            $tag = $bytes[$offset++];
            if ($tag === 'T') {
                if ($current !== null) {
                    $tables[] = $current;
                }
                [$columnCount, $length] = SQLiteVarint::decode($bytes, $offset);
                $offset += $length;
                if ($columnCount < 1 || $offset + $columnCount > strlen($bytes)) {
                    throw new \InvalidArgumentException('Malformed changeset table header');
                }
                $pkFlags = [];
                for ($i = 0; $i < $columnCount; $i++) {
                    $pkFlags[] = ord($bytes[$offset++]) !== 0;
                }
                [$nameLength, $length] = SQLiteVarint::decode($bytes, $offset);
                $offset += $length;
                $table = substr($bytes, $offset, $nameLength);
                if (strlen($table) !== $nameLength) {
                    throw new \InvalidArgumentException('Truncated changeset table name');
                }
                $offset += $nameLength;
                $columns = [];
                $primaryKey = [];
                for ($i = 0; $i < $columnCount; $i++) {
                    $column = 'c' . $i;
                    $columns[] = $column;
                    if ($pkFlags[$i]) {
                        $primaryKey[] = $column;
                    }
                }
                $current = ['table' => $table, 'columns' => $columns, 'primary_key' => $primaryKey, 'changes' => []];
                continue;
            }

            if ($current === null) {
                throw new \InvalidArgumentException('Changeset operation encountered before table header');
            }
            if ($tag === 'I') {
                [$record, $offset] = self::decodeRecord($bytes, $offset, $current['columns']);
                $current['changes'][] = ['op' => 'insert', 'new' => $record];
            } elseif ($tag === 'D') {
                [$record, $offset] = self::decodeRecord($bytes, $offset, $current['columns']);
                $current['changes'][] = ['op' => 'delete', 'old' => $record];
            } elseif ($tag === 'U') {
                [$old, $offset] = self::decodeRecord($bytes, $offset, $current['columns']);
                [$new, $offset] = self::decodeRecord($bytes, $offset, $current['columns']);
                $current['changes'][] = ['op' => 'update', 'old' => $old, 'new' => $new];
            } else {
                throw new \InvalidArgumentException('Unsupported changeset tag');
            }
        }

        if ($current !== null) {
            $tables[] = $current;
        }

        return $tables;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array{table:string, columns:list<string>, primary_key:list<string>, changes:list<array<string, mixed>>} $changeset
     * @return array{rows:list<array<string, mixed>>, applied:list<array<string, mixed>>, conflicts:list<array<string, mixed>>}
     */
    public static function apply(array $rows, array $changeset, string $conflictPolicy = 'omit'): array
    {
        self::assertTableShape($changeset['table'], $changeset['columns'], $changeset['primary_key']);
        if (!in_array($conflictPolicy, ['omit', 'replace', 'abort'], true)) {
            throw new \InvalidArgumentException('Unsupported changeset conflict policy');
        }

        $byKey = self::indexRows($rows, $changeset['primary_key']);
        $applied = [];
        $conflicts = [];
        foreach ($changeset['changes'] as $change) {
            $op = $change['op'] ?? null;
            $probe = $op === 'insert' ? ($change['new'] ?? []) : ($change['old'] ?? []);
            $key = self::rowKey($probe, $changeset['primary_key']);
            $exists = isset($byKey[$key]);

            if ($op === 'insert') {
                if ($exists && $conflictPolicy !== 'replace') {
                    $conflict = ['type' => 'conflict', 'op' => 'insert', 'key' => $key];
                    if ($conflictPolicy === 'abort') {
                        throw new \RuntimeException('Changeset insert conflict: ' . $key);
                    }
                    $conflicts[] = $conflict;
                    continue;
                }
                $byKey[$key] = self::projectRow($change['new'], $changeset['columns']);
            } elseif ($op === 'delete') {
                if (!$exists) {
                    $conflicts[] = ['type' => 'notfound', 'op' => 'delete', 'key' => $key];
                    continue;
                }
                if (!self::rowsMatch($byKey[$key], $change['old'], $changeset['columns'])) {
                    $conflicts[] = ['type' => 'data', 'op' => 'delete', 'key' => $key];
                    continue;
                }
                unset($byKey[$key]);
            } elseif ($op === 'update') {
                if (!$exists) {
                    $conflicts[] = ['type' => 'notfound', 'op' => 'update', 'key' => $key];
                    continue;
                }
                if (!self::rowsMatch($byKey[$key], $change['old'], $changeset['columns'])) {
                    $conflicts[] = ['type' => 'data', 'op' => 'update', 'key' => $key];
                    continue;
                }
                $byKey[$key] = self::mergeUpdate($byKey[$key], $change['new'], $changeset['columns']);
            } else {
                throw new \InvalidArgumentException('Unsupported changeset operation');
            }
            $applied[] = ['op' => $op, 'key' => $key];
        }

        return ['rows' => array_values($byKey), 'applied' => $applied, 'conflicts' => $conflicts];
    }

    /**
     * @param list<string> $columns
     * @param array<string, mixed> $record
     */
    private static function encodeRecord(array $columns, array $record): string
    {
        $bytes = '';
        foreach ($columns as $column) {
            $value = array_key_exists($column, $record) ? $record[$column] : ['undefined' => true];
            if (is_array($value) && ($value['undefined'] ?? false) === true) {
                $bytes .= chr(self::TYPE_UNDEFINED);
            } elseif ($value === null) {
                $bytes .= chr(self::TYPE_NULL);
            } elseif (is_int($value)) {
                $bytes .= chr(self::TYPE_INT) . pack('J', $value);
            } elseif (is_float($value)) {
                $bytes .= chr(self::TYPE_FLOAT) . pack('E', $value);
            } elseif (is_array($value) && isset($value['blob']) && is_string($value['blob'])) {
                $bytes .= chr(self::TYPE_BLOB) . SQLiteVarint::encode(strlen($value['blob'])) . $value['blob'];
            } elseif (is_string($value)) {
                $bytes .= chr(self::TYPE_TEXT) . SQLiteVarint::encode(strlen($value)) . $value;
            } else {
                throw new \InvalidArgumentException('Unsupported changeset value');
            }
        }

        return $bytes;
    }

    /**
     * @param list<string> $columns
     * @return array{0:array<string, mixed>, 1:int}
     */
    private static function decodeRecord(string $bytes, int $offset, array $columns): array
    {
        $record = [];
        foreach ($columns as $column) {
            if (!isset($bytes[$offset])) {
                throw new \InvalidArgumentException('Truncated changeset record');
            }
            $type = ord($bytes[$offset++]);
            if ($type === self::TYPE_UNDEFINED) {
                $record[$column] = ['undefined' => true];
            } elseif ($type === self::TYPE_NULL) {
                $record[$column] = null;
            } elseif ($type === self::TYPE_INT) {
                $chunk = substr($bytes, $offset, 8);
                if (strlen($chunk) !== 8) {
                    throw new \InvalidArgumentException('Truncated changeset integer');
                }
                $record[$column] = unpack('J', $chunk)[1];
                $offset += 8;
            } elseif ($type === self::TYPE_FLOAT) {
                $chunk = substr($bytes, $offset, 8);
                if (strlen($chunk) !== 8) {
                    throw new \InvalidArgumentException('Truncated changeset float');
                }
                $record[$column] = unpack('E', $chunk)[1];
                $offset += 8;
            } elseif ($type === self::TYPE_TEXT || $type === self::TYPE_BLOB) {
                [$length, $used] = SQLiteVarint::decode($bytes, $offset);
                $offset += $used;
                $value = substr($bytes, $offset, $length);
                if (strlen($value) !== $length) {
                    throw new \InvalidArgumentException('Truncated changeset bytes');
                }
                $record[$column] = $type === self::TYPE_BLOB ? ['blob' => $value] : $value;
                $offset += $length;
            } else {
                throw new \InvalidArgumentException('Unsupported changeset value type');
            }
        }

        return [$record, $offset];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $primaryKey
     * @return array<string, array<string, mixed>>
     */
    private static function indexRows(array $rows, array $primaryKey): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[self::rowKey($row, $primaryKey)] = $row;
        }

        return $indexed;
    }

    /** @param list<string> $primaryKey */
    private static function rowKey(array $row, array $primaryKey): string
    {
        $parts = [];
        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException('Changeset row is missing primary key column');
            }
            $parts[] = json_encode($row[$column], JSON_THROW_ON_ERROR);
        }

        return implode("\x1f", $parts);
    }

    /** @param list<string> $columns */
    private static function projectRow(array $row, array $columns): array
    {
        $projected = [];
        foreach ($columns as $column) {
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    /** @param list<string> $columns */
    private static function rowsMatch(array $current, array $old, array $columns): bool
    {
        foreach ($columns as $column) {
            if (is_array($old[$column] ?? null) && (($old[$column]['undefined'] ?? false) === true)) {
                continue;
            }
            if (($current[$column] ?? null) !== ($old[$column] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $columns */
    private static function mergeUpdate(array $current, array $new, array $columns): array
    {
        foreach ($columns as $column) {
            if (is_array($new[$column] ?? null) && (($new[$column]['undefined'] ?? false) === true)) {
                continue;
            }
            $current[$column] = $new[$column] ?? null;
        }

        return $current;
    }

    private static function hasDefinedValue(array $record): bool
    {
        foreach ($record as $value) {
            if (!is_array($value) || ($value['undefined'] ?? false) !== true) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $columns @param list<string> $primaryKey */
    private static function assertTableShape(string $table, array $columns, array $primaryKey): void
    {
        if ($table === '' || $columns === [] || $primaryKey === []) {
            throw new \InvalidArgumentException('Changeset table requires a name, columns, and primary key');
        }
        $columnMap = array_fill_keys($columns, true);
        foreach ($primaryKey as $column) {
            if (!isset($columnMap[$column])) {
                throw new \InvalidArgumentException('Changeset primary key column is not present in table columns');
            }
        }
    }
}
