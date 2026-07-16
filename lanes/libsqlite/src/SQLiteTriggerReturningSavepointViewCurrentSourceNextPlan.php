<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerReturningSavepointViewCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<string>|array<string,string|callable(array<string,mixed>):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function execute(
        SQLiteAttachedSchemaCatalog $catalog,
        string $triggerName,
        array $tables,
        array $currentRows,
        array $nextRows,
        string $savepointName,
        array $returning = ['*'],
        array $options = [],
    ): array {
        $currentSource = self::sourceToken($options, 'current', 'current@view-trigger-next136');
        $nextSource = self::sourceToken($options, 'next', 'next@view-trigger-next136');

        $base = SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan::execute(
            $catalog,
            $triggerName,
            $tables,
            $currentRows,
            $nextRows,
            $savepointName,
            $returning,
            $options,
        );

        $currentRolledBack = (bool) ($base['current']['rolled_back'] ?? false);
        $nextRolledBack = (bool) ($base['next']['rolled_back'] ?? false);
        $currentAttempted = (array) ($base['current']['attempted_returning_rows'] ?? []);
        $nextAttempted = (array) ($base['next']['attempted_returning_rows'] ?? []);
        $currentReturning = (array) ($base['current_returning'] ?? []);
        $nextReturning = (array) ($base['next_returning'] ?? []);
        $nextAdmitted = !$nextRolledBack;

        $currentStream = self::stream($currentAttempted, $currentReturning, 'current', $currentSource, !$currentRolledBack);
        $nextStream = self::stream($nextAttempted, $nextReturning, 'next', $nextSource, $nextAdmitted);
        $suppressedCurrent = array_values(array_filter($currentStream, static fn (array $row): bool => !$row['admitted']));
        $suppressedNext = array_values(array_filter($nextStream, static fn (array $row): bool => !$row['admitted']));
        $admittedCurrent = array_values(array_filter($currentStream, static fn (array $row): bool => $row['admitted']));
        $admittedNext = array_values(array_filter($nextStream, static fn (array $row): bool => $row['admitted']));

        return array_merge($base, [
            'status' => self::status($currentRolledBack, $nextRolledBack),
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'source_transition' => [
                'from' => $currentSource,
                'to' => $nextSource,
                'current_rolled_back' => $currentRolledBack,
                'next_admitted' => $nextAdmitted,
                'next_input' => $currentRolledBack ? 'saved-current-source' : 'current-phase-output',
                'visible_source' => $nextAdmitted ? $nextSource : ($currentRolledBack ? $currentSource : $nextSource . ':rolled-back'),
            ],
            'current_source_stream' => $currentStream,
            'next_source_stream' => $nextStream,
            'admitted_current_source_stream' => $admittedCurrent,
            'admitted_next_source_stream' => $admittedNext,
            'suppressed_current_source_stream' => $suppressedCurrent,
            'suppressed_next_source_stream' => $suppressedNext,
            'attempted_source_stream' => array_merge($currentStream, $nextStream),
            'admitted_returning_rows' => array_values(array_map(static fn (array $row): array => $row['returning'], array_merge($admittedCurrent, $admittedNext))),
            'suppressed_returning_rows' => array_values(array_map(static fn (array $row): array => $row['returning'], array_merge($suppressedCurrent, $suppressedNext))),
            'returning_rows' => array_values(array_map(static fn (array $row): array => $row['returning'], array_merge($admittedCurrent, $admittedNext))),
            'current_source_admitted' => !$currentRolledBack,
            'next_source_admitted' => $nextAdmitted,
            'current_source_rolled_back_to_savepoint' => $currentRolledBack,
            'next_source_rolled_back_to_savepoint' => $nextRolledBack,
            'dependency_closure' => 'reuses-native-view-trigger-returning-savepoint-plans',
            'dependencies' => array_values(array_unique(array_merge(
                (array) ($base['dependencies'] ?? []),
                [
                    'sqlite-trigger-returning-savepoint-view-current-source-next136',
                    'sqlite-view-trigger-current-rollback-next-source-admission',
                ],
            ))),
        ]);
    }

    /**
     * @param array<string,mixed> $options
     */
    private static function sourceToken(array $options, string $phase, string $fallback): string
    {
        $token = $options[$phase . '_source'] ?? $fallback;
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9_.:@-]+$/', $token)) {
            throw new InvalidArgumentException('SQLite trigger RETURNING view current-source token is malformed');
        }

        return $token;
    }

    private static function status(bool $currentRolledBack, bool $nextRolledBack): string
    {
        if ($currentRolledBack && !$nextRolledBack) {
            return 'current-view-trigger-rollback-next-source-admitted';
        }
        if (!$currentRolledBack && $nextRolledBack) {
            return 'current-view-trigger-admitted-next-source-rolled-back';
        }
        if ($currentRolledBack && $nextRolledBack) {
            return 'current-next-view-trigger-savepoints-rolled-back';
        }

        return 'current-next-view-trigger-returning-source-admitted';
    }

    /**
     * @param list<array<string,mixed>> $attempted
     * @param list<array<string,mixed>> $visible
     * @return list<array<string,mixed>>
     */
    private static function stream(array $attempted, array $visible, string $phase, string $source, bool $phaseAdmitted): array
    {
        $visibleByOrdinal = [];
        foreach ($visible as $ordinal => $row) {
            $visibleByOrdinal[$ordinal] = $row;
        }

        $stream = [];
        foreach ($attempted as $ordinal => $entry) {
            $row = (array) ($entry['row'] ?? $entry);
            $stream[] = [
                'phase' => $phase,
                'source' => $source,
                'source_ordinal' => $entry['source_ordinal'] ?? $ordinal,
                'returning_ordinal' => $ordinal,
                'admitted' => $phaseAdmitted && array_key_exists($ordinal, $visibleByOrdinal),
                'returning' => $phaseAdmitted && array_key_exists($ordinal, $visibleByOrdinal) ? (array) $visibleByOrdinal[$ordinal] : $row,
            ];
        }

        return $stream;
    }
}
