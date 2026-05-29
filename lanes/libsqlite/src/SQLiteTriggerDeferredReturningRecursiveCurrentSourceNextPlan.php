<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerDeferredReturningRecursiveCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param array<string,mixed> $statement
     * @return array<string,mixed>
     */
    public static function sourceBarrier(array $parents, array $children, array $foreignKey, array $statement): array
    {
        $base = SQLiteTriggerRecursiveDeferredReturningCurrentSourceNext121Plan::update(
            $parents,
            $children,
            $foreignKey,
            $statement,
        );

        $currentSource = (string) $base['current_source'];
        $nextSource = (string) ($statement['next_source'] ?? $base['next_source']);
        $rolledBack = $base['status'] === 'rolled-back';
        $blocked = $base['status'] === 'deferred-commit-blocked';
        $committed = $base['status'] === 'commit-ok';

        $currentStream = [];
        $recursiveStream = [];
        foreach ((array) $base['attempted_returning_rows'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $streamRow = [
                'ordinal' => $entry['ordinal'] ?? count($currentStream) + count($recursiveStream),
                'source' => $entry['trigger_source'] === 'statement' ? $currentSource : $nextSource,
                'trigger_source' => $entry['trigger_source'] ?? null,
                'trigger_depth' => $entry['trigger_depth'] ?? null,
                'old_key' => $entry['old_key'] ?? null,
                'new_key' => $entry['new_key'] ?? null,
                'returning' => $entry['returning'] ?? [],
            ];
            if (($entry['trigger_source'] ?? 'statement') === 'statement') {
                $currentStream[] = $streamRow;
            } else {
                $recursiveStream[] = $streamRow;
            }
        }

        $admittedNext = ($rolledBack || $blocked) ? [] : $recursiveStream;
        $deferredQueue = [];
        foreach ((array) $base['foreign_key_violations'] as $index => $violation) {
            if (!is_array($violation)) {
                continue;
            }
            $deferredQueue[] = [
                'ordinal' => $index,
                'source' => $nextSource,
                'phase' => $violation['phase'] ?? 'deferred-commit',
                'child_key' => $violation['child_key'] ?? null,
                'admitted' => false,
            ];
        }

        return array_merge($base, [
            'source_transition' => [
                'current' => $currentSource,
                'recursive_next' => $nextSource,
                'visible_next' => (string) $base['next_source'],
                'barrier' => $rolledBack ? 'rollback-to-current-source' : ($blocked ? 'deferred-blocked-before-next-source' : 'commit-admits-next-source'),
            ],
            'current_source_stream' => $currentStream,
            'recursive_next_source_stream' => $recursiveStream,
            'admitted_next_source_stream' => $admittedNext,
            'suppressed_next_source_stream' => ($rolledBack || $blocked) ? $recursiveStream : [],
            'deferred_check_queue' => $deferredQueue,
            'deferred_barrier_open' => $committed,
            'deferred_barrier_reason' => $committed ? 'no-deferred-violations' : ($rolledBack ? 'rollback-on-deferred-violation' : 'deferred-violation-blocks-next-source'),
            'dependencies' => array_values(array_unique(array_merge(
                (array) $base['dependencies'],
                [
                    'sqlite-trigger-deferred-returning-recursive-current-source-next125',
                    'sqlite-recursive-trigger-returning-next-source-barrier',
                ],
            ))),
        ]);
    }
}
