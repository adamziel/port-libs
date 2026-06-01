<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonPathIndexedUpdatePlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name:string,path:string,column?:string,collation?:string,unique?:bool}> $indexes
     * @param list<array{rowid:int|string,column?:string,mutations:list<array{function:string,path:string,value:mixed}>}> $updates
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,index_updates:list<array<string,mixed>>,changed_rows:list<array<string,mixed>>,changes:int}
     */
    public static function plan(array $rows, array $indexes, array $updates): array
    {
        self::validateIndexes($indexes);
        $before = array_values($rows);
        $after = $before;
        $positions = self::rowPositions($after);
        $indexUpdates = [];
        $changedRows = [];

        foreach ($updates as $update) {
            $rowid = $update['rowid'] ?? null;
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException('SQLite JSON indexed UPDATE rowid must be integer or text');
            }
            if (!array_key_exists((string) $rowid, $positions)) {
                continue;
            }

            $position = $positions[(string) $rowid];
            $rowBefore = $after[$position];
            $mutationColumn = self::mutationColumn($update);
            $json = self::jsonColumn($rowBefore, $mutationColumn);
            foreach ($update['mutations'] ?? [] as $mutation) {
                $function = strtolower($mutation['function'] ?? '');
                $path = $mutation['path'] ?? null;
                if (!is_string($path)) {
                    throw new \InvalidArgumentException('SQLite JSON indexed UPDATE mutation path must be text');
                }
                if ($function === 'json_remove' || $function === 'jsonb_remove') {
                    $json = SQLiteJsonRemove::removeSqlFunction($function, $json, $path);
                } else {
                    $json = SQLiteJsonMutation::mutateSqlFunction($function, $json, $path, $mutation['value'] ?? null);
                }
            }
            if ($json instanceof SQLiteBlobValue) {
                $json = SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decode($json->bytes));
            }

            $rowAfter = $rowBefore;
            $rowAfter[$mutationColumn] = $json;
            $after[$position] = $rowAfter;
            $changedRows[] = $rowAfter;

            foreach ($indexes as $index) {
                $column = $index['column'] ?? 'key_value';
                $current = self::indexKey($rowBefore, $column, $index['path']);
                $next = self::indexKey($rowAfter, $column, $index['path']);
                if ($current === $next) {
                    continue;
                }

                $indexUpdates[] = [
                    'index' => $index['name'],
                    'rowid' => $rowid,
                    'path' => $index['path'],
                    'current' => $current,
                    'next' => $next,
                    'delete' => $current !== null,
                    'insert' => $next !== null,
                    'collation' => strtoupper($index['collation'] ?? 'BINARY'),
                    'unique' => (bool) ($index['unique'] ?? false),
                ];
            }
        }

        self::assertUniqueNextKeys($indexUpdates, $after, $indexes);

        return [
            'before' => $before,
            'after' => $after,
            'index_updates' => $indexUpdates,
            'changed_rows' => $changedRows,
            'changes' => count($changedRows),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function rowPositions(array $rows): array
    {
        $positions = [];
        foreach ($rows as $position => $row) {
            $rowid = $row['setting_id'] ?? $row['rowid'] ?? null;
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException('SQLite JSON indexed UPDATE rows need setting_id or rowid');
            }
            $key = (string) $rowid;
            if (array_key_exists($key, $positions)) {
                throw new \InvalidArgumentException('SQLite JSON indexed UPDATE rows need unique rowids');
            }
            $positions[$key] = $position;
        }

        return $positions;
    }

    /**
     * @param list<array{name:string,path:string,column?:string,collation?:string,unique?:bool}> $indexes
     */
    private static function validateIndexes(array $indexes): void
    {
        foreach ($indexes as $index) {
            if (($index['name'] ?? '') === '' || !is_string($index['name'] ?? null)) {
                throw new \InvalidArgumentException('SQLite JSON indexed UPDATE index name must be text');
            }
            if (($index['path'] ?? '') === '' || !is_string($index['path'] ?? null)) {
                throw new \InvalidArgumentException('SQLite JSON indexed UPDATE index path must be text');
            }
            SQLiteJsonInspection::locatePath('{}', $index['path']);
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function jsonColumn(array $row, string $column): string|SQLiteBlobValue|null
    {
        $value = $row[$column] ?? null;
        if ($value !== null && !is_string($value) && !$value instanceof SQLiteBlobValue) {
            throw new \InvalidArgumentException('SQLite JSON indexed UPDATE column must be text JSON, JSONB, or NULL');
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $update
     */
    private static function mutationColumn(array $update): string
    {
        $column = $update['column'] ?? 'key_value';
        if (!is_string($column) || $column === '') {
            throw new \InvalidArgumentException('SQLite JSON indexed UPDATE mutation column must be text');
        }

        return $column;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function indexKey(array $row, string $column, string $path): mixed
    {
        return self::canonicalKey(SQLiteJsonExtract::extract(self::jsonColumn($row, $column), $path));
    }

    private static function canonicalKey(mixed $value): mixed
    {
        if ($value instanceof SQLiteBlobValue) {
            return ['jsonb' => bin2hex($value->bytes)];
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $indexUpdates
     * @param list<array<string,mixed>> $rows
     * @param list<array{name:string,path:string,column?:string,collation?:string,unique?:bool}> $indexes
     */
    private static function assertUniqueNextKeys(array $indexUpdates, array $rows, array $indexes): void
    {
        $uniqueNames = [];
        foreach ($indexes as $index) {
            if (($index['unique'] ?? false) === true) {
                $uniqueNames[$index['name']] = $index;
            }
        }
        if ($uniqueNames === []) {
            return;
        }

        foreach ($uniqueNames as $name => $index) {
            $seen = [];
            foreach ($rows as $row) {
                $key = self::indexKey($row, $index['column'] ?? 'key_value', $index['path']);
                if ($key === null) {
                    continue;
                }
                $fingerprint = is_scalar($key) ? get_debug_type($key) . ':' . (string) $key : serialize($key);
                if (array_key_exists($fingerprint, $seen)) {
                    throw new \InvalidArgumentException("SQLite JSON indexed UPDATE unique index {$name} conflict");
                }
                $seen[$fingerprint] = true;
            }
        }
    }
}
