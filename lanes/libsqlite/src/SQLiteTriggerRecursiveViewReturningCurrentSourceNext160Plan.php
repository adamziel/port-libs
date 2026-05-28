<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext160Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $nextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_next_source?:bool,max_depth?:int,savepoint?:string,current_generation?:string,next_generation?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentRoots,
        array $nextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $currentGeneration = self::sourceToken((string) ($options['current_generation'] ?? 'current-source-next160'));
        $nextGeneration = self::sourceToken((string) ($options['next_generation'] ?? 'next-source-next160'));
        $releaseNext = (bool) ($options['release_next_source'] ?? false);

        $plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNext157Plan::execute(
            $rows,
            $currentRoots,
            $nextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'admit_next_source' => $releaseNext,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_returning_next160',
            ],
        );

        $currentReturning = self::annotateReturning(
            $plan['current_returning_rows'],
            $currentGeneration,
            'current-returning-drained',
            true,
        );
        $attemptedNextReturning = self::annotateReturning(
            $plan['attempted_next_returning_rows'],
            $nextGeneration,
            $releaseNext ? 'next-returning-released' : 'next-returning-attempted-only',
            $releaseNext,
        );

        $plan['status'] = $releaseNext
            ? 'trigger-recursive-view-returning-current-source-release-next160'
            : 'trigger-recursive-view-returning-current-source-barrier-next160';
        $plan['current_generation'] = $currentGeneration;
        $plan['next_generation'] = $nextGeneration;
        $plan['source_barrier'] = [
            'savepoint' => $plan['savepoint'],
            'current_generation' => $currentGeneration,
            'next_generation' => $nextGeneration,
            'current_source' => $plan['current_view']['source'],
            'next_source' => $plan['next_view']['source'],
            'visible_source_before_release' => $plan['current_view']['source'],
            'visible_source_after_release' => $releaseNext ? $plan['next_view']['source'] : $plan['current_view']['source'],
            'current_returning_drained' => count($plan['current_returning_rows']),
            'next_returning_attempted' => count($plan['attempted_next_returning_rows']),
            'next_returning_visible' => $releaseNext ? count($plan['attempted_next_returning_rows']) : 0,
            'release_required_for_next_source' => true,
            'released' => $releaseNext,
        ];
        $plan['current_returning_rows'] = $currentReturning;
        $plan['attempted_next_returning_rows'] = $attemptedNextReturning;
        $plan['next_returning_rows'] = $releaseNext ? $attemptedNextReturning : [];
        $plan['returning_visibility'] = [
            'current_visible' => array_column($currentReturning, 'visibility_key'),
            'attempted_next' => array_column($attemptedNextReturning, 'visibility_key'),
            'visible' => array_column($releaseNext ? array_merge($currentReturning, $attemptedNextReturning) : $currentReturning, 'visibility_key'),
            'suppressed' => $releaseNext ? [] : array_column($attemptedNextReturning, 'visibility_key'),
        ];
        $plan['yield_boundary'] = $releaseNext
            ? 'current-source-returning-drained-release-admits-next-source-next160'
            : 'current-source-returning-drained-next-source-held-at-barrier-next160';
        $plan['dependencies'] = array_values(array_unique(array_merge($plan['dependencies'], [
            'sqlite-trigger-recursive-view-returning-current-source-next160',
            'sqlite-returning-source-generation-barrier',
            'sqlite-next-source-release-required-after-current-returning',
        ])));

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function annotateReturning(array $rows, string $generation, string $visibility, bool $visible): array
    {
        $out = [];
        foreach ($rows as $row) {
            $returning = $row['returning'] ?? [];
            if (!is_array($returning)) {
                throw new InvalidArgumentException('SQLite trigger recursive view next160 returning row is malformed');
            }
            $name = (string) ($returning['option_name'] ?? $row['ordinal'] ?? count($out));
            $row['source_generation'] = $generation;
            $row['visibility'] = $visibility;
            $row['visible_to_statement'] = $visible;
            $row['visibility_key'] = $generation . ':' . $name;
            $out[] = $row;
        }

        return $out;
    }

    private static function sourceToken(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException('SQLite trigger recursive view next160 source generation is malformed');
        }

        return $value;
    }
}
