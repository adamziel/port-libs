<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext147Plan
{
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
    public static function execute(
        array $savepointRows,
        array $currentRows,
        array $nextRows,
        array $triggers,
        array $uniqueColumns,
        array $returning,
        array $options = [],
    ): array {
        $savepoint = self::identifier((string) ($options['savepoint'] ?? 'wp_recursive_returning_batch'), 'savepoint');
        $currentSource = self::source((string) ($options['current_source'] ?? 'current-recursive-returning'));
        $nextSource = self::source((string) ($options['next_source'] ?? 'next-recursive-returning'));
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
        $current = SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext139Plan::insertRowsWithinSavepoint(
            $savepointImage,
            array_values($currentRows),
            $triggers,
            $uniqueColumns,
            $returning,
            $shared + ['rollback_to' => $rollbackCurrent],
        );
        $nextBaseRows = $rollbackCurrent ? $savepointImage : $current['after_statement'];
        $next = SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext139Plan::insertRowsWithinSavepoint(
            $nextBaseRows,
            array_values($nextRows),
            $triggers,
            $uniqueColumns,
            $returning,
            $shared + ['rollback_to' => $rollbackNext],
        );

        $currentStream = self::stream($current['yielded'], 'current', $currentSource, !$rollbackCurrent);
        $nextStream = self::stream($next['yielded'], 'next', $nextSource, !$rollbackNext);
        $attempted = array_merge($currentStream, $nextStream);
        $admitted = array_values(array_filter($attempted, static fn (array $row): bool => $row['admitted']));
        $suppressed = array_values(array_filter($attempted, static fn (array $row): bool => !$row['admitted']));
        $finalRows = $rollbackNext ? $nextBaseRows : $next['after_statement'];

        return [
            'status' => self::status($rollbackCurrent, $rollbackNext),
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
            'returning_rows' => self::rows($admitted),
            'suppressed_returning_rows' => self::rows($suppressed),
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

    private static function status(bool $rollbackCurrent, bool $rollbackNext): string
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
    private static function stream(array $yielded, string $phase, string $source, bool $admitted): array
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
    private static function rows(array $stream): array
    {
        return array_values(array_map(static fn (array $row): array => (array) $row['returning'], $stream));
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("SQLite trigger recursive RETURNING next147 {$label} is malformed");
        }

        return $identifier;
    }

    private static function source(string $source): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]+$/', $source)) {
            throw new InvalidArgumentException('SQLite trigger recursive RETURNING next147 source token is malformed');
        }

        return $source;
    }
}
