<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext200Plan
{
    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext194Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + [
                'admit_next_source' => true,
                'auto_ack_current' => true,
                'cursor_name' => 'wp_recursive_view_returning_cursor_200',
                'current_generation' => 'wp-current-returning-200',
                'next_generation' => 'wp-next-returning-200',
                'checkpoint_name' => 'wp_recursive_view_checkpoint_200',
                'handoff_token' => 'wp.returning.current.source.handoff.200',
                'savepoint' => 'wp_recursive_view_returning_next200',
                'drain_ticket_prefix' => 'wp.returning.current.source.drain.200',
                'resume_source_prefix' => 'wp.returning.current.source.resume.200',
            ],
        );

        $currentRows = self::rows($base['current_source_rows'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows'] ?? [], 'attempted next source rows');
        $actualDrainCount = count($currentRows);
        $expectedDrainCount = self::nonNegativeInt($options['expected_current_drain_count_next200'] ?? $actualDrainCount, 'expected current drain count');
        $actualHighWater = self::lastResumeToken($currentRows);
        $expectedHighWater = self::token((string) ($options['expected_current_highwater_token_next200'] ?? $actualHighWater), 'expected current highwater token');
        $currentGeneration = self::token((string) ($base['current_step_epoch_next194'] ?? 'epoch200:missing'), 'current step epoch');
        $expectedGeneration = self::token((string) ($options['expected_current_generation_epoch_next200'] ?? $currentGeneration), 'expected current generation epoch');

        $baseAdmitted = (bool) ($base['next_source_exposed_after_current_done_next194'] ?? false);
        $drainCountMatches = $actualDrainCount === $expectedDrainCount;
        $highWaterMatches = hash_equals($expectedHighWater, $actualHighWater);
        $generationMatches = hash_equals($expectedGeneration, $currentGeneration);
        $nextVisible = $baseAdmitted && $drainCountMatches && $highWaterMatches && $generationMatches;
        $blockReasons = self::blockReasons(
            $base['block_reasons_next194'] ?? [],
            $baseAdmitted,
            $drainCountMatches,
            $highWaterMatches,
            $generationMatches,
        );

        $taggedCurrent = self::tagRows($currentRows, true, [], $actualDrainCount, $actualHighWater, $currentGeneration);
        $taggedNext = self::tagRows($nextRows, $nextVisible, $blockReasons, $actualDrainCount, $actualHighWater, $currentGeneration);
        $visibleRows = array_values(array_filter(array_merge($taggedCurrent, $taggedNext), static fn (array $row): bool => $row['visible_after_current_highwater_next200']));
        $heldRows = array_values(array_filter($taggedNext, static fn (array $row): bool => !$row['visible_after_current_highwater_next200']));

        return [
            'status_next200' => self::status($nextVisible, $baseAdmitted, $drainCountMatches, $highWaterMatches, $generationMatches),
            'base' => $base,
            'current_drain_count_next200' => $actualDrainCount,
            'expected_current_drain_count_next200' => $expectedDrainCount,
            'current_drain_count_matches_next200' => $drainCountMatches,
            'current_highwater_token_next200' => $actualHighWater,
            'expected_current_highwater_token_next200' => $expectedHighWater,
            'current_highwater_token_matches_next200' => $highWaterMatches,
            'current_generation_epoch_next200' => $currentGeneration,
            'expected_current_generation_epoch_next200' => $expectedGeneration,
            'current_generation_epoch_matches_next200' => $generationMatches,
            'base_next_exposed_before_highwater_next200' => $baseAdmitted,
            'next_source_exposed_after_current_highwater_next200' => $nextVisible,
            'current_source_rows_next200' => $taggedCurrent,
            'attempted_next_source_rows_next200' => $taggedNext,
            'visible_rows_next200' => $visibleRows,
            'held_rows_next200' => $heldRows,
            'visible_returning_rows_next200' => array_column($visibleRows, 'returning'),
            'held_returning_rows_next200' => array_column($heldRows, 'returning'),
            'block_reasons_next200' => $blockReasons,
            'current_highwater_plan_next200' => [
                'current_rows' => count($taggedCurrent),
                'attempted_next_rows' => count($taggedNext),
                'visible_rows' => count($visibleRows),
                'held_next_rows' => count($heldRows),
                'current_highwater_token' => $actualHighWater,
                'current_drain_count_matches' => $drainCountMatches,
                'current_highwater_token_matches' => $highWaterMatches,
                'current_generation_epoch_matches' => $generationMatches,
                'decision' => $nextVisible ? 'admit-next-source-after-current-highwater' : 'hold-next-source-until-current-highwater',
                'blocked_at_resume_token' => $nextVisible || $taggedNext === [] ? null : (string) ($taggedNext[0]['resume_token'] ?? ''),
            ],
            'counts_next200' => [
                'current_rows' => count($taggedCurrent),
                'attempted_next_rows' => count($taggedNext),
                'visible_rows' => count($visibleRows),
                'held_rows' => count($heldRows),
                'block_reasons' => count($blockReasons),
            ],
            'yield_boundary_next200' => $nextVisible
                ? 'recursive-view-returning-current-source-next200-current-highwater-next-exposed'
                : 'recursive-view-returning-current-source-next200-current-highwater-held',
            'dependencies_next200' => array_values(array_unique(array_merge($base['dependencies_next194'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next200',
                'sqlite-returning-current-source-highwater-gate',
                'wordpress-recursive-view-returning-current-source-next200',
            ]))),
            'dependency_closure_next200' => 'no new support component needed; reuses recursive view trigger RETURNING resume rows and adds current-source drain high-water gating',
            'non_overlap_next200' => 'extends accepted next194 SQLITE_DONE/source-cookie gate with current drain-count and high-water resume-token admission; avoids next194 done-gate repeats, next187 drain tickets, row-value RETURNING, schema reparse, WAL, pager, B-tree, JSON, PRAGMA, and encoding slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, bool $visible, array $blockReasons, int $drainCount, string $highWater, string $generation): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'current_drain_count_next200' => $drainCount,
                'current_highwater_token_next200' => $highWater,
                'current_generation_epoch_next200' => $generation,
                'visible_after_current_highwater_next200' => $visible,
                'held_by_current_highwater_reasons_next200' => $visible ? [] : $blockReasons,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next200 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning'], $row['resume_token'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next200 {$label} row envelope is malformed");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function lastResumeToken(array $rows): string
    {
        $last = $rows[array_key_last($rows)] ?? null;
        if (!is_array($last) || !isset($last['resume_token'])) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next200 current highwater row is missing');
        }

        return self::token((string) $last['resume_token'], 'current highwater token');
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockReasons(mixed $baseReasons, bool $baseAdmitted, bool $drainCountMatches, bool $highWaterMatches, bool $generationMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next200 base block reasons must be a list');
        }

        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseAdmitted && $reasons === []) {
            $reasons[] = 'current-source-done-gate-held';
        }
        if (!$drainCountMatches) {
            $reasons[] = 'current-source-drain-count-mismatch';
        }
        if (!$highWaterMatches) {
            $reasons[] = 'current-source-highwater-token-mismatch';
        }
        if (!$generationMatches) {
            $reasons[] = 'current-source-generation-epoch-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $visible, bool $baseAdmitted, bool $drainCountMatches, bool $highWaterMatches, bool $generationMatches): string
    {
        if ($visible) {
            return 'trigger-recursive-view-returning-current-source-next200-next-exposed';
        }
        if (!$baseAdmitted) {
            return 'trigger-recursive-view-returning-current-source-next200-done-gate-held';
        }
        if (!$drainCountMatches) {
            return 'trigger-recursive-view-returning-current-source-next200-drain-count-held';
        }
        if (!$highWaterMatches) {
            return 'trigger-recursive-view-returning-current-source-next200-highwater-held';
        }
        if (!$generationMatches) {
            return 'trigger-recursive-view-returning-current-source-next200-generation-held';
        }

        return 'trigger-recursive-view-returning-current-source-next200-held';
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next200 {$label} is malformed");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next200 {$label} is malformed");
        }

        return $value;
    }
}
