<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext211Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext209Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rows($base['current_source_rows_next209'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next209'] ?? [], 'attempted next source rows');
        $tamperedCurrentRows = self::tamperCurrentRows($currentRows, $options['tamper_current_returning_sources_next211'] ?? []);
        $sourceSeal = self::sourceSeal($tamperedCurrentRows, 'current');
        $expectedSourceSeal = self::hex((string) ($options['expected_current_source_seal_next211'] ?? $sourceSeal), 'expected current source seal');
        $expectedRowCount = self::nonNegativeInt($options['expected_current_source_row_count_next211'] ?? count($tamperedCurrentRows), 'expected current source row count');
        $actualRowCount = count($tamperedCurrentRows);
        $watermarksUnique = self::uniqueWatermarks($tamperedCurrentRows);
        $sourceSealMatches = hash_equals($sourceSeal, $expectedSourceSeal);
        $rowCountMatches = $actualRowCount === $expectedRowCount;
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_drain_next209'] ?? false);
        $currentSourceSealed = $baseVisible && $sourceSealMatches && $rowCountMatches && $watermarksUnique;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next209'] ?? [],
            $baseVisible,
            $sourceSealMatches,
            $rowCountMatches,
            $watermarksUnique,
        );

        $taggedCurrent = self::tagRows($tamperedCurrentRows, 'current', true, [], $sourceSeal, $expectedSourceSeal, $expectedRowCount);
        $taggedNext = self::tagRows($nextRows, 'next', $currentSourceSealed, $blockedReasons, $sourceSeal, $expectedSourceSeal, $expectedRowCount);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_seal_next211'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_seal_next211'],
        ));

        return [
            'status_next211' => self::status($baseVisible, $sourceSealMatches, $rowCountMatches, $watermarksUnique, $currentSourceSealed),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next211' => $baseVisible,
            'current_source_seal_next211' => $sourceSeal,
            'expected_current_source_seal_next211' => $expectedSourceSeal,
            'current_source_seal_matches_next211' => $sourceSealMatches,
            'current_source_row_count_next211' => $actualRowCount,
            'expected_current_source_row_count_next211' => $expectedRowCount,
            'current_source_row_count_matches_next211' => $rowCountMatches,
            'current_source_watermarks_unique_next211' => $watermarksUnique,
            'next_source_visible_after_current_source_seal_next211' => $currentSourceSealed,
            'current_source_rows_next211' => $taggedCurrent,
            'attempted_next_source_rows_next211' => $taggedNext,
            'visible_returning_rows_next211' => $visibleRows,
            'held_next_source_rows_next211' => $heldRows,
            'visible_returning_payloads_next211' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next211' => array_column($heldRows, 'returning'),
            'blocked_reasons_next211' => $blockedReasons,
            'current_source_seal_plan_next211' => [
                'base_next_source_visible' => $baseVisible,
                'current_rows' => $actualRowCount,
                'expected_current_rows' => $expectedRowCount,
                'row_count_matches' => $rowCountMatches,
                'source_seal' => $sourceSeal,
                'expected_source_seal' => $expectedSourceSeal,
                'source_seal_matches' => $sourceSealMatches,
                'watermarks_unique' => $watermarksUnique,
                'next_source_visible' => $currentSourceSealed,
                'decision' => $currentSourceSealed
                    ? 'publish-next-source-after-current-returning-source-seal'
                    : 'hold-next-source-until-current-returning-source-seal',
            ],
            'yield_boundary_next211' => $currentSourceSealed
                ? 'recursive-view-returning-next211-current-source-sealed-then-next'
                : 'recursive-view-returning-next211-current-source-seal-fences-next',
            'dependency_closure_next211' => 'no new support component needed; reuses recursive view RETURNING generation, current-source drain watermarks, and adds a bounded current-source RETURNING source seal',
            'dependencies_next211' => array_values(array_unique(array_merge($base['dependencies_next209'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next211',
                'sqlite-returning-current-source-seal',
                'wordpress-recursive-view-returning-current-source-next211',
            ]))),
            'non_overlap_next211' => 'adds current-source RETURNING source sealing after next209 drain watermarks; avoids next208 cursor-close fencing, next209 drain-watermark admission, next203 generation handoff, DML/row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next211 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next211 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param mixed $overrides
     * @return list<array<string,mixed>>
     */
    private static function tamperCurrentRows(array $rows, mixed $overrides): array
    {
        if ($overrides === []) {
            return $rows;
        }
        if (!is_array($overrides)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next211 source overrides must be an array');
        }
        foreach ($overrides as $index => $source) {
            if (!is_int($index) || !isset($rows[$index])) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next211 source override index is invalid');
            }
            if (!is_string($source) || $source === '' || preg_match('/\s/', $source) === 1) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next211 source override is malformed');
            }
            $rows[$index]['returning']['trigger_source_alias'] = $source;
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function sourceSeal(array $rows, string $phase): string
    {
        if ($rows === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next211 current source rows are empty');
        }
        $parts = [$phase, (string) count($rows)];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts[] = implode(':', [
                (string) ($returning['trigger_source_alias'] ?? ''),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($row['current_source_watermark_next209'] ?? ''),
            ]);
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 32);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function uniqueWatermarks(array $rows): bool
    {
        $watermarks = [];
        foreach ($rows as $row) {
            $watermark = $row['current_source_watermark_next209'] ?? null;
            if (!is_string($watermark) || preg_match('/^[a-f0-9]{32}$/', $watermark) !== 1) {
                return false;
            }
            $watermarks[] = $watermark;
        }

        return count($watermarks) === count(array_unique($watermarks));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $reasons, string $sourceSeal, string $expectedSourceSeal, int $expectedRowCount): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'source_seal_phase_next211' => $phase,
                'current_source_seal_next211' => $sourceSeal,
                'expected_current_source_seal_next211' => $expectedSourceSeal,
                'expected_current_source_row_count_next211' => $expectedRowCount,
                'visible_after_current_source_seal_next211' => $visible,
                'held_by_current_source_seal_reasons_next211' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockedReasons(
        mixed $baseReasons,
        bool $baseVisible,
        bool $sourceSealMatches,
        bool $rowCountMatches,
        bool $watermarksUnique,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next211 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'next209-current-source-drain-held';
        }
        if (!$sourceSealMatches) {
            $reasons[] = 'current-source-returning-seal-mismatch';
        }
        if (!$rowCountMatches) {
            $reasons[] = 'current-source-returning-row-count-mismatch';
        }
        if (!$watermarksUnique) {
            $reasons[] = 'current-source-returning-watermark-duplicate';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $baseVisible, bool $sourceSealMatches, bool $rowCountMatches, bool $watermarksUnique, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next211-source-sealed';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next211-base-held';
        }
        if (!$sourceSealMatches) {
            return 'trigger-recursive-view-returning-current-source-next211-source-seal-held';
        }
        if (!$rowCountMatches) {
            return 'trigger-recursive-view-returning-current-source-next211-row-count-held';
        }
        if (!$watermarksUnique) {
            return 'trigger-recursive-view-returning-current-source-next211-watermark-held';
        }

        return 'trigger-recursive-view-returning-current-source-next211-held';
    }

    private static function hex(string $value, string $label): string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next211 {$label} must be a 32-hex token");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next211 {$label} must be a non-negative integer");
        }

        return $value;
    }
}
