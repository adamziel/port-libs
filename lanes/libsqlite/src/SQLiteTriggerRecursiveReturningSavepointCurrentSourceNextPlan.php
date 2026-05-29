<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan
{

    /* Variant consolidated from SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $initialRows
     * @param list<array<string,mixed>> $inputRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,int):mixed> $returning
     * @param array{savepoint?:string,rollback_to?:bool,recursive_triggers?:bool,max_depth?:int,current_source?:string,next_source?:string,conflict_action?:string} $options
     * @return array<string,mixed>
     */
    public static function insertRowsWithinSavepointNext139(
        array $initialRows,
        array $inputRows,
        array $triggers,
        array $uniqueColumns,
        array $returning = ['*'],
        array $options = [],
    ): array {
        $savepoint = self::identifierNext139((string) ($options['savepoint'] ?? 'wp_recursive_import'), 'savepoint');
        $rollbackTo = (bool) ($options['rollback_to'] ?? true);
        $currentSource = (string) ($options['current_source'] ?? 'current-recursive-trigger-returning');
        $nextSource = (string) ($options['next_source'] ?? 'next-after-recursive-trigger-savepoint');
        $conflictAction = (string) ($options['conflict_action'] ?? 'abort');

        $beforeRows = array_values($initialRows);
        $statement = SQLiteDmlTriggerRecursionPlan::insertRows(
            $beforeRows,
            array_values($inputRows),
            $triggers,
            $uniqueColumns,
            $conflictAction,
            [
                'recursive_triggers' => (bool) ($options['recursive_triggers'] ?? true),
                'max_depth' => (int) ($options['max_depth'] ?? 1000),
            ],
        );

        $returningRows = self::returningRowsNext139($statement['inserted'], $statement['effects'], $returning);
        $afterRows = $rollbackTo ? $beforeRows : $statement['rows'];

        return [
            'savepoint' => $savepoint,
            'rolled_back' => $rollbackTo,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'before' => $beforeRows,
            'after_statement' => $statement['rows'],
            'after_savepoint' => $afterRows,
            'returning_rows' => $returningRows,
            'yielded' => self::yieldedRowsNext139($returningRows, $savepoint, $rollbackTo),
            'inserted_before_rollback' => $statement['inserted'],
            'trigger_effects_before_rollback' => $statement['effects'],
            'ignored_before_rollback' => $statement['ignored'],
            'changes_before_rollback' => $statement['changes'],
            'discarded_returning_count' => $rollbackTo ? count($returningRows) : 0,
            'restored_unique_keys' => self::uniqueKeysNext139($afterRows, $uniqueColumns),
            'statement_unique_keys' => self::uniqueKeysNext139($statement['rows'], $uniqueColumns),
            'recursive_triggers' => $statement['recursive_triggers'],
            'max_depth' => $statement['max_depth'],
            'dependencies' => [
                'sqlite-dml-trigger-recursion-corpus',
                'sqlite-trigger-deferred-returning-savepoint-current-source-next119',
                'sqlite-trigger-recursive-returning-savepoint-current-source-next139',
                'sqlite-returning-yield-before-rollback-to-savepoint',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $effects
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,int):mixed> $returning
     * @return list<array<string,mixed>>
     */
    private static function returningRowsNext139(array $rows, array $effects, array $returning): array
    {
        $depths = [];
        foreach ($effects as $effect) {
            if (($effect['action'] ?? null) === 'insert' && in_array($effect['result'] ?? null, ['inserted', 'replaced-conflict'], true)) {
                $depths[] = (int) $effect['depth'];
            }
        }

        $result = [];
        foreach ($rows as $index => $row) {
            $resultRow = [];
            foreach ($returning as $returningIndex => $expr) {
                if ($expr === '*') {
                    foreach ($row as $column => $value) {
                        $resultRow[(string) $column] = $value;
                    }
                    continue;
                }
                if (is_callable($expr)) {
                    $resultRow['expr' . $returningIndex] = $expr($row, $index, $depths[$index] ?? $index);
                    continue;
                }

                $column = is_array($expr) ? (string) $expr['expr'] : (string) $expr;
                $alias = is_array($expr) ? (string) ($expr['as'] ?? $column) : $column;
                if (str_starts_with($column, 'new.')) {
                    $column = substr($column, 4);
                    if (!is_array($expr)) {
                        $alias = $column;
                    }
                }
                $column = self::identifierNext139($column, 'returning column');
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite recursive trigger RETURNING column {$column} is missing");
                }
                $resultRow[$alias] = $row[$column];
            }
            $result[] = $resultRow;
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $returningRows
     * @return list<array<string,mixed>>
     */
    private static function yieldedRowsNext139(array $returningRows, string $savepoint, bool $rolledBack): array
    {
        $yielded = [];
        foreach ($returningRows as $index => $row) {
            $yielded[] = [
                'statement' => $index,
                'savepoint' => $savepoint,
                'rolled_back_after_yield' => $rolledBack,
                'row' => $row,
            ];
        }

        return $yielded;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function uniqueKeysNext139(array $rows, array $columns): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite recursive trigger RETURNING unique columns cannot be empty');
        }

        $keys = [];
        foreach ($rows as $row) {
            $parts = [];
            foreach ($columns as $column) {
                $column = self::identifierNext139($column, 'unique column');
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite recursive trigger RETURNING unique column {$column} is missing");
                }
                $parts[] = (string) $row[$column];
            }
            $keys[] = implode("\0", $parts);
        }

        return $keys;
    }

    private static function identifierNext139(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("SQLite recursive trigger RETURNING savepoint {$label} is malformed");
        }

        return $identifier;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $savepointRows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,int):mixed> $returning
     * @param array{savepoint?:string,current_source?:string,next_source?:string,rollback_current?:bool,rollback_next?:bool,recursive_triggers?:bool,max_depth?:int,conflict_action?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext147(
        array $savepointRows,
        array $currentRows,
        array $nextRows,
        array $triggers,
        array $uniqueColumns,
        array $returning,
        array $options = [],
    ): array {
        $savepoint = self::identifierNext147((string) ($options['savepoint'] ?? 'wp_recursive_returning_batch'), 'savepoint');
        $currentSource = self::sourceNext147((string) ($options['current_source'] ?? 'current-recursive-returning'));
        $nextSource = self::sourceNext147((string) ($options['next_source'] ?? 'next-recursive-returning'));
        $rollbackCurrent = (bool) ($options['rollback_current'] ?? true);
        $rollbackNext = (bool) ($options['rollback_next'] ?? false);

        if ($savepointRows === [] || $currentRows === [] || $nextRows === []) {
            throw new InvalidArgumentException('SQLite trigger recursive RETURNING next147 requires savepoint, current, and next rows');
        }
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite trigger recursive RETURNING next147 projection cannot be empty');
        }

        $shared = [
            'savepoint' => $savepoint,
            'recursive_triggers' => (bool) ($options['recursive_triggers'] ?? true),
            'max_depth' => (int) ($options['max_depth'] ?? 1000),
            'conflict_action' => (string) ($options['conflict_action'] ?? 'abort'),
            'current_source' => $currentSource,
            'next_source' => $nextSource,
        ];

        $savepointImage = array_values($savepointRows);
        $current = SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepointNext139(
            $savepointImage,
            array_values($currentRows),
            $triggers,
            $uniqueColumns,
            $returning,
            $shared + ['rollback_to' => $rollbackCurrent],
        );
        $nextBaseRows = $rollbackCurrent ? $savepointImage : $current['after_statement'];
        $next = SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepointNext139(
            $nextBaseRows,
            array_values($nextRows),
            $triggers,
            $uniqueColumns,
            $returning,
            $shared + ['rollback_to' => $rollbackNext],
        );

        $currentStream = self::streamNext147($current['yielded'], 'current', $currentSource, !$rollbackCurrent);
        $nextStream = self::streamNext147($next['yielded'], 'next', $nextSource, !$rollbackNext);
        $attempted = array_merge($currentStream, $nextStream);
        $admitted = array_values(array_filter($attempted, static fn (array $row): bool => $row['admitted']));
        $suppressed = array_values(array_filter($attempted, static fn (array $row): bool => !$row['admitted']));
        $finalRows = $rollbackNext ? $nextBaseRows : $next['after_statement'];

        return [
            'status' => self::statusNext147($rollbackCurrent, $rollbackNext),
            'savepoint' => $savepoint,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'rollback_current' => $rollbackCurrent,
            'rollback_next' => $rollbackNext,
            'savepoint_rows' => $savepointImage,
            'current' => $current,
            'next' => $next,
            'next_base_rows' => $nextBaseRows,
            'final_rows' => array_values($finalRows),
            'current_stream' => $currentStream,
            'next_stream' => $nextStream,
            'attempted_returning_stream' => $attempted,
            'admitted_returning_stream' => $admitted,
            'suppressed_returning_stream' => $suppressed,
            'returning_rows' => self::rowsNext147($admitted),
            'suppressed_returning_rows' => self::rowsNext147($suppressed),
            'source_transition' => [
                'savepoint' => $savepoint,
                'current' => $currentSource,
                'next' => $nextSource,
                'next_started_from' => $rollbackCurrent ? 'savepoint-current-source' : 'current-statement-output',
                'returning_rows_yield_before_rollback' => true,
                'current_returning_visibility' => $rollbackCurrent ? 'suppressed-after-rollback-to' : 'admitted',
                'next_returning_visibility' => $rollbackNext ? 'suppressed-after-rollback-to' : 'admitted',
                'visible_source' => $rollbackNext ? ($rollbackCurrent ? $currentSource : $nextSource . ':rolled-back') : $nextSource,
            ],
            'changes_before_rollback' => [
                'current' => $current['changes_before_rollback'],
                'next' => $next['changes_before_rollback'],
                'attempted' => $current['changes_before_rollback'] + $next['changes_before_rollback'],
                'admitted' => ($rollbackCurrent ? 0 : $current['changes_before_rollback']) + ($rollbackNext ? 0 : $next['changes_before_rollback']),
            ],
            'discarded_returning_count' => $current['discarded_returning_count'] + $next['discarded_returning_count'],
            'dependency_closure' => 'no-new-support-component-reuses-native-recursive-trigger-returning-savepoint-current-source',
            'dependencies' => array_values(array_unique(array_merge(
                (array) ($current['dependencies'] ?? []),
                (array) ($next['dependencies'] ?? []),
                [
                    'sqlite-trigger-recursive-returning-savepoint-current-source-next139',
                    'sqlite-trigger-recursive-returning-savepoint-current-source-next147',
                    'sqlite-returning-rows-yield-before-rollback-to-savepoint',
                ],
            ))),
        ];
    }

    private static function statusNext147(bool $rollbackCurrent, bool $rollbackNext): string
    {
        if ($rollbackCurrent && !$rollbackNext) {
            return 'trigger-recursive-returning-savepoint-current-source-next147-current-rolled-back-next-admitted';
        }
        if (!$rollbackCurrent && $rollbackNext) {
            return 'trigger-recursive-returning-savepoint-current-source-next147-current-admitted-next-rolled-back';
        }
        if ($rollbackCurrent && $rollbackNext) {
            return 'trigger-recursive-returning-savepoint-current-source-next147-both-rolled-back';
        }

        return 'trigger-recursive-returning-savepoint-current-source-next147-both-admitted';
    }

    /**
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function streamNext147(array $yielded, string $phase, string $source, bool $admitted): array
    {
        $stream = [];
        foreach ($yielded as $ordinal => $row) {
            $stream[] = [
                'phase' => $phase,
                'source' => $source,
                'source_ordinal' => $ordinal,
                'returning_ordinal' => $ordinal,
                'savepoint' => (string) ($row['savepoint'] ?? ''),
                'admitted' => $admitted,
                'rolled_back_after_yield' => (bool) ($row['rolled_back_after_yield'] ?? false),
                'returning' => (array) ($row['row'] ?? []),
            ];
        }

        return $stream;
    }

    /**
     * @param list<array<string,mixed>> $stream
     * @return list<array<string,mixed>>
     */
    private static function rowsNext147(array $stream): array
    {
        return array_values(array_map(static fn (array $row): array => (array) $row['returning'], $stream));
    }

    private static function identifierNext147(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("SQLite trigger recursive RETURNING next147 {$label} is malformed");
        }

        return $identifier;
    }

    private static function sourceNext147(string $source): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]+$/', $source)) {
            throw new InvalidArgumentException('SQLite trigger recursive RETURNING next147 source token is malformed');
        }

        return $source;
    }
}
