<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext143Plan
{
    /**
     * @param list<array<string,mixed>> $initialRows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,int):mixed> $returning
     * @param array{view?:string,savepoint?:string,current_source?:string,next_source?:string,current_rollback_to?:bool,next_rollback_to?:bool,recursive_triggers?:bool,max_depth?:int,conflict_action?:string} $options
     * @return array<string,mixed>
     */
    public static function insertThroughViewSources(
        array $initialRows,
        array $currentRows,
        array $nextRows,
        array $triggers,
        array $uniqueColumns,
        array $returning = ['*'],
        array $options = [],
    ): array {
        $view = self::identifier((string) ($options['view'] ?? 'wp_option_import_view'), 'view');
        $savepoint = self::identifier((string) ($options['savepoint'] ?? 'wp_recursive_view_import'), 'savepoint');
        $currentSource = self::sourceToken((string) ($options['current_source'] ?? 'current-recursive-view-returning'));
        $nextSource = self::sourceToken((string) ($options['next_source'] ?? 'next-recursive-view-returning'));
        $currentRollback = (bool) ($options['current_rollback_to'] ?? true);
        $nextRollback = (bool) ($options['next_rollback_to'] ?? false);

        if ($currentRows === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING current rows cannot be empty');
        }
        if ($nextRows === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next rows cannot be empty');
        }

        $shared = [
            'savepoint' => $savepoint,
            'recursive_triggers' => (bool) ($options['recursive_triggers'] ?? true),
            'max_depth' => (int) ($options['max_depth'] ?? 1000),
            'conflict_action' => (string) ($options['conflict_action'] ?? 'abort'),
        ];
        $current = SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext139Plan::insertRowsWithinSavepoint(
            $initialRows,
            $currentRows,
            $triggers,
            $uniqueColumns,
            $returning,
            $shared + [
                'rollback_to' => $currentRollback,
                'current_source' => $currentSource,
                'next_source' => $nextSource,
            ],
        );
        $nextBaseRows = $currentRollback ? $initialRows : $current['after_statement'];
        $next = SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext139Plan::insertRowsWithinSavepoint(
            $nextBaseRows,
            $nextRows,
            $triggers,
            $uniqueColumns,
            $returning,
            $shared + [
                'rollback_to' => $nextRollback,
                'current_source' => $currentSource,
                'next_source' => $nextSource,
            ],
        );

        $currentStream = self::stream($current['yielded'], 'current', $currentSource, $view, !$currentRollback);
        $nextStream = self::stream($next['yielded'], 'next', $nextSource, $view, !$nextRollback);
        $admittedCurrent = array_values(array_filter($currentStream, static fn (array $row): bool => $row['admitted']));
        $admittedNext = array_values(array_filter($nextStream, static fn (array $row): bool => $row['admitted']));
        $suppressedCurrent = array_values(array_filter($currentStream, static fn (array $row): bool => !$row['admitted']));
        $suppressedNext = array_values(array_filter($nextStream, static fn (array $row): bool => !$row['admitted']));
        $finalRows = $nextRollback ? $nextBaseRows : $next['after_statement'];

        return [
            'view' => $view,
            'savepoint' => $savepoint,
            'status' => self::status($currentRollback, $nextRollback),
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'source_transition' => [
                'from' => $currentSource,
                'to' => $nextSource,
                'current_rolled_back' => $currentRollback,
                'next_rolled_back' => $nextRollback,
                'next_input' => $currentRollback ? 'saved-current-source' : 'current-phase-output',
                'visible_source' => !$nextRollback ? $nextSource : ($currentRollback ? $currentSource : $nextSource . ':rolled-back'),
            ],
            'before' => array_values($initialRows),
            'current' => $current,
            'next' => $next,
            'current_source_stream' => $currentStream,
            'next_source_stream' => $nextStream,
            'attempted_source_stream' => array_merge($currentStream, $nextStream),
            'admitted_current_source_stream' => $admittedCurrent,
            'admitted_next_source_stream' => $admittedNext,
            'suppressed_current_source_stream' => $suppressedCurrent,
            'suppressed_next_source_stream' => $suppressedNext,
            'returning_rows' => self::rows(array_merge($admittedCurrent, $admittedNext)),
            'suppressed_returning_rows' => self::rows(array_merge($suppressedCurrent, $suppressedNext)),
            'final_rows' => array_values($finalRows),
            'current_source_admitted' => !$currentRollback,
            'next_source_admitted' => !$nextRollback,
            'discarded_returning_count' => $current['discarded_returning_count'] + $next['discarded_returning_count'],
            'dependency_closure' => 'reuses-native-recursive-trigger-returning-savepoint-and-view-current-source-plans',
            'dependencies' => array_values(array_unique(array_merge(
                (array) ($current['dependencies'] ?? []),
                (array) ($next['dependencies'] ?? []),
                [
                    'sqlite-trigger-returning-savepoint-view-current-source-next136',
                    'sqlite-trigger-recursive-returning-savepoint-current-source-next139',
                    'sqlite-trigger-recursive-view-returning-current-source-next143',
                ],
            ))),
        ];
    }

    private static function status(bool $currentRollback, bool $nextRollback): string
    {
        if ($currentRollback && !$nextRollback) {
            return 'current-recursive-view-rollback-next-source-admitted';
        }
        if (!$currentRollback && $nextRollback) {
            return 'current-recursive-view-admitted-next-source-rolled-back';
        }
        if ($currentRollback && $nextRollback) {
            return 'current-next-recursive-view-savepoints-rolled-back';
        }

        return 'current-next-recursive-view-returning-source-admitted';
    }

    /**
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function stream(array $yielded, string $phase, string $source, string $view, bool $admitted): array
    {
        $stream = [];
        foreach ($yielded as $ordinal => $row) {
            $stream[] = [
                'phase' => $phase,
                'view' => $view,
                'source' => $source,
                'source_ordinal' => $ordinal,
                'returning_ordinal' => $ordinal,
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING {$label} is malformed");
        }

        return $identifier;
    }

    private static function sourceToken(string $token): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]+$/', $token)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING source token is malformed');
        }

        return $token;
    }
}
