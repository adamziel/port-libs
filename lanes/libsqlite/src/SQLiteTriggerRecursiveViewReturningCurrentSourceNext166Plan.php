<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext166Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $nextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_next_source?:bool,max_depth?:int,savepoint?:string,current_generation?:string,next_generation?:string,trigger_child_prefix?:string} $options
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
        $releaseNext = (bool) ($options['release_next_source'] ?? true);
        $currentGeneration = self::token((string) ($options['current_generation'] ?? 'current-source-next166'), 'current generation');
        $nextGeneration = self::token((string) ($options['next_generation'] ?? 'next-source-next166'), 'next generation');
        $savepoint = self::token((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_next166'), 'savepoint');

        $plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNext163Plan::execute(
            $rows,
            $currentRoots,
            $nextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => $releaseNext,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $savepoint,
                'current_generation' => $currentGeneration,
                'next_generation' => $nextGeneration,
                'trigger_child_prefix' => $options['trigger_child_prefix'] ?? 'audit-child',
            ],
        );

        $held = SQLiteTriggerRecursiveViewReturningCurrentSourceNext163Plan::execute(
            $rows,
            $currentRoots,
            $nextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => false,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $savepoint . '_held_probe',
                'current_generation' => $currentGeneration,
                'next_generation' => $nextGeneration,
                'trigger_child_prefix' => $options['trigger_child_prefix'] ?? 'audit-child',
            ],
        );
        $releasedProbe = $releaseNext ? $plan : SQLiteTriggerRecursiveViewReturningCurrentSourceNext163Plan::execute(
            $rows,
            $currentRoots,
            $nextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => true,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $savepoint . '_released_probe',
                'current_generation' => $currentGeneration,
                'next_generation' => $nextGeneration,
                'trigger_child_prefix' => $options['trigger_child_prefix'] ?? 'audit-child',
            ],
        );

        $currentTimeline = self::timelineRows($plan['current_returning_rows'], 'current-returning-drain', $currentGeneration, 0, true);
        $nextTimeline = $releaseNext
            ? self::timelineRows($plan['next_returning_rows'], 'next-source-after-current-drain', $nextGeneration, count($currentTimeline), true)
            : self::timelineRows($releasedProbe['next_returning_rows'], 'next-source-held-until-current-drain', $nextGeneration, count($currentTimeline), false);

        $visible = array_values(array_filter(array_merge($currentTimeline, $nextTimeline), static fn (array $row): bool => (bool) $row['visible']));
        $suppressed = array_values(array_filter(array_merge($currentTimeline, $nextTimeline), static fn (array $row): bool => !(bool) $row['visible']));
        $currentLastOrdinal = $currentTimeline === [] ? -1 : max(array_column($currentTimeline, 'ordinal'));
        $nextFirstOrdinal = $nextTimeline === [] ? null : min(array_column($nextTimeline, 'ordinal'));

        $plan['status'] = $releaseNext
            ? 'trigger-recursive-view-returning-current-drain-before-next-source-next166'
            : 'trigger-recursive-view-returning-current-drain-holds-next-source-next166';
        $plan['returning_drain'] = [
            'current_source' => $plan['source_barrier']['current_source'],
            'next_source' => $plan['source_barrier']['next_source'],
            'current_generation' => $currentGeneration,
            'next_generation' => $nextGeneration,
            'current_visible_count' => count($currentTimeline),
            'next_visible_count' => count(array_filter($nextTimeline, static fn (array $row): bool => (bool) $row['visible'])),
            'next_suppressed_count' => count(array_filter($nextTimeline, static fn (array $row): bool => !(bool) $row['visible'])),
            'current_last_ordinal' => $currentLastOrdinal,
            'next_first_ordinal' => $nextFirstOrdinal,
            'next_after_current_drain' => $nextFirstOrdinal === null || $nextFirstOrdinal > $currentLastOrdinal,
            'visible_keys' => array_column($visible, 'visibility_key'),
            'suppressed_keys' => array_column($suppressed, 'visibility_key'),
            'timeline' => array_merge($currentTimeline, $nextTimeline),
        ];
        $plan['next_source_admission'] = [
            'released' => $releaseNext,
            'seeded_by_trigger_generated_rows' => $plan['next_source_seed']['seeded_names'],
            'held_probe_seeded_names' => $held['next_source_seed']['seeded_names'],
            'held_probe_visible_keys' => $held['next_source_seed']['seeded_returning_keys'],
            'admission_reason' => $releaseNext
                ? 'current RETURNING drain completed before next source trigger seed admission'
                : 'next source remains held while current RETURNING rows are visible',
        ];
        $plan['yield_boundary'] = $releaseNext
            ? 'current-returning-drain-then-trigger-generated-next-source-next166'
            : 'current-returning-drain-with-next-source-held-next166';
        $plan['dependencies'] = array_values(array_unique(array_merge($plan['dependencies'], [
            'sqlite-trigger-recursive-view-returning-current-source-next166',
            'sqlite-returning-current-source-drain-before-next-source-admission',
            'sqlite-view-trigger-generated-rows-hidden-until-current-returning-drain',
        ])));
        $plan['dependency_closure'] = 'reuses-native-recursive-view-returning-source-barriers';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function timelineRows(array $rows, string $phase, string $generation, int $offset, bool $visible): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'] ?? [];
            if (!is_array($returning)) {
                throw new InvalidArgumentException('SQLite trigger recursive view next166 RETURNING row is malformed');
            }
            $out[] = [
                'ordinal' => $offset + $index,
                'phase' => $phase,
                'generation' => $generation,
                'visible' => $visible,
                'visibility_key' => (string) ($row['visibility_key'] ?? ($generation . ':' . ($returning['option_name'] ?? $index))),
                'option_name' => (string) ($returning['option_name'] ?? ''),
                'root_name' => (string) ($returning['root_name'] ?? ''),
                'trigger_cookie' => (string) ($returning['trigger_cookie'] ?? ''),
            ];
        }

        return $out;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next166 {$label} is malformed");
        }

        return $value;
    }
}
