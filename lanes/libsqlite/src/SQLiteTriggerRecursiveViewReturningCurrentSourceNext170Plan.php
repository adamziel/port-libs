<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext170Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $firstNextRoots
     * @param list<array<string,mixed>> $secondNextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_staged_sources?:int,max_depth?:int,savepoint?:string,current_generation?:string,first_next_generation?:string,second_next_generation?:string,cursor_name?:string,current_schema_cookie?:int,next_schema_cookie?:int,reprepare_token?:string,expected_reprepare_token?:string} $options
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
        $currentCookie = self::nonNegativeInt($options['current_schema_cookie'] ?? 1, 'current schema cookie');
        $nextCookie = self::nonNegativeInt($options['next_schema_cookie'] ?? $currentCookie, 'next schema cookie');
        $token = self::token((string) ($options['reprepare_token'] ?? 'wp-recursive-view-returning-next170'), 'reprepare token');
        $expectedToken = self::token((string) ($options['expected_reprepare_token'] ?? $token), 'expected reprepare token');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext165Plan::execute(
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
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_next170',
                'current_generation' => $options['current_generation'] ?? 'wp-import-current-170',
                'first_next_generation' => $options['first_next_generation'] ?? 'wp-import-next-170-a',
                'second_next_generation' => $options['second_next_generation'] ?? 'wp-import-next-170-b',
                'cursor_name' => $options['cursor_name'] ?? 'wp_recursive_view_returning_cursor_170',
            ],
        );

        $sourceChanged = self::sourceChanged($currentView, $nextView) || $currentCookie !== $nextCookie;
        $tokenMatches = $token === $expectedToken;
        $releaseRequested = (int) $base['source_next_plan']['release_count'];
        $releaseAllowed = !$sourceChanged || $tokenMatches;
        $steps = self::barrierSteps($base['cursor_steps'], $sourceChanged, $releaseAllowed, $currentCookie, $nextCookie, $token, $expectedToken);
        $visible = array_values(array_filter($steps, static fn (array $step): bool => $step['visible_after_barrier']));
        $held = array_values(array_filter($steps, static fn (array $step): bool => !$step['visible_after_barrier']));
        $current = array_values(array_filter($steps, static fn (array $step): bool => $step['phase'] === 'current'));
        $staged = array_values(array_filter($steps, static fn (array $step): bool => $step['phase'] !== 'current'));

        return [
            'status' => self::status($sourceChanged, $releaseRequested, $releaseAllowed),
            'savepoint' => $base['savepoint'],
            'cursor' => $base['cursor'],
            'current_schema_cookie' => $currentCookie,
            'next_schema_cookie' => $nextCookie,
            'reprepare_token' => $token,
            'expected_reprepare_token' => $expectedToken,
            'source_changed' => $sourceChanged,
            'reprepare_required' => $sourceChanged,
            'reprepare_token_matches' => $tokenMatches,
            'release_requested' => $releaseRequested,
            'release_allowed' => $releaseAllowed,
            'base' => $base,
            'barrier_steps' => $steps,
            'visible_barrier_steps' => $visible,
            'held_barrier_steps' => $held,
            'current_barrier_steps' => $current,
            'staged_barrier_steps' => $staged,
            'visible_keys' => array_column($visible, 'visibility_key'),
            'held_keys' => array_column($held, 'visibility_key'),
            'current_keys' => array_column($current, 'visibility_key'),
            'staged_keys' => array_column($staged, 'visibility_key'),
            'statement_rows' => count($visible),
            'attempted_statement_rows' => count($steps),
            'current_drained_before_next' => self::currentBeforeStaged($steps),
            'returning_barrier' => [
                'current_source_visible' => count($current),
                'staged_source_attempted' => count($staged),
                'staged_source_visible' => count($visible) - count($current),
                'staged_source_held' => count($held),
                'reason' => $sourceChanged && !$releaseAllowed
                    ? 'next view or trigger source changed before matching reprepare token'
                    : 'current RETURNING stream drained before next source visibility',
            ],
            'yield_boundary' => $sourceChanged
                ? 'recursive-view-returning-current-source-reprepare-barrier-next170'
                : 'recursive-view-returning-current-source-drain-barrier-next170',
            'dependency_closure' => 'reuses-native-recursive-view-returning-current-source-cursor-model',
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next170',
                'sqlite-recursive-view-returning-current-source-drain-reprepare-barrier',
                'sqlite-returning-current-source-next-token-admission',
            ]))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $steps
     * @return list<array<string,mixed>>
     */
    private static function barrierSteps(array $steps, bool $sourceChanged, bool $releaseAllowed, int $currentCookie, int $nextCookie, string $token, string $expectedToken): array
    {
        $out = [];
        foreach ($steps as $ordinal => $step) {
            if (!is_array($step) || !isset($step['phase'], $step['visibility_key'])) {
                throw new InvalidArgumentException('SQLite trigger recursive view next170 cursor step is malformed');
            }
            $phase = (string) $step['phase'];
            $baseVisible = (bool) ($step['visible'] ?? false);
            $current = $phase === 'current';
            $visible = $current || ($baseVisible && $releaseAllowed);
            $step['barrier_ordinal'] = $ordinal;
            $step['schema_cookie'] = $current ? $currentCookie : $nextCookie;
            $step['reprepare_token'] = $current ? null : $token;
            $step['expected_reprepare_token'] = $current ? null : $expectedToken;
            $step['source_changed'] = $sourceChanged;
            $step['visible_after_barrier'] = $visible;
            $step['held_by_reprepare_barrier'] = !$current && $baseVisible && !$releaseAllowed;
            $step['held_by_current_source'] = !$current && !$visible;
            $step['drained_current_before_step'] = !$current;
            $out[] = $step;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     */
    private static function sourceChanged(array $currentView, array $nextView): bool
    {
        foreach (['source', 'trigger_source', 'trigger', 'name'] as $key) {
            if ((string) ($currentView[$key] ?? '') !== (string) ($nextView[$key] ?? '')) {
                return true;
            }
        }

        return false;
    }

    private static function status(bool $sourceChanged, int $releaseRequested, bool $releaseAllowed): string
    {
        if ($sourceChanged && !$releaseAllowed) {
            return 'trigger-recursive-view-returning-current-source-reprepare-held-next170';
        }
        if ($releaseRequested > 0) {
            return 'trigger-recursive-view-returning-current-source-reprepared-next170';
        }

        return 'trigger-recursive-view-returning-current-source-drained-next170';
    }

    /**
     * @param list<array<string,mixed>> $steps
     */
    private static function currentBeforeStaged(array $steps): bool
    {
        $seenStaged = false;
        foreach ($steps as $step) {
            if (($step['phase'] ?? null) !== 'current') {
                $seenStaged = true;
                continue;
            }
            if ($seenStaged) {
                return false;
            }
        }

        return true;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        $int = (int) $value;
        if ($int < 0) {
            throw new InvalidArgumentException("SQLite trigger recursive view next170 {$label} must be non-negative");
        }

        return $int;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next170 {$label} is malformed");
        }

        return $value;
    }
}
