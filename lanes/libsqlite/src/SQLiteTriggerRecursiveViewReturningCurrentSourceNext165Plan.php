<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext165Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $firstNextRoots
     * @param list<array<string,mixed>> $secondNextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_staged_sources?:int,max_depth?:int,savepoint?:string,current_generation?:string,first_next_generation?:string,second_next_generation?:string,cursor_name?:string} $options
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
        $cursor = self::token((string) ($options['cursor_name'] ?? 'wp_recursive_view_returning_cursor_165'), 'cursor');
        $releaseCount = self::releaseCount($options['release_staged_sources'] ?? 0);

        $queue = SQLiteTriggerRecursiveViewReturningCurrentSourceNext162Plan::execute(
            $rows,
            $currentRoots,
            $firstNextRoots,
            $secondNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_staged_sources' => $releaseCount,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_next165',
                'current_generation' => $options['current_generation'] ?? 'wp-import-current-165',
                'first_next_generation' => $options['first_next_generation'] ?? 'wp-import-next-165-a',
                'second_next_generation' => $options['second_next_generation'] ?? 'wp-import-next-165-b',
            ],
        );

        $first = $queue['first_stage'];
        if (!is_array($first)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next165 first stage is malformed');
        }

        $currentRows = self::rows($first['current_returning_rows'] ?? [], 'current returning rows');
        $firstRows = self::rows($first['attempted_next_returning_rows'] ?? [], 'first next returning rows');
        $second = $queue['second_stage'] ?? null;
        if (!is_array($second)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next165 second stage is malformed');
        }
        $secondRows = self::rows($second['attempted_next_returning_rows'] ?? [], 'second next returning rows');

        $currentKeys = array_column($currentRows, 'visibility_key');
        $firstKeys = array_column($firstRows, 'visibility_key');
        $secondKeys = array_column($secondRows, 'visibility_key');
        $visibleKeys = self::stringList($queue['returning_visibility']['visible'] ?? [], 'visible returning keys');
        $suppressedKeys = self::stringList($queue['returning_visibility']['suppressed'] ?? [], 'suppressed returning keys');
        $currentGeneration = (string) $queue['current_generation'];
        $visibleGeneration = (string) $queue['visible_generation'];

        $steps = [];
        foreach ($currentKeys as $ordinal => $key) {
            $steps[] = [
                'cursor' => $cursor,
                'ordinal' => $ordinal,
                'phase' => 'current',
                'generation' => $currentGeneration,
                'visibility_key' => $key,
                'source' => $queue['current_source'],
                'visible' => true,
                'drained_before_next' => true,
            ];
        }
        foreach ($firstKeys as $ordinal => $key) {
            $visible = in_array($key, $visibleKeys, true);
            $steps[] = [
                'cursor' => $cursor,
                'ordinal' => $ordinal,
                'phase' => 'first-next',
                'generation' => $queue['staged_generations'][0],
                'visibility_key' => $key,
                'source' => $queue['next_source'],
                'visible' => $visible,
                'held_by_current_source' => !$visible,
            ];
        }
        foreach ($secondKeys as $ordinal => $key) {
            $visible = in_array($key, $visibleKeys, true);
            $steps[] = [
                'cursor' => $cursor,
                'ordinal' => $ordinal,
                'phase' => 'second-next',
                'generation' => $queue['staged_generations'][1],
                'visibility_key' => $key,
                'source' => $queue['next_source'],
                'visible' => $visible,
                'held_by_current_source' => !$visible,
            ];
        }

        $visibleSteps = array_values(array_filter($steps, static fn (array $step): bool => $step['visible'] === true));
        $heldSteps = array_values(array_filter($steps, static fn (array $step): bool => $step['visible'] === false));
        $sourceNextPlan = [
            'cursor' => $cursor,
            'current_generation' => $currentGeneration,
            'visible_generation' => $visibleGeneration,
            'release_count' => $releaseCount,
            'current_source_steps' => count($currentKeys),
            'staged_source_steps' => count($firstKeys) + count($secondKeys),
            'visible_steps' => count($visibleSteps),
            'held_steps' => count($heldSteps),
            'current_drained_before_staged' => self::currentBeforeStaged($steps),
            'visible_keys' => array_column($visibleSteps, 'visibility_key'),
            'held_keys' => array_column($heldSteps, 'visibility_key'),
            'first_next_visible' => $releaseCount >= 1,
            'second_next_visible' => $releaseCount >= 2,
        ];

        return [
            'status' => match ($releaseCount) {
                0 => 'trigger-recursive-view-returning-current-source-next-cursor-held-next165',
                1 => 'trigger-recursive-view-returning-current-source-next-cursor-first-released-next165',
                default => 'trigger-recursive-view-returning-current-source-next-cursor-all-released-next165',
            },
            'savepoint' => $queue['savepoint'],
            'cursor' => $cursor,
            'queue' => $queue,
            'cursor_steps' => $steps,
            'visible_cursor_steps' => $visibleSteps,
            'held_cursor_steps' => $heldSteps,
            'source_next_plan' => $sourceNextPlan,
            'returning_visibility' => [
                'visible' => $visibleKeys,
                'suppressed' => $suppressedKeys,
                'current_visible' => $currentKeys,
                'first_next' => $firstKeys,
                'second_next' => $secondKeys,
            ],
            'statement_rows' => count($visibleSteps),
            'attempted_statement_rows' => count($steps),
            'changes' => $queue['changes'],
            'after_savepoint' => $queue['after_savepoint'],
            'yield_boundary' => match ($releaseCount) {
                0 => 'recursive-view-returning-current-source-next-cursor-held-next165',
                1 => 'recursive-view-returning-current-source-next-cursor-first-release-next165',
                default => 'recursive-view-returning-current-source-next-cursor-all-release-next165',
            },
            'dependencies' => array_values(array_unique(array_merge($queue['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next165',
                'sqlite-returning-current-source-next-cursor-drain',
                'sqlite-recursive-view-returning-staged-source-visibility',
            ]))),
            'dependency_closure' => 'reuses-native-recursive-view-returning-current-source-queue-and-cursor-model',
        ];
    }

    private static function releaseCount(mixed $value): int
    {
        $count = (int) $value;
        if ($count < 0 || $count > 2) {
            throw new InvalidArgumentException('SQLite trigger recursive view next165 release count must be 0, 1, or 2');
        }

        return $count;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next165 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite trigger recursive view next165 {$label} are malformed");
        }

        return $rows;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite trigger recursive view next165 {$label} are malformed");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }

    /**
     * @param list<array<string,mixed>> $steps
     */
    private static function currentBeforeStaged(array $steps): bool
    {
        $seenStaged = false;
        foreach ($steps as $step) {
            if (($step['phase'] ?? '') === 'current' && $seenStaged) {
                return false;
            }
            if (($step['phase'] ?? '') !== 'current') {
                $seenStaged = true;
            }
        }

        return true;
    }
}
