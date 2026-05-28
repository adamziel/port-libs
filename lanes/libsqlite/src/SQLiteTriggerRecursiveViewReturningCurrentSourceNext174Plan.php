<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext174Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $firstNextRoots
     * @param list<array<string,mixed>> $secondNextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_staged_sources?:int,max_depth?:int,savepoint?:string,current_generation?:string,first_next_generation?:string,second_next_generation?:string,cursor_name?:string,current_schema_cookie?:int,next_schema_cookie?:int,reprepare_token?:string,expected_reprepare_token?:string,conflict_key_separator?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentRoots,
        array $firstNextRoots,
        array $secondNextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $separator = self::separator((string) ($options['conflict_key_separator'] ?? ':'));
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext170Plan::execute(
            $rows,
            $currentRoots,
            $firstNextRoots,
            $secondNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_staged_sources' => $options['release_staged_sources'] ?? 0,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_next174',
                'current_generation' => $options['current_generation'] ?? 'wp-import-current-174',
                'first_next_generation' => $options['first_next_generation'] ?? 'wp-import-next-174-a',
                'second_next_generation' => $options['second_next_generation'] ?? 'wp-import-next-174-b',
                'cursor_name' => $options['cursor_name'] ?? 'wp_recursive_view_returning_cursor_174',
                'current_schema_cookie' => $options['current_schema_cookie'] ?? 174,
                'next_schema_cookie' => $options['next_schema_cookie'] ?? 175,
                'reprepare_token' => $options['reprepare_token'] ?? 'wp.reprepare.174',
                'expected_reprepare_token' => $options['expected_reprepare_token'] ?? 'wp.reprepare.174.expected',
            ],
        );

        $steps = self::watermarkedSteps($base['barrier_steps'], $separator);
        $visible = array_values(array_filter($steps, static fn (array $step): bool => $step['visible_after_watermark']));
        $held = array_values(array_filter($steps, static fn (array $step): bool => !$step['visible_after_watermark']));
        $conflicts = array_values(array_filter($steps, static fn (array $step): bool => $step['conflicts_with_current_key']));
        $stagedConflicts = array_values(array_filter($conflicts, static fn (array $step): bool => $step['phase'] !== 'current'));

        return [
            'status' => self::status($base, $stagedConflicts),
            'savepoint' => $base['savepoint'],
            'cursor' => $base['cursor'],
            'base' => $base,
            'conflict_key_separator' => $separator,
            'watermark_steps' => $steps,
            'visible_watermark_steps' => $visible,
            'held_watermark_steps' => $held,
            'conflicting_staged_steps' => $stagedConflicts,
            'statement_rows' => count($visible),
            'attempted_statement_rows' => count($steps),
            'current_statement_rows' => count(array_filter($steps, static fn (array $step): bool => $step['phase'] === 'current')),
            'staged_statement_rows' => count(array_filter($steps, static fn (array $step): bool => $step['phase'] !== 'current')),
            'visible_keys' => array_column($visible, 'visibility_key'),
            'held_keys' => array_column($held, 'visibility_key'),
            'conflict_keys' => array_values(array_unique(array_column($stagedConflicts, 'logical_key'))),
            'current_source_watermark' => [
                'current_keys' => array_values(array_unique(array_column(
                    array_filter($steps, static fn (array $step): bool => $step['phase'] === 'current'),
                    'logical_key',
                ))),
                'staged_conflict_keys' => array_values(array_unique(array_column($stagedConflicts, 'logical_key'))),
                'current_drained_before_next' => $base['current_drained_before_next'],
                'reprepare_token_matches' => $base['reprepare_token_matches'],
                'source_changed' => $base['source_changed'],
                'reason' => $stagedConflicts === []
                    ? 'no staged RETURNING row reused a current-source key'
                    : 'staged RETURNING rows reuse current-source keys and stay behind the current-source watermark',
            ],
            'yield_boundary' => $stagedConflicts === []
                ? 'recursive-view-returning-current-source-watermark-clear-next174'
                : 'recursive-view-returning-current-source-watermark-conflict-held-next174',
            'dependency_closure' => 'no new support component needed; reuses native recursive view RETURNING current-source cursor and reprepare barriers',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next174',
                'sqlite-returning-current-source-duplicate-key-watermark',
                'wordpress-recursive-view-returning-current-source-next174',
            ]))),
            'non_overlap' => 'extends accepted next170 source-drain/reprepare barrier with duplicate-key watermarking for staged next-source rows; does not repeat savepoint rollback, deferred FK, UPSERT, DELETE, or schema reparse trigger slices',
        ];
    }

    /**
     * @param mixed $steps
     * @return list<array<string,mixed>>
     */
    private static function watermarkedSteps(mixed $steps, string $separator): array
    {
        if (!is_array($steps) || !array_is_list($steps)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next174 barrier steps are malformed');
        }

        $currentKeys = [];
        $out = [];
        foreach ($steps as $ordinal => $step) {
            if (!is_array($step) || !isset($step['visibility_key'], $step['phase'])) {
                throw new InvalidArgumentException('SQLite trigger recursive view next174 barrier step is malformed');
            }
            $logicalKey = self::logicalKey((string) $step['visibility_key'], $separator);
            $isCurrent = $step['phase'] === 'current';
            if ($isCurrent) {
                $currentKeys[$logicalKey] = true;
            }

            $conflicts = !$isCurrent && isset($currentKeys[$logicalKey]);
            $step['watermark_ordinal'] = $ordinal;
            $step['logical_key'] = $logicalKey;
            $step['conflicts_with_current_key'] = $conflicts;
            $step['visible_after_watermark'] = (bool) ($step['visible_after_barrier'] ?? false) && !$conflicts;
            $step['held_by_current_source_watermark'] = $conflicts;
            $out[] = $step;
        }

        return $out;
    }

    private static function logicalKey(string $visibilityKey, string $separator): string
    {
        $offset = strrpos($visibilityKey, $separator);
        if ($offset === false || $offset === strlen($visibilityKey) - strlen($separator)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next174 visibility key is malformed');
        }

        return substr($visibilityKey, $offset + strlen($separator));
    }

    private static function status(array $base, array $stagedConflicts): string
    {
        if ($stagedConflicts !== []) {
            return 'trigger-recursive-view-returning-current-source-watermark-held-next174';
        }
        if (($base['release_allowed'] ?? false) === true && (int) ($base['release_requested'] ?? 0) > 0) {
            return 'trigger-recursive-view-returning-current-source-watermark-released-next174';
        }

        return 'trigger-recursive-view-returning-current-source-watermark-drained-next174';
    }

    private static function separator(string $value): string
    {
        if ($value === '') {
            throw new InvalidArgumentException('SQLite trigger recursive view next174 conflict key separator must not be empty');
        }

        return $value;
    }
}
