<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteViewTriggerReturningSavepointPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,mixed> $newRow
     * @param list<string>|array<string,string|callable(array<string,mixed>):mixed> $returning
     * @param array{page_size?:int,savepoint_page_images?:array<int,string>,dirty_pages?:array<int,string>,wal_start_frame?:int,wal_frames?:list<array{frame_index:int,page_number:int,commit_frame?:bool}>} $options
     * @return array{tables:array<string,list<array<string,mixed>>>,operations:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,attempted_returning_rows:list<array<string,mixed>>,returning_columns:list<string>,changes:int,savepoint:string,rolled_back_to_savepoint:bool,rollback_reason:?string,rollback_page_numbers:list<int>,rollback_to_wal_frame:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool}>,writes_by_schema:array<string,int>,read_count:int,dependencies:list<string>}
     */
    public static function insertIntoView(
        SQLiteAttachedSchemaCatalog $catalog,
        string $triggerName,
        array $tables,
        array $newRow,
        string $savepointName,
        array $returning = ['*'],
        array $options = [],
    ): array {
        if ($savepointName === '') {
            throw new InvalidArgumentException('SQLite view trigger RETURNING savepoint requires a savepoint name');
        }

        $before = self::normalizeTables($tables);
        $working = $before;
        $yield = SQLiteAttachTempViewTriggerYieldPlan::yield($catalog, $triggerName, $newRow);
        $returningRow = self::projectReturningRow($newRow, $returning);
        $operations = [];
        $rolledBack = false;
        $reason = null;

        foreach ($yield['operations'] as $operation) {
            $operations[] = $operation;
            if (($operation['kind'] ?? '') === 'select') {
                continue;
            }
            if (($operation['kind'] ?? '') === 'insert' && self::isRaiseRollbackRow($operation['row'] ?? [])) {
                $rolledBack = true;
                $reason = 'view-trigger-raise-rollback-current-savepoint';
                break;
            }
            self::applyOperation($working, $operation);
        }

        $rollbackPlan = self::rollbackPlan($savepointName, $options);

        return [
            'tables' => $rolledBack ? $before : self::sortTables($working),
            'operations' => $operations,
            'returning_rows' => $rolledBack ? [] : [$returningRow],
            'attempted_returning_rows' => [$returningRow],
            'returning_columns' => self::returningColumnNames($returning),
            'changes' => $rolledBack ? 0 : self::writeCount($operations),
            'savepoint' => $savepointName,
            'rolled_back_to_savepoint' => $rolledBack,
            'rollback_reason' => $reason,
            'rollback_page_numbers' => $rolledBack ? $rollbackPlan['rollback_page_numbers'] : [],
            'rollback_to_wal_frame' => $rolledBack ? $rollbackPlan['rollback_to_wal_frame'] : 0,
            'discarded_wal_frames' => $rolledBack ? $rollbackPlan['discarded_wal_frames'] : [],
            'writes_by_schema' => self::writesBySchema($operations),
            'read_count' => self::readCount($operations),
            'dependencies' => [
                'sqlite-instead-of-view-trigger-yield',
                'sqlite-returning-current-row',
                'sqlite-savepoint-current-rollback',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        $normalized = [];
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException('SQLite view trigger table key must be schema.table');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new InvalidArgumentException('SQLite view trigger table rows must be arrays');
                }
            }
            $normalized[strtolower($name)] = array_values($rows);
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,mixed> $operation
     */
    private static function applyOperation(array &$tables, array $operation): void
    {
        $key = strtolower((string) ($operation['schema'] ?? '') . '.' . (string) ($operation['table'] ?? ''));
        if (!array_key_exists($key, $tables)) {
            throw new InvalidArgumentException("SQLite view trigger operation target is missing: {$key}");
        }

        $kind = (string) ($operation['kind'] ?? '');
        if ($kind === 'insert') {
            $tables[$key][] = (array) ($operation['row'] ?? []);
            return;
        }

        if ($kind === 'update') {
            $predicate = (array) ($operation['where'] ?? []);
            foreach ($tables[$key] as &$row) {
                if (self::predicateMatches($row, $predicate)) {
                    foreach ((array) ($operation['set'] ?? []) as $column => $value) {
                        $row[(string) $column] = $value;
                    }
                }
            }
            unset($row);
            return;
        }

        if ($kind === 'delete') {
            $predicate = (array) ($operation['where'] ?? []);
            $tables[$key] = array_values(array_filter(
                $tables[$key],
                static fn (array $row): bool => !self::predicateMatches($row, $predicate)
            ));
            return;
        }

        throw new InvalidArgumentException('SQLite view trigger operation kind is unsupported');
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function predicateMatches(array $row, array $predicate): bool
    {
        $column = (string) ($predicate['column'] ?? '');
        if ($column === '' || !array_key_exists($column, $row)) {
            throw new InvalidArgumentException('SQLite view trigger predicate column is missing');
        }
        $value = $predicate['value'] ?? null;
        $operator = strtoupper((string) ($predicate['operator'] ?? '='));

        return $operator === 'IS' ? $row[$column] === $value : $row[$column] == $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function isRaiseRollbackRow(array $row): bool
    {
        return ($row['raise'] ?? null) === 'rollback'
            || ($row['action'] ?? null) === 'rollback-current-savepoint'
            || ($row['label'] ?? null) === 'rollback-current-savepoint';
    }

    /**
     * @param list<array<string,mixed>> $operations
     */
    private static function writeCount(array $operations): int
    {
        return count(array_filter($operations, static fn (array $operation): bool => ($operation['kind'] ?? '') !== 'select'));
    }

    /**
     * @param list<array<string,mixed>> $operations
     * @return array<string,int>
     */
    private static function writesBySchema(array $operations): array
    {
        $counts = [];
        foreach ($operations as $operation) {
            if (($operation['kind'] ?? '') === 'select') {
                continue;
            }
            $schema = (string) ($operation['schema'] ?? '');
            $counts[$schema] = ($counts[$schema] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $operations
     */
    private static function readCount(array $operations): int
    {
        return count(array_filter($operations, static fn (array $operation): bool => ($operation['kind'] ?? '') === 'select'));
    }

    /**
     * @param list<string>|array<string,string|callable(array<string,mixed>):mixed> $returning
     * @return array<string,mixed>
     */
    private static function projectReturningRow(array $row, array $returning): array
    {
        if ($returning === [] || $returning === ['*']) {
            return $row;
        }

        $projected = [];
        foreach ($returning as $alias => $column) {
            if (is_int($alias)) {
                if (!is_string($column) || $column === '' || !array_key_exists($column, $row)) {
                    throw new InvalidArgumentException('SQLite view trigger RETURNING column is missing');
                }
                $projected[$column] = $row[$column];
                continue;
            }
            if (is_callable($column)) {
                $projected[$alias] = $column($row);
                continue;
            }
            if (!is_string($column) || $column === '' || !array_key_exists($column, $row)) {
                throw new InvalidArgumentException('SQLite view trigger RETURNING expression is missing');
            }
            $projected[$alias] = $row[$column];
        }

        return $projected;
    }

    /**
     * @param list<string>|array<string,string|callable(array<string,mixed>):mixed> $returning
     * @return list<string>
     */
    private static function returningColumnNames(array $returning): array
    {
        if ($returning === [] || $returning === ['*']) {
            return ['*'];
        }

        $names = [];
        foreach ($returning as $alias => $column) {
            if (is_int($alias)) {
                if (!is_string($column) || $column === '') {
                    throw new InvalidArgumentException('SQLite view trigger RETURNING column is malformed');
                }
                $names[] = $column;
                continue;
            }
            $names[] = (string) $alias;
        }

        return $names;
    }

    /**
     * @param array{page_size?:int,savepoint_page_images?:array<int,string>,dirty_pages?:array<int,string>,wal_start_frame?:int,wal_frames?:list<array{frame_index:int,page_number:int,commit_frame?:bool}>} $options
     * @return array{rollback_page_numbers:list<int>,rollback_to_wal_frame:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool}>}
     */
    private static function rollbackPlan(string $savepointName, array $options): array
    {
        $pageSize = (int) ($options['page_size'] ?? 512);
        if ($pageSize < 1) {
            throw new InvalidArgumentException('SQLite view trigger savepoint page size must be positive');
        }

        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('transaction');
        $walStartFrame = (int) ($options['wal_start_frame'] ?? 0);
        for ($frame = 1; $frame <= $walStartFrame; ++$frame) {
            $stack->recordWalFrameWrite($frame, 1);
        }
        $stack->savepoint($savepointName);
        foreach (($options['savepoint_page_images'] ?? []) as $pageNumber => $image) {
            if (!is_int($pageNumber)) {
                throw new InvalidArgumentException('SQLite view trigger savepoint image keys must be page numbers');
            }
            $stack->recordPageImageWrite($pageNumber, (string) $image);
        }
        foreach (($options['dirty_pages'] ?? []) as $pageNumber => $image) {
            if (!is_int($pageNumber)) {
                throw new InvalidArgumentException('SQLite view trigger dirty page keys must be page numbers');
            }
            $stack->recordPageImageWrite($pageNumber, (string) $image);
        }
        foreach (($options['wal_frames'] ?? []) as $walFrame) {
            if (!is_array($walFrame) || !isset($walFrame['frame_index'], $walFrame['page_number'])) {
                throw new InvalidArgumentException('SQLite view trigger WAL frame is malformed');
            }
            $stack->recordWalFrameWrite((int) $walFrame['frame_index'], (int) $walFrame['page_number'], (bool) ($walFrame['commit_frame'] ?? false));
        }

        $imagePlan = $stack->rollbackToImagePlan($savepointName, $pageSize);
        $walPlan = $stack->walRollbackToWithPlan($savepointName);

        return [
            'rollback_page_numbers' => $imagePlan['restored_page_numbers'],
            'rollback_to_wal_frame' => $walPlan['rollback_to_frame'],
            'discarded_wal_frames' => array_map(
                static fn (array $frame): array => [
                    'frame_index' => (int) $frame['frame_index'],
                    'page_number' => (int) $frame['page_number'],
                    'commit_frame' => (bool) $frame['commit_frame'],
                ],
                $walPlan['discarded_wal_frames']
            ),
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function sortTables(array $tables): array
    {
        ksort($tables);

        return $tables;
    }
}
