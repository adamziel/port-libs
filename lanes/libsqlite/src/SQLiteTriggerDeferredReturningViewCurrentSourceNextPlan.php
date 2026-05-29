<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerDeferredReturningViewCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{parent_table:string,child_table:string,parent_key:string,child_key:string,deferred?:bool} $foreignKey
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
        array $foreignKey,
        string $savepointName,
        array $returning = ['*'],
        array $options = [],
    ): array {
        self::validateForeignKey($foreignKey);

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

        $currentSource = self::sourceToken($options, 'current', $base['current']['operations'] ?? []);
        $nextSource = self::sourceToken($options, 'next', $base['next']['operations'] ?? []);
        $deferred = (bool) ($foreignKey['deferred'] ?? true);
        $violations = self::foreignKeyViolations((array) $base['tables'], $foreignKey);
        $rollbackOnViolation = (bool) ($options['rollback_on_deferred_violation'] ?? true);
        $phaseRollback = $base['rolled_back_phases'] !== [];
        $deferredFailure = $deferred && $violations !== [] && !$phaseRollback;
        $releaseStatus = $phaseRollback
            ? 'phase-savepoint-rolled-back'
            : ($deferredFailure ? 'deferred-foreign-key-failed' : 'released');
        $rolledBackToCurrent = $deferredFailure && $rollbackOnViolation;
        $blockedBeforeNext = $deferredFailure && !$rollbackOnViolation;
        $committed = !$phaseRollback && !$deferredFailure;
        $finalTables = $rolledBackToCurrent ? (array) $base['current_source_tables'] : (array) $base['tables'];

        $currentStream = self::tagRows((array) $base['current_returning'], 'current', $currentSource, true);
        $nextStream = self::tagRows((array) $base['next_returning'], 'next', $nextSource, $committed);
        $attempted = array_merge(
            self::tagAttemptedRows((array) $base['current']['attempted_returning_rows'], 'current', $currentSource),
            self::tagAttemptedRows((array) $base['next']['attempted_returning_rows'], 'next', $nextSource),
        );

        if ($rolledBackToCurrent) {
            $returningRows = [];
        } elseif ($blockedBeforeNext) {
            $returningRows = $currentStream;
        } else {
            $returningRows = array_merge($currentStream, $nextStream);
        }

        return array_merge($base, [
            'status' => $rolledBackToCurrent
                ? 'deferred-view-returning-rolled-back-to-current-source'
                : ($blockedBeforeNext ? 'deferred-view-returning-blocked-before-next-source' : $base['status']),
            'release_status' => $releaseStatus,
            'deferred_foreign_key' => $foreignKey,
            'deferred_violations' => $violations,
            'deferred_violation_count' => count($violations),
            'rollback_on_deferred_violation' => $rollbackOnViolation,
            'deferred_barrier_open' => $committed,
            'deferred_barrier_reason' => $committed
                ? 'no-deferred-violations'
                : ($phaseRollback ? 'view-trigger-savepoint-rollback' : ($rolledBackToCurrent ? 'rollback-on-deferred-violation' : 'deferred-violation-blocks-next-source')),
            'source_transition' => [
                'current' => $currentSource,
                'next' => $nextSource,
                'visible' => $rolledBackToCurrent ? $currentSource : $nextSource,
                'barrier' => $committed ? 'commit-admits-next-source' : ($rolledBackToCurrent ? 'rollback-to-current-source' : 'deferred-blocked-before-next-source'),
            ],
            'current_source_stream' => $currentStream,
            'next_source_stream' => $nextStream,
            'attempted_source_stream' => $attempted,
            'admitted_next_source_stream' => $committed ? $nextStream : [],
            'suppressed_next_source_stream' => $committed ? [] : $nextStream,
            'returning_rows' => $returningRows,
            'tables' => $finalTables,
            'rolled_back_to_current_source' => $rolledBackToCurrent,
            'dependencies' => array_values(array_unique(array_merge(
                (array) $base['dependencies'],
                [
                    'sqlite-trigger-deferred-returning-view-current-source-next127',
                    'sqlite-view-trigger-returning-deferred-fk-release-barrier',
                ],
            ))),
        ]);
    }

    /**
     * @param array<string,mixed> $foreignKey
     */
    private static function validateForeignKey(array $foreignKey): void
    {
        foreach (['parent_table', 'child_table', 'parent_key', 'child_key'] as $key) {
            if (!isset($foreignKey[$key]) || !is_string($foreignKey[$key]) || $foreignKey[$key] === '') {
                throw new InvalidArgumentException('SQLite deferred view RETURNING foreign key is malformed');
            }
        }
        foreach (['parent_table', 'child_table'] as $key) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $foreignKey[$key])) {
                throw new InvalidArgumentException('SQLite deferred view RETURNING foreign-key table must be schema.table');
            }
        }
    }

    /**
     * @param array<string,mixed> $options
     * @param list<array<string,mixed>> $operations
     */
    private static function sourceToken(array $options, string $phase, array $operations): string
    {
        $configured = $options[$phase . '_source'] ?? null;
        if (is_string($configured) && preg_match('/^[A-Za-z0-9_.:@-]+$/', $configured)) {
            return $configured;
        }

        $trigger = $operations[0]['trigger'] ?? $phase;
        return $phase . '@' . preg_replace('/[^A-Za-z0-9_.:@-]+/', '-', (string) $trigger);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,mixed> $foreignKey
     * @return list<array<string,mixed>>
     */
    private static function foreignKeyViolations(array $tables, array $foreignKey): array
    {
        $parentTable = strtolower((string) $foreignKey['parent_table']);
        $childTable = strtolower((string) $foreignKey['child_table']);
        if (!isset($tables[$parentTable], $tables[$childTable])) {
            throw new InvalidArgumentException('SQLite deferred view RETURNING foreign-key tables are missing');
        }

        $parentKey = (string) $foreignKey['parent_key'];
        $childKey = (string) $foreignKey['child_key'];
        $parents = [];
        foreach ($tables[$parentTable] as $row) {
            if (!array_key_exists($parentKey, $row)) {
                throw new InvalidArgumentException('SQLite deferred view RETURNING parent key is missing');
            }
            $parents[(string) $row[$parentKey]] = true;
        }

        $violations = [];
        foreach ($tables[$childTable] as $ordinal => $row) {
            if (!array_key_exists($childKey, $row)) {
                throw new InvalidArgumentException('SQLite deferred view RETURNING child key is missing');
            }
            $value = $row[$childKey];
            if ($value === null || isset($parents[(string) $value])) {
                continue;
            }
            $violations[] = [
                'child_table' => $childTable,
                'child_ordinal' => $ordinal,
                'child_key' => $value,
                'parent_table' => $parentTable,
                'parent_key' => $parentKey,
                'phase' => 'deferred-release',
            ];
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, string $source, bool $admitted): array
    {
        $tagged = [];
        foreach ($rows as $ordinal => $row) {
            $tagged[] = [
                'phase' => $phase,
                'source' => $source,
                'ordinal' => $ordinal,
                'admitted' => $admitted,
                'returning' => $row,
            ];
        }

        return $tagged;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagAttemptedRows(array $rows, string $phase, string $source): array
    {
        $tagged = [];
        foreach ($rows as $entry) {
            $tagged[] = [
                'phase' => $phase,
                'source' => $source,
                'source_ordinal' => $entry['source_ordinal'] ?? null,
                'returning' => $entry['row'] ?? $entry,
            ];
        }

        return $tagged;
    }
}
