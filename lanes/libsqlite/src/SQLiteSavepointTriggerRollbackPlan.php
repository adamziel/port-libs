<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSavepointTriggerRollbackPlan
{
    /**
     * @param list<array<string,mixed>> $outerRows
     * @param list<array<string,mixed>> $savepointRows
     * @param list<array<string,mixed>> $inputRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param array{page_size?:int,savepoint_page_images?:array<int,string>,dirty_pages?:array<int,string>,wal_start_frame?:int,wal_frames?:list<array{frame_index:int,page_number:int,commit_frame?:bool}>,recursive_triggers?:bool,max_depth?:int,returning?:list<string>|array<string,string|callable(array<string,mixed>):mixed>} $options
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,changes:int,savepoint:string,savepoint_active_after:bool,transaction_active_after:bool,rolled_back_to_savepoint:bool,rollback_reason:?string,rollback_scope:string,rollback_rows_removed:int,rollback_page_numbers:list<int>,rollback_to_wal_frame:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool}>,restored_page_numbers:list<int>,returning_rows:list<array<string,mixed>>,attempted_returning_rows:list<array<string,mixed>>,returning_columns:list<string>,returning_after_triggers:bool,dependencies:list<string>}
     */
    public static function insertRows(
        array $outerRows,
        array $savepointRows,
        array $inputRows,
        array $triggers,
        array $uniqueColumns,
        string $savepointName,
        array $options = [],
    ): array {
        self::validateUniqueColumns($uniqueColumns);
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback requires a savepoint name');
        }

        $recursiveTriggers = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = (int) ($options['max_depth'] ?? 1000);
        if ($maxDepth < 0) {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback max_depth cannot be negative');
        }

        $rows = array_values($savepointRows);
        $inserted = [];
        $ignored = [];
        $effects = [];
        $attemptedReturningRows = [];
        $returning = $options['returning'] ?? ['*'];

        foreach ($inputRows as $row) {
            $result = self::insertOne($rows, $row, $triggers, $uniqueColumns, $recursiveTriggers, $maxDepth, 0);
            $effects = array_merge($effects, $result['effects']);
            if ($result['inserted'] !== [] && self::sameRow($result['inserted'][0], $row)) {
                $attemptedReturningRows[] = self::projectReturningRow($result['inserted'][0], $returning);
            }

            if ($result['rollback_to_savepoint']) {
                return self::finishRollback($outerRows, $savepointRows, $result['rows'], $effects, $savepointName, (string) $result['reason'], $options, $attemptedReturningRows);
            }

            $rows = $result['rows'];
            $inserted = array_merge($inserted, $result['inserted']);
            $ignored = array_merge($ignored, $result['ignored']);
        }

        return self::finish($rows, $inserted, $ignored, $effects, $savepointName, false, null, 'none', [], 0, [], 0, $attemptedReturningRows, $attemptedReturningRows, self::returningColumnNames($returning));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,rollback_to_savepoint:bool,reason:?string}
     */
    private static function insertOne(
        array $rows,
        array $row,
        array $triggers,
        array $uniqueColumns,
        bool $recursiveTriggers,
        int $maxDepth,
        int $depth,
    ): array {
        if ($depth > $maxDepth) {
            throw new \RuntimeException('SQLite savepoint trigger rollback recursion limit exceeded');
        }

        $inserted = [];
        $ignored = [];
        $effects = [];
        $conflictIndex = self::findConflictIndex($rows, $row, $uniqueColumns);
        if ($conflictIndex !== null) {
            $effects[] = self::effect('insert', 'ignored-conflict', $row, $depth, 'ignore');
            $ignored[] = $row;
            return self::stepResult($rows, $inserted, $ignored, $effects, false, null);
        }

        $rows[] = $row;
        $inserted[] = $row;
        $effects[] = self::effect('insert', 'inserted', $row, $depth, 'abort');

        foreach ($triggers as $trigger) {
            self::validateTrigger($trigger);
            if (strtolower((string) $trigger['timing']) !== 'after' || strtolower((string) $trigger['event']) !== 'insert') {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? null, $row)) {
                $effects[] = self::effect('trigger', 'when-skipped', $row, $depth, 'abort', 'after');
                continue;
            }
            if (($trigger['rollback'] ?? false) === true) {
                $effects[] = self::effect('trigger', 'rollback-current-savepoint', $row, $depth, 'rollback', 'after');
                return self::stepResult($rows, $inserted, $ignored, $effects, true, 'trigger-raise-rollback-current-savepoint');
            }

            $childRow = self::triggerRow((array) $trigger['insert_row'], $row);
            $effects[] = self::effect('trigger', 'fired', $childRow, $depth, 'abort', 'after');

            if (!$recursiveTriggers && $depth > 0) {
                $effects[] = self::effect('trigger', 'recursive-trigger-suppressed', $childRow, $depth, 'abort', 'after');
                continue;
            }

            $child = self::insertOne($rows, $childRow, $triggers, $uniqueColumns, $recursiveTriggers, $maxDepth, $depth + 1);
            $effects = array_merge($effects, $child['effects']);
            if ($child['rollback_to_savepoint']) {
                return self::stepResult($child['rows'], $inserted, $ignored, $effects, true, $child['reason']);
            }
            $rows = $child['rows'];
            $inserted = array_merge($inserted, $child['inserted']);
            $ignored = array_merge($ignored, $child['ignored']);
        }

        return self::stepResult($rows, $inserted, $ignored, $effects, false, null);
    }

    /**
     * @param list<array<string,mixed>> $outerRows
     * @param list<array<string,mixed>> $savepointRows
     * @param list<array<string,mixed>> $dirtyRows
     * @param list<array<string,mixed>> $effects
     * @param array<string,mixed> $options
     * @param list<array<string,mixed>> $attemptedReturningRows
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,changes:int,savepoint:string,savepoint_active_after:bool,transaction_active_after:bool,rolled_back_to_savepoint:bool,rollback_reason:?string,rollback_scope:string,rollback_rows_removed:int,rollback_page_numbers:list<int>,rollback_to_wal_frame:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool}>,restored_page_numbers:list<int>,returning_rows:list<array<string,mixed>>,attempted_returning_rows:list<array<string,mixed>>,returning_columns:list<string>,returning_after_triggers:bool,dependencies:list<string>}
     */
    private static function finishRollback(array $outerRows, array $savepointRows, array $dirtyRows, array $effects, string $savepointName, string $reason, array $options, array $attemptedReturningRows): array
    {
        $pageSize = (int) ($options['page_size'] ?? 512);
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback page size must be positive');
        }

        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('transaction');
        foreach (array_keys($outerRows) as $index) {
            $stack->recordPageWrite($index + 1);
        }
        $walStartFrame = (int) ($options['wal_start_frame'] ?? 0);
        for ($frame = 1; $frame <= $walStartFrame; $frame++) {
            $stack->recordWalFrameWrite($frame, 1);
        }
        $stack->savepoint($savepointName);
        foreach (($options['savepoint_page_images'] ?? []) as $pageNumber => $image) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite savepoint trigger rollback page image keys must be page numbers');
            }
            $stack->recordPageImageWrite($pageNumber, (string) $image);
        }
        foreach (($options['dirty_pages'] ?? []) as $pageNumber => $image) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite savepoint trigger rollback dirty page keys must be page numbers');
            }
            $stack->recordPageImageWrite($pageNumber, (string) $image);
        }
        foreach (($options['wal_frames'] ?? []) as $walFrame) {
            if (!is_array($walFrame) || !isset($walFrame['frame_index'], $walFrame['page_number'])) {
                throw new \InvalidArgumentException('SQLite savepoint trigger rollback WAL frame is malformed');
            }
            $stack->recordWalFrameWrite((int) $walFrame['frame_index'], (int) $walFrame['page_number'], (bool) ($walFrame['commit_frame'] ?? false));
        }

        $imagePlan = $stack->rollbackToImagePlan($savepointName, $pageSize);
        $walPlan = $stack->walRollbackToWithPlan($savepointName);

        return self::finish(
            array_values($savepointRows),
            [],
            [],
            $effects,
            $savepointName,
            true,
            $reason,
            'current-savepoint',
            $imagePlan['restored_page_numbers'],
            max(0, count($dirtyRows) - count($savepointRows)),
            $walPlan['discarded_wal_frames'],
            $walPlan['rollback_to_frame'],
            [],
            $attemptedReturningRows,
            self::returningColumnNames($options['returning'] ?? ['*'])
        );
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $inserted
     * @param list<array<string,mixed>> $ignored
     * @param list<array<string,mixed>> $effects
     * @param list<int> $restoredPageNumbers
     * @param list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}> $discardedWalFrames
     * @param list<array<string,mixed>> $returningRows
     * @param list<array<string,mixed>> $attemptedReturningRows
     * @param list<string> $returningColumns
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,changes:int,savepoint:string,savepoint_active_after:bool,transaction_active_after:bool,rolled_back_to_savepoint:bool,rollback_reason:?string,rollback_scope:string,rollback_rows_removed:int,rollback_page_numbers:list<int>,rollback_to_wal_frame:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool}>,restored_page_numbers:list<int>,returning_rows:list<array<string,mixed>>,attempted_returning_rows:list<array<string,mixed>>,returning_columns:list<string>,returning_after_triggers:bool,dependencies:list<string>}
     */
    private static function finish(
        array $rows,
        array $inserted,
        array $ignored,
        array $effects,
        string $savepointName,
        bool $rolledBack,
        ?string $reason,
        string $scope,
        array $restoredPageNumbers,
        int $rowsRemoved,
        array $discardedWalFrames,
        int $rollbackToWalFrame,
        array $returningRows,
        array $attemptedReturningRows,
        array $returningColumns,
    ): array {
        return [
            'rows' => array_values($rows),
            'inserted' => $rolledBack ? [] : $inserted,
            'ignored' => $rolledBack ? [] : $ignored,
            'effects' => $effects,
            'changes' => $rolledBack ? 0 : count($inserted),
            'savepoint' => $savepointName,
            'savepoint_active_after' => true,
            'transaction_active_after' => true,
            'rolled_back_to_savepoint' => $rolledBack,
            'rollback_reason' => $reason,
            'rollback_scope' => $scope,
            'rollback_rows_removed' => $rowsRemoved,
            'rollback_page_numbers' => $restoredPageNumbers,
            'rollback_to_wal_frame' => $rollbackToWalFrame,
            'discarded_wal_frames' => array_map(static fn (array $frame): array => [
                'frame_index' => $frame['frame_index'],
                'page_number' => $frame['page_number'],
                'commit_frame' => $frame['commit_frame'],
            ], $discardedWalFrames),
            'restored_page_numbers' => $restoredPageNumbers,
            'returning_rows' => $rolledBack ? [] : $returningRows,
            'attempted_returning_rows' => $attemptedReturningRows,
            'returning_columns' => $returningColumns,
            'returning_after_triggers' => false,
            'dependencies' => ['sqlite-savepoint-current-rollback', 'sqlite-trigger-raise-rollback', 'sqlite-returning-current-row'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $inserted
     * @param list<array<string,mixed>> $ignored
     * @param list<array<string,mixed>> $effects
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,rollback_to_savepoint:bool,reason:?string}
     */
    private static function stepResult(array $rows, array $inserted, array $ignored, array $effects, bool $rollbackToSavepoint, ?string $reason): array
    {
        return [
            'rows' => array_values($rows),
            'inserted' => $inserted,
            'ignored' => $ignored,
            'effects' => $effects,
            'rollback_to_savepoint' => $rollbackToSavepoint,
            'reason' => $reason,
        ];
    }

    /**
     * @param array<string,mixed> $trigger
     */
    private static function validateTrigger(array $trigger): void
    {
        if (!in_array(strtolower((string) ($trigger['timing'] ?? '')), ['after'], true)) {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback supports AFTER triggers only');
        }
        if (strtolower((string) ($trigger['event'] ?? '')) !== 'insert') {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback supports INSERT triggers only');
        }
        if (($trigger['table'] ?? null) !== 'target') {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback supports target-table trigger actions only');
        }
        if (($trigger['action'] ?? null) !== 'insert') {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback supports INSERT trigger actions only');
        }
        if (($trigger['rollback'] ?? false) !== true && (!isset($trigger['insert_row']) || !is_array($trigger['insert_row']))) {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback insert_row is required');
        }
    }

    /**
     * @param array<string,mixed> $template
     * @param array<string,mixed> $newRow
     * @return array<string,mixed>
     */
    private static function triggerRow(array $template, array $newRow): array
    {
        $row = [];
        foreach ($template as $column => $value) {
            if (is_string($value) && str_starts_with($value, 'new.')) {
                $sourceColumn = substr($value, 4);
                if (!array_key_exists($sourceColumn, $newRow)) {
                    throw new \InvalidArgumentException("SQLite savepoint trigger rollback NEW column {$sourceColumn} is missing");
                }
                $row[$column] = $newRow[$sourceColumn];
                continue;
            }
            if (is_string($value) && str_starts_with($value, 'new_increment.')) {
                $sourceColumn = substr($value, 14);
                if (!array_key_exists($sourceColumn, $newRow) || !is_int($newRow[$sourceColumn])) {
                    throw new \InvalidArgumentException("SQLite savepoint trigger rollback NEW integer column {$sourceColumn} is missing");
                }
                $row[$column] = $newRow[$sourceColumn] + 1;
                continue;
            }
            if (is_string($value) && str_starts_with($value, 'concat:new.')) {
                [$sourceColumn, $suffix] = array_pad(explode(':', substr($value, 11), 2), 2, '');
                if (!array_key_exists($sourceColumn, $newRow)) {
                    throw new \InvalidArgumentException("SQLite savepoint trigger rollback NEW column {$sourceColumn} is missing");
                }
                $row[$column] = (string) $newRow[$sourceColumn] . $suffix;
                continue;
            }

            $row[$column] = $value;
        }

        return $row;
    }

    /**
     * @param mixed $when
     * @param array<string,mixed> $row
     */
    private static function whenMatches(mixed $when, array $row): bool
    {
        if ($when === null) {
            return true;
        }
        if (!is_array($when)) {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback WHEN clause is malformed');
        }
        $column = (string) ($when['column'] ?? '');
        if ($column === '' || !array_key_exists($column, $row)) {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback WHEN column is missing');
        }
        $operator = strtolower((string) ($when['operator'] ?? '<'));
        $value = $when['value'] ?? null;

        return match ($operator) {
            '<' => $row[$column] < $value,
            '<=' => $row[$column] <= $value,
            '=' => $row[$column] === $value,
            '!=' => $row[$column] !== $value,
            'in' => is_array($value) && in_array($row[$column], $value, true),
            default => throw new \InvalidArgumentException('SQLite savepoint trigger rollback WHEN operator is unsupported'),
        };
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function findConflictIndex(array $rows, array $row, array $columns): ?int
    {
        foreach ($rows as $index => $candidate) {
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $candidate) || $row[$column] !== $candidate[$column]) {
                    continue 2;
                }
            }

            return $index;
        }

        return null;
    }

    /**
     * @param list<string> $columns
     */
    private static function validateUniqueColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite savepoint trigger rollback unique column list cannot be empty');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new \InvalidArgumentException('SQLite savepoint trigger rollback unique column is malformed');
            }
        }
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string>|array<string,string|callable(array<string,mixed>):mixed> $returning
     * @return array<string,mixed>
     */
    private static function projectReturningRow(array $row, array $returning): array
    {
        if ($returning === [] || $returning === ['*']) {
            return $row;
        }

        $projected = [];
        foreach ($returning as $key => $column) {
            if (is_callable($column)) {
                $projected[(string) $key] = $column($row);
                continue;
            }

            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite savepoint trigger rollback RETURNING column is malformed');
            }
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite savepoint trigger rollback RETURNING column {$column} is missing");
            }

            $projected[is_string($key) ? $key : $column] = $row[$column];
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

        $columns = [];
        foreach ($returning as $key => $column) {
            if (is_callable($column)) {
                if (!is_string($key) || $key === '') {
                    throw new \InvalidArgumentException('SQLite savepoint trigger rollback RETURNING expression alias is required');
                }
                $columns[] = $key;
                continue;
            }
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite savepoint trigger rollback RETURNING column is malformed');
            }
            $columns[] = is_string($key) ? $key : $column;
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    private static function sameRow(array $left, array $right): bool
    {
        return json_encode($left, JSON_THROW_ON_ERROR) === json_encode($right, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function effect(string $action, string $result, array $row, int $depth, string $conflictAction, ?string $timing = null): array
    {
        return array_filter([
            'timing' => $timing,
            'action' => $action,
            'result' => $result,
            'depth' => $depth,
            'effective_conflict_action' => $conflictAction,
            'row' => $row,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
