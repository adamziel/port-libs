<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext203Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext196Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentGeneration = self::token((string) ($options['current_generation_next203'] ?? 'wp.current.recursive.returning.generation.203'), 'current generation');
        $expectedGeneration = self::token((string) ($options['expected_current_generation_next203'] ?? $currentGeneration), 'expected current generation');
        $handoffCursor = self::token((string) ($options['current_handoff_cursor_next203'] ?? 'wp.returning.current.handoff.cursor.203'), 'current handoff cursor');
        $commitMarker = self::token((string) ($options['current_generation_commit_marker_next203'] ?? 'wp.current.recursive.returning.commit.203'), 'current generation commit marker');
        $basePublishAllowed = (bool) ($base['next_source_publish_allowed_next196'] ?? false);
        $generationMatches = hash_equals($currentGeneration, $expectedGeneration);
        $requiredReceipts = self::generationReceipts(
            self::rows($base['recursive_child_rows_next196'] ?? [], 'recursive child rows'),
            $currentGeneration,
            $handoffCursor,
            $commitMarker,
        );
        $acknowledgedReceipts = self::acknowledgedReceipts($options, $requiredReceipts);
        $requireOrder = (bool) ($options['require_generation_receipt_order_next203'] ?? true);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $generationFenceClear = $requiredReceipts !== []
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;
        $nextVisible = $basePublishAllowed && $generationMatches && $generationFenceClear;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next196'] ?? [],
            $basePublishAllowed,
            $generationMatches,
            $generationFenceClear,
            $missingReceipts,
            $unexpectedReceipts,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagCurrentRows(
            self::rows($base['recursive_child_rows_next196'] ?? [], 'recursive child rows'),
            $requiredReceipts,
            $currentGeneration,
            $handoffCursor,
            $commitMarker,
        );
        $nextRows = self::tagNextRows(
            self::rows($base['base']['base']['next_source_rows_next189'] ?? [], 'next rows'),
            $nextVisible,
            $currentGeneration,
            $handoffCursor,
            $commitMarker,
            $blockedReasons,
        );
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_generation_next203'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_generation_next203'],
        ));

        return [
            'status_next203' => self::status($basePublishAllowed, $generationMatches, $generationFenceClear, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_publish_allowed_next203' => $basePublishAllowed,
            'current_generation_next203' => $currentGeneration,
            'expected_current_generation_next203' => $expectedGeneration,
            'current_generation_matches_next203' => $generationMatches,
            'current_handoff_cursor_next203' => $handoffCursor,
            'current_generation_commit_marker_next203' => $commitMarker,
            'required_current_generation_receipts_next203' => $requiredReceipts,
            'acknowledged_current_generation_receipts_next203' => $acknowledgedReceipts,
            'missing_current_generation_receipts_next203' => $missingReceipts,
            'unexpected_current_generation_receipts_next203' => $unexpectedReceipts,
            'require_generation_receipt_order_next203' => $requireOrder,
            'current_generation_receipt_order_matches_next203' => $orderMatches,
            'current_generation_fence_clear_next203' => $generationFenceClear,
            'next_source_visible_after_current_generation_next203' => $nextVisible,
            'current_generation_rows_next203' => $currentRows,
            'attempted_next_generation_rows_next203' => $nextRows,
            'visible_returning_rows_next203' => $visibleRows,
            'held_next_source_rows_next203' => $heldRows,
            'visible_returning_payloads_next203' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next203' => array_column($heldRows, 'returning'),
            'current_generation_row_count_next203' => count($currentRows),
            'attempted_next_generation_row_count_next203' => count($nextRows),
            'visible_row_count_next203' => count($visibleRows),
            'held_next_row_count_next203' => count($heldRows),
            'blocked_reasons_next203' => $blockedReasons,
            'current_generation_plan_next203' => [
                'base_next_source_publish_allowed' => $basePublishAllowed,
                'current_generation_matches' => $generationMatches,
                'required_generation_receipts' => $requiredReceipts,
                'acknowledged_generation_receipts' => $acknowledgedReceipts,
                'missing_generation_receipts' => $missingReceipts,
                'unexpected_generation_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'generation_fence_clear' => $generationFenceClear,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-generation-handoff'
                    : 'hold-next-source-until-current-generation-handoff',
            ],
            'yield_boundary_next203' => $nextVisible
                ? 'recursive-view-returning-next203-current-generation-handoff-then-next'
                : 'recursive-view-returning-next203-current-generation-handoff-fences-next',
            'dependency_closure_next203' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-handoff-fence',
            'dependencies_next203' => array_values(array_unique(array_merge($base['dependencies_next196'], [
                'sqlite-trigger-recursive-view-returning-current-source-next203',
                'sqlite-returning-current-source-generation-handoff',
                'wordpress-recursive-view-returning-current-source-next203',
            ]))),
            'non_overlap_next203' => 'adds current-source generation handoff receipts after next196 recursive child drain; avoids accepted next196 child drain, next195 receipt fence, next191 fingerprint fencing, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function generationReceipts(array $rows, string $generation, string $cursor, string $commitMarker): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $generation,
                $cursor,
                $commitMarker,
                (string) ($row['source_signature_next196'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning_option_name'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 30);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_generation_receipts_next203'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_generation_receipts_next203'] ?? [], 'acknowledged current generation receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next203 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{30}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next203 {$label} contain a malformed receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next203 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next203 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentRows(array $rows, array $receipts, string $generation, string $cursor, string $commitMarker): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'generation_phase_next203' => 'current',
                'current_generation_next203' => $generation,
                'current_handoff_cursor_next203' => $cursor,
                'current_generation_commit_marker_next203' => $commitMarker,
                'current_generation_receipt_next203' => $receipts[$index] ?? null,
                'visible_after_current_generation_next203' => true,
                'held_by_current_generation_reasons_next203' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRows(array $rows, bool $visible, string $generation, string $cursor, string $commitMarker, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'generation_phase_next203' => 'next',
                'current_generation_next203' => $generation,
                'current_handoff_cursor_next203' => $cursor,
                'current_generation_commit_marker_next203' => $commitMarker,
                'current_generation_receipt_next203' => null,
                'visible_after_current_generation_next203' => $visible,
                'held_by_current_generation_reasons_next203' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasons(
        mixed $baseReasons,
        bool $basePublishAllowed,
        bool $generationMatches,
        bool $generationFenceClear,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next203 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$basePublishAllowed && $reasons === []) {
            $reasons[] = 'base-next196-current-source-not-published';
        }
        if (!$generationMatches) {
            $reasons[] = 'current-generation-mismatch';
        }
        if (!$generationFenceClear) {
            if ($missing !== []) {
                $reasons[] = 'current-generation-receipt-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-generation-receipt-unexpected';
            }
            if ($requireOrder && !$orderMatches) {
                $reasons[] = 'current-generation-receipt-order-mismatch';
            }
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $basePublishAllowed, bool $generationMatches, bool $generationFenceClear, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next203-generation-released';
        }
        if (!$basePublishAllowed) {
            return 'trigger-recursive-view-returning-current-source-next203-base-held';
        }
        if (!$generationMatches) {
            return 'trigger-recursive-view-returning-current-source-next203-generation-held';
        }
        if (!$generationFenceClear) {
            return 'trigger-recursive-view-returning-current-source-next203-receipts-held';
        }

        return 'trigger-recursive-view-returning-current-source-next203-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next203 {$label} is malformed");
        }

        return $token;
    }
}
