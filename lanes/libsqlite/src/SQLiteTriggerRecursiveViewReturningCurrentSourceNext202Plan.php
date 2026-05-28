<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext202Plan
{
    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable> $returning
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext196Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentGeneration = self::token((string) ($options['current_view_generation_next202'] ?? 'wp.current.recursive.view.202'), 'current view generation');
        $expectedGeneration = self::token((string) ($options['expected_current_view_generation_next202'] ?? $currentGeneration), 'expected current view generation');
        $nextGeneration = self::token((string) ($options['next_view_generation_next202'] ?? 'wp.next.recursive.view.202'), 'next view generation');
        $resumeBarrier = self::token((string) ($options['returning_resume_barrier_next202'] ?? 'wp.returning.resume.barrier.202'), 'returning resume barrier');
        $requiredDepths = self::depths($options['required_current_depths_next202'] ?? self::requiredDepths($base), 'required current depths');
        $acknowledgedDepths = self::depths($options['acknowledged_current_depths_next202'] ?? [], 'acknowledged current depths');
        $generationMatches = hash_equals($currentGeneration, $expectedGeneration);
        $baseAllowsNext = (bool) ($base['next_source_publish_allowed_next196'] ?? false);
        $depthsAcknowledged = self::sameSet($requiredDepths, $acknowledgedDepths);
        $publishNext = $baseAllowsNext && $generationMatches && $depthsAcknowledged;
        $blocked = self::blockedReasons($base, $baseAllowsNext, $generationMatches, $depthsAcknowledged);
        $currentRows = self::currentRows($base, $currentGeneration, $resumeBarrier, $requiredDepths);
        $nextRows = self::nextRows($base, $nextGeneration, $resumeBarrier, $publishNext, $blocked);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => $row['visible_after_current_generation_next202']
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !$row['visible_after_current_generation_next202']
        ));

        return [
            'status_next202' => self::status($publishNext, $baseAllowsNext, $generationMatches, $depthsAcknowledged),
            'base_next202' => $base,
            'savepoint_next202' => (string) ($base['savepoint'] ?? ''),
            'current_view_generation_next202' => $currentGeneration,
            'expected_current_view_generation_next202' => $expectedGeneration,
            'current_view_generation_matches_next202' => $generationMatches,
            'next_view_generation_next202' => $nextGeneration,
            'returning_resume_barrier_next202' => $resumeBarrier,
            'required_current_depths_next202' => $requiredDepths,
            'acknowledged_current_depths_next202' => $acknowledgedDepths,
            'current_depths_acknowledged_next202' => $depthsAcknowledged,
            'base_next_source_publish_allowed_next202' => $baseAllowsNext,
            'next_source_publish_allowed_next202' => $publishNext,
            'blocked_reasons_next202' => $blocked,
            'current_generation_rows_next202' => $currentRows,
            'attempted_next_generation_rows_next202' => $nextRows,
            'visible_returning_rows_next202' => $visibleRows,
            'held_next_returning_rows_next202' => $heldRows,
            'visible_returning_payloads_next202' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next202' => array_column($heldRows, 'returning'),
            'current_generation_row_count_next202' => count($currentRows),
            'attempted_next_generation_row_count_next202' => count($nextRows),
            'visible_row_count_next202' => count($visibleRows),
            'held_next_row_count_next202' => count($heldRows),
            'current_source_next_plan_next202' => [
                'base_next_source_publish_allowed' => $baseAllowsNext,
                'current_view_generation_matches' => $generationMatches,
                'required_current_depths' => $requiredDepths,
                'acknowledged_current_depths' => $acknowledgedDepths,
                'current_depths_acknowledged' => $depthsAcknowledged,
                'next_source_publish_allowed' => $publishNext,
                'decision' => self::decision($publishNext, $baseAllowsNext, $generationMatches, $depthsAcknowledged),
            ],
            'yield_boundary_next202' => $publishNext
                ? 'recursive-view-returning-next202-current-generation-depths-then-next'
                : 'recursive-view-returning-next202-current-generation-depths-fence-next',
            'dependencies_next202' => array_values(array_unique(array_merge($base['dependencies_next196'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next202',
                'sqlite-returning-current-view-generation-depth-fence',
                'wordpress-recursive-view-returning-current-source-next202',
            ]))),
            'dependency_closure_next202' => 'no new support component needed; reuses next196 recursive child drain and adds current view generation/depth acknowledgement fencing',
            'non_overlap_next202' => 'adds current view generation and recursive depth acknowledgement fencing after accepted next196 child-ordinal drains; avoids next195 receipt fences, next196 child drain, row-value RETURNING, schema reparse, FK, WAL, VFS, JSON, planner, and B-tree slices',
        ];
    }

    /**
     * @return list<int>
     */
    private static function requiredDepths(array $base): array
    {
        $depths = [];
        foreach (self::baseCurrentRows($base) as $row) {
            if (isset($row['recursive_depth_next196']) && is_int($row['recursive_depth_next196'])) {
                $depths[] = $row['recursive_depth_next196'];
                continue;
            }
            $payload = $row['returning'] ?? [];
            if (is_array($payload) && isset($payload['depth_value']) && is_int($payload['depth_value'])) {
                $depths[] = $payload['depth_value'];
            }
        }

        $depths = array_values(array_unique($depths));
        sort($depths);

        return $depths === [] ? [0] : $depths;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function baseCurrentRows(array $base): array
    {
        $rows = [];
        foreach (['base', 'recursive_child_rows_next196'] as $key) {
            $source = $key === 'base' ? ($base['base']['following_current_rows_next192'] ?? []) : ($base[$key] ?? []);
            if (!is_array($source) || !array_is_list($source)) {
                continue;
            }
            foreach ($source as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function depths(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next202 {$label} must be a list");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value < 0) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next202 {$label} must contain non-negative integers");
            }
            $out[] = $value;
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * @param list<int> $required
     * @param list<int> $acknowledged
     */
    private static function sameSet(array $required, array $acknowledged): bool
    {
        sort($required);
        sort($acknowledged);

        return $required === $acknowledged;
    }

    /**
     * @param list<int> $depths
     * @return list<array<string,mixed>>
     */
    private static function currentRows(array $base, string $generation, string $barrier, array $depths): array
    {
        $rows = [];
        foreach (self::baseCurrentRows($base) as $ordinal => $row) {
            $rows[] = $row + [
                'generation_phase_next202' => 'current',
                'current_view_generation_next202' => $generation,
                'returning_resume_barrier_next202' => $barrier,
                'required_current_depths_next202' => $depths,
                'visible_after_current_generation_next202' => true,
                'held_by_current_generation_reasons_next202' => [],
                'generation_row_ordinal_next202' => $ordinal,
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $blocked
     * @return list<array<string,mixed>>
     */
    private static function nextRows(array $base, string $generation, string $barrier, bool $visible, array $blocked): array
    {
        $rows = [];
        $source = $base['base']['base']['next_source_rows_next189']
            ?? $base['base']['next_source_rows_next192']
            ?? $base['base']['attempted_next_rows_next190']
            ?? [];
        if (!is_array($source) || !array_is_list($source)) {
            $source = [];
        }
        foreach ($source as $ordinal => $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = $row + [
                'generation_phase_next202' => 'next',
                'next_view_generation_next202' => $generation,
                'returning_resume_barrier_next202' => $barrier,
                'visible_after_current_generation_next202' => $visible,
                'held_by_current_generation_reasons_next202' => $visible ? [] : $blocked,
                'generation_row_ordinal_next202' => $ordinal,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function blockedReasons(array $base, bool $baseAllowsNext, bool $generationMatches, bool $depthsAcknowledged): array
    {
        $reasons = [];
        if (!$baseAllowsNext) {
            $baseReasons = $base['blocked_reasons_next196'] ?? [];
            $reasons = is_array($baseReasons) ? array_values(array_map('strval', $baseReasons)) : ['base-next196-held'];
        }
        if (!$generationMatches) {
            $reasons[] = 'current-view-generation-mismatch';
        }
        if (!$depthsAcknowledged) {
            $reasons[] = 'current-recursive-depths-not-acknowledged';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $publishNext, bool $baseAllowsNext, bool $generationMatches, bool $depthsAcknowledged): string
    {
        if ($publishNext) {
            return 'trigger-recursive-view-returning-current-source-next202-next-source-visible';
        }
        if (!$baseAllowsNext) {
            return 'trigger-recursive-view-returning-current-source-next202-base-held';
        }
        if (!$generationMatches) {
            return 'trigger-recursive-view-returning-current-source-next202-generation-held';
        }
        if (!$depthsAcknowledged) {
            return 'trigger-recursive-view-returning-current-source-next202-depth-held';
        }

        return 'trigger-recursive-view-returning-current-source-next202-held';
    }

    private static function decision(bool $publishNext, bool $baseAllowsNext, bool $generationMatches, bool $depthsAcknowledged): string
    {
        if ($publishNext) {
            return 'publish-next-after-current-generation-depth-acks';
        }
        if (!$baseAllowsNext) {
            return 'hold-next-until-next196-child-drain';
        }
        if (!$generationMatches) {
            return 'hold-next-current-view-generation';
        }
        if (!$depthsAcknowledged) {
            return 'hold-next-current-recursive-depths';
        }

        return 'hold-next-source';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next202 {$label} is malformed");
        }

        return $token;
    }
}
