<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext206Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext203Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rows($base['current_generation_rows_next203'] ?? [], 'current generation rows');
        $nextRows = self::rows($base['attempted_next_generation_rows_next203'] ?? [], 'attempted next generation rows');
        $sourceToken = self::token((string) ($options['yield_current_source_token_next206'] ?? 'wp.current.recursive.returning.source.206'), 'current source token');
        $cursor = self::token((string) ($options['yield_current_cursor_next206'] ?? 'wp.returning.current.cursor.206'), 'current cursor');
        $statementToken = self::token((string) ($options['yield_statement_token_next206'] ?? 'wp.recursive.view.returning.statement.206'), 'statement token');
        $batchKeys = self::batchKeys($currentRows, $sourceToken, $cursor, $statementToken);
        $watermark = self::watermark($batchKeys, $sourceToken, $cursor, $statementToken);
        $expectedWatermark = self::token((string) ($options['expected_yield_watermark_next206'] ?? $watermark), 'expected watermark');
        $acknowledgedWatermark = self::token((string) ($options['acknowledged_yield_watermark_next206'] ?? $watermark), 'acknowledged watermark');
        $expectedCount = self::nonNegativeInt($options['expected_yield_row_count_next206'] ?? count($currentRows), 'expected row count');
        $baseVisible = (bool) ($base['next_source_visible_after_current_generation_next203'] ?? false);
        $watermarkMatches = hash_equals($watermark, $expectedWatermark) && hash_equals($watermark, $acknowledgedWatermark);
        $rowCountMatches = count($currentRows) === $expectedCount;
        $nextVisible = $baseVisible && $watermarkMatches && $rowCountMatches;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next203'] ?? [],
            $baseVisible,
            $watermarkMatches,
            $rowCountMatches,
        );

        $taggedCurrent = self::tagCurrentRows($currentRows, $batchKeys, $watermark, $sourceToken, $cursor, $statementToken);
        $taggedNext = self::tagNextRows($nextRows, $nextVisible, $blockedReasons, $watermark, $sourceToken, $cursor, $statementToken);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_yield_watermark_next206'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_yield_watermark_next206'],
        ));

        return [
            'status_next206' => self::status($nextVisible, $baseVisible, $watermarkMatches, $rowCountMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next206' => $baseVisible,
            'yield_current_source_token_next206' => $sourceToken,
            'yield_current_cursor_next206' => $cursor,
            'yield_statement_token_next206' => $statementToken,
            'yield_batch_keys_next206' => $batchKeys,
            'yield_watermark_next206' => $watermark,
            'expected_yield_watermark_next206' => $expectedWatermark,
            'acknowledged_yield_watermark_next206' => $acknowledgedWatermark,
            'yield_watermark_matches_next206' => $watermarkMatches,
            'yield_row_count_next206' => count($currentRows),
            'expected_yield_row_count_next206' => $expectedCount,
            'yield_row_count_matches_next206' => $rowCountMatches,
            'next_source_visible_after_yield_watermark_next206' => $nextVisible,
            'current_source_rows_next206' => $taggedCurrent,
            'attempted_next_source_rows_next206' => $taggedNext,
            'visible_returning_rows_next206' => $visibleRows,
            'held_next_source_rows_next206' => $heldRows,
            'visible_returning_payloads_next206' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next206' => array_column($heldRows, 'returning'),
            'blocked_reasons_next206' => $blockedReasons,
            'yield_watermark_plan_next206' => [
                'current_rows' => count($taggedCurrent),
                'attempted_next_rows' => count($taggedNext),
                'visible_rows' => count($visibleRows),
                'held_next_rows' => count($heldRows),
                'base_next_source_visible' => $baseVisible,
                'watermark_matches' => $watermarkMatches,
                'row_count_matches' => $rowCountMatches,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-yield-watermark'
                    : 'hold-next-source-until-current-yield-watermark',
            ],
            'yield_boundary_next206' => $nextVisible
                ? 'recursive-view-returning-next206-current-watermark-then-next'
                : 'recursive-view-returning-next206-current-watermark-fences-next',
            'dependency_closure_next206' => 'no new support component needed; reuses next203 recursive view RETURNING generation receipts and adds current-source yield watermark fencing',
            'dependencies_next206' => array_values(array_unique(array_merge($base['dependencies_next203'], [
                'sqlite-trigger-recursive-view-returning-current-source-next206',
                'sqlite-returning-current-source-yield-watermark',
                'wordpress-recursive-view-returning-current-source-next206',
            ]))),
            'non_overlap_next206' => 'adds current-source yield watermark admission after next203 generation receipts; avoids accepted next203 generation handoff, next196 recursive child drain, next195 receipt fences, next191 fingerprint fencing, row-value RETURNING, DML trigger conflicts, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function batchKeys(array $rows, string $sourceToken, string $cursor, string $statementToken): array
    {
        $keys = [];
        foreach ($rows as $index => $row) {
            $payload = $row['returning'];
            $parts = [
                $sourceToken,
                $cursor,
                $statementToken,
                (string) ($row['current_generation_receipt_next203'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($payload['name'] ?? $payload['option_name'] ?? $row['returning_option_name'] ?? ''),
            ];
            $keys[] = substr(hash('sha256', implode('|', $parts)), 0, 32);
        }

        return $keys;
    }

    /**
     * @param list<string> $batchKeys
     */
    private static function watermark(array $batchKeys, string $sourceToken, string $cursor, string $statementToken): string
    {
        if ($batchKeys === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next206 current source batch is empty');
        }

        return substr(hash('sha256', $sourceToken . '|' . $cursor . '|' . $statementToken . '|' . implode(',', $batchKeys)), 0, 32);
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next206 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next206 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $batchKeys
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentRows(array $rows, array $batchKeys, string $watermark, string $sourceToken, string $cursor, string $statementToken): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'yield_phase_next206' => 'current',
                'yield_current_source_token_next206' => $sourceToken,
                'yield_current_cursor_next206' => $cursor,
                'yield_statement_token_next206' => $statementToken,
                'yield_batch_key_next206' => $batchKeys[$index] ?? null,
                'yield_watermark_next206' => $watermark,
                'visible_after_yield_watermark_next206' => true,
                'held_by_yield_watermark_reasons_next206' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blockedReasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRows(array $rows, bool $visible, array $blockedReasons, string $watermark, string $sourceToken, string $cursor, string $statementToken): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'yield_phase_next206' => 'next',
                'yield_current_source_token_next206' => $sourceToken,
                'yield_current_cursor_next206' => $cursor,
                'yield_statement_token_next206' => $statementToken,
                'yield_batch_key_next206' => null,
                'yield_watermark_next206' => $watermark,
                'visible_after_yield_watermark_next206' => $visible,
                'held_by_yield_watermark_reasons_next206' => $visible ? [] : $blockedReasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $watermarkMatches, bool $rowCountMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next206 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'next203-generation-handoff-held';
        }
        if (!$watermarkMatches) {
            $reasons[] = 'current-yield-watermark-mismatch';
        }
        if (!$rowCountMatches) {
            $reasons[] = 'current-yield-row-count-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $nextVisible, bool $baseVisible, bool $watermarkMatches, bool $rowCountMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next206-watermark-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next206-base-held';
        }
        if (!$watermarkMatches) {
            return 'trigger-recursive-view-returning-current-source-next206-watermark-held';
        }
        if (!$rowCountMatches) {
            return 'trigger-recursive-view-returning-current-source-next206-row-count-held';
        }

        return 'trigger-recursive-view-returning-current-source-next206-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next206 {$label} is malformed");
        }

        return $token;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next206 {$label} must be a non-negative integer");
        }

        return $value;
    }
}
