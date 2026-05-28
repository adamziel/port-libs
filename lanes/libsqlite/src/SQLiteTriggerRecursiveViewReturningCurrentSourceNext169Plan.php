<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext169Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $nestedCurrentRoots
     * @param list<array<string,mixed>> $firstNextRoots
     * @param list<array<string,mixed>> $secondNextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_staged_sources?:int,max_depth?:int,savepoint?:string,current_generation?:string,nested_generation?:string,first_next_generation?:string,second_next_generation?:string,cursor_name?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentRoots,
        array $nestedCurrentRoots,
        array $firstNextRoots,
        array $secondNextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $releaseCount = self::releaseCount($options['release_staged_sources'] ?? 0);
        $cursor = self::token((string) ($options['cursor_name'] ?? 'wp_recursive_view_returning_cursor_169'), 'cursor');
        $savepoint = self::token((string) ($options['savepoint'] ?? 'wp_recursive_view_next169'), 'savepoint');
        $currentGeneration = self::token((string) ($options['current_generation'] ?? 'wp-import-current-169'), 'current generation');
        $nestedGeneration = self::token((string) ($options['nested_generation'] ?? 'wp-import-current-169-nested'), 'nested generation');
        $firstNextGeneration = self::token((string) ($options['first_next_generation'] ?? 'wp-import-next-169-a'), 'first next generation');
        $secondNextGeneration = self::token((string) ($options['second_next_generation'] ?? 'wp-import-next-169-b'), 'second next generation');
        $maxDepth = (int) ($options['max_depth'] ?? 8);
        if ($maxDepth < 1) {
            throw new InvalidArgumentException('SQLite trigger recursive view next169 max depth must be positive');
        }

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext165Plan::execute(
            $rows,
            $currentRoots,
            $firstNextRoots,
            $secondNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_staged_sources' => $releaseCount,
                'max_depth' => $maxDepth,
                'savepoint' => $savepoint . '_base',
                'current_generation' => $currentGeneration,
                'first_next_generation' => $firstNextGeneration,
                'second_next_generation' => $secondNextGeneration,
                'cursor_name' => $cursor . '_base',
            ],
        );

        $nested = SQLiteTriggerRecursiveViewReturningCurrentSourceNext165Plan::execute(
            $rows,
            $nestedCurrentRoots,
            [],
            [],
            $currentView,
            $nextView,
            $returning,
            [
                'release_staged_sources' => 0,
                'max_depth' => $maxDepth,
                'savepoint' => $savepoint . '_nested',
                'current_generation' => $nestedGeneration,
                'first_next_generation' => $firstNextGeneration . '_nested_empty_a',
                'second_next_generation' => $secondNextGeneration . '_nested_empty_b',
                'cursor_name' => $cursor . '_nested',
            ],
        );

        $baseCurrent = self::phaseSteps($base['cursor_steps'], 'current', $cursor, 'current');
        $nestedCurrent = self::phaseSteps($nested['cursor_steps'], 'current', $cursor, 'nested-current');
        $firstNext = self::phaseSteps($base['cursor_steps'], 'first-next', $cursor, 'first-next');
        $secondNext = self::phaseSteps($base['cursor_steps'], 'second-next', $cursor, 'second-next');
        $steps = array_values(array_merge($baseCurrent, $nestedCurrent, $firstNext, $secondNext));

        $visible = [];
        $held = [];
        foreach ($steps as $ordinal => $step) {
            $step['statement_ordinal'] = $ordinal;
            $isCurrent = in_array($step['phase'], ['current', 'nested-current'], true);
            $isVisible = $isCurrent
                || ($step['phase'] === 'first-next' && $releaseCount >= 1)
                || ($step['phase'] === 'second-next' && $releaseCount >= 2);
            $step['visible'] = $isVisible;
            $step['held_by_current_source'] = !$isVisible;
            if ($isVisible) {
                $visible[] = $step;
            } else {
                $held[] = $step;
            }
            $steps[$ordinal] = $step;
        }

        $currentCount = count($baseCurrent) + count($nestedCurrent);
        $stagedCount = count($firstNext) + count($secondNext);
        $sourceNextPlan = [
            'cursor' => $cursor,
            'release_count' => $releaseCount,
            'current_source_steps' => count($baseCurrent),
            'nested_current_source_steps' => count($nestedCurrent),
            'combined_current_source_steps' => $currentCount,
            'staged_source_steps' => $stagedCount,
            'visible_steps' => count($visible),
            'held_steps' => count($held),
            'current_drained_before_nested' => self::orderedBefore($steps, ['current'], ['nested-current']),
            'nested_drained_before_staged' => self::orderedBefore($steps, ['current', 'nested-current'], ['first-next', 'second-next']),
            'current_source_pinned_until_nested_drains' => $held !== [] && $releaseCount === 0,
            'first_next_visible' => $releaseCount >= 1,
            'second_next_visible' => $releaseCount >= 2,
            'visible_keys' => array_column($visible, 'visibility_key'),
            'held_keys' => array_column($held, 'visibility_key'),
        ];

        return [
            'status' => match ($releaseCount) {
                0 => 'trigger-recursive-view-returning-current-source-nested-held-next169',
                1 => 'trigger-recursive-view-returning-current-source-nested-first-released-next169',
                default => 'trigger-recursive-view-returning-current-source-nested-all-released-next169',
            },
            'savepoint' => $savepoint,
            'cursor' => $cursor,
            'base' => $base,
            'nested' => $nested,
            'cursor_steps' => $steps,
            'visible_cursor_steps' => $visible,
            'held_cursor_steps' => $held,
            'source_next_plan' => $sourceNextPlan,
            'statement_rows' => count($visible),
            'attempted_statement_rows' => count($steps),
            'changes' => $releaseCount === 0 ? $currentCount : count($visible),
            'returning_visibility' => [
                'visible' => array_column($visible, 'visibility_key'),
                'held' => array_column($held, 'visibility_key'),
                'current' => array_column($baseCurrent, 'visibility_key'),
                'nested_current' => array_column($nestedCurrent, 'visibility_key'),
                'first_next' => array_column($firstNext, 'visibility_key'),
                'second_next' => array_column($secondNext, 'visibility_key'),
            ],
            'yield_boundary' => match ($releaseCount) {
                0 => 'recursive-view-returning-current-source-nested-drain-before-held-next169',
                1 => 'recursive-view-returning-current-source-nested-drain-first-release-next169',
                default => 'recursive-view-returning-current-source-nested-drain-all-release-next169',
            },
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next169',
                'sqlite-returning-current-source-reentrant-drain',
                'sqlite-recursive-view-returning-nested-current-before-staged-next',
            ]))),
            'dependency_closure' => 'reuses-native-recursive-view-returning-current-source-cursor-model-for-reentrant-drain',
        ];
    }

    private static function releaseCount(mixed $value): int
    {
        $count = (int) $value;
        if ($count < 0 || $count > 2) {
            throw new InvalidArgumentException('SQLite trigger recursive view next169 release count must be 0, 1, or 2');
        }

        return $count;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next169 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param mixed $steps
     * @return list<array<string,mixed>>
     */
    private static function phaseSteps(mixed $steps, string $sourcePhase, string $cursor, string $phase): array
    {
        if (!is_array($steps) || !array_is_list($steps)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next169 cursor steps are malformed');
        }

        $filtered = [];
        foreach ($steps as $step) {
            if (!is_array($step) || ($step['phase'] ?? null) !== $sourcePhase) {
                continue;
            }
            $step['cursor'] = $cursor;
            $step['phase'] = $phase;
            $filtered[] = $step;
        }

        return $filtered;
    }

    /**
     * @param list<array<string,mixed>> $steps
     * @param list<string> $early
     * @param list<string> $late
     */
    private static function orderedBefore(array $steps, array $early, array $late): bool
    {
        $seenLate = false;
        foreach ($steps as $step) {
            $phase = (string) ($step['phase'] ?? '');
            if (in_array($phase, $late, true)) {
                $seenLate = true;
            }
            if ($seenLate && in_array($phase, $early, true)) {
                return false;
            }
        }

        return true;
    }
}
