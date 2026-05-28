<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext249Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext246Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_conflict_image_next246'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next246'] ?? [], 'current rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next246'] ?? [], 'next rows');
        $assignmentColumns = self::columns($options['upsert_assignment_columns_next249'] ?? ($base['upsert_excluded_columns_next246'] ?? ['value']), 'assignment columns');
        $sourceToken = self::token((string) ($options['current_source_assignment_token_next249'] ?? 'wp.current.source.assignment.249'), 'assignment token');
        $expectedSourceToken = self::token((string) ($options['expected_current_source_assignment_token_next249'] ?? $sourceToken), 'expected assignment token');
        $sourceMatches = hash_equals($sourceToken, $expectedSourceToken);
        $requireOrder = (bool) ($options['require_current_source_assignment_order_next249'] ?? true);
        $assignments = self::assignmentImages($base['current_source_conflict_images_next246'] ?? [], $assignmentColumns);
        $requiredReceipts = self::assignmentReceipts($assignments, $sourceToken);
        $acknowledgedReceipts = self::acknowledgedReceipts($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $assignmentsComplete = $requiredReceipts !== []
            && $sourceMatches
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;
        $nextVisible = $baseVisible && $assignmentsComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next246'] ?? [],
            $baseVisible,
            $sourceMatches,
            $missingReceipts,
            $unexpectedReceipts,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current-assignment', true, $requiredReceipts, $sourceToken, $assignmentColumns, []);
        $nextRows = self::tagRows($nextRows, 'next-source', $nextVisible, [], $sourceToken, $assignmentColumns, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_assignment_next249'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_assignment_next249'],
        ));

        return [
            'status_next249' => self::status($baseVisible, $sourceMatches, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next249' => $baseVisible,
            'current_source_assignment_token_next249' => $sourceToken,
            'expected_current_source_assignment_token_next249' => $expectedSourceToken,
            'current_source_assignment_token_matches_next249' => $sourceMatches,
            'upsert_assignment_columns_next249' => $assignmentColumns,
            'current_source_assignment_images_next249' => $assignments,
            'required_current_source_assignment_receipts_next249' => $requiredReceipts,
            'acknowledged_current_source_assignment_receipts_next249' => $acknowledgedReceipts,
            'missing_current_source_assignment_receipts_next249' => $missingReceipts,
            'unexpected_current_source_assignment_receipts_next249' => $unexpectedReceipts,
            'require_current_source_assignment_order_next249' => $requireOrder,
            'current_source_assignment_order_matches_next249' => $orderMatches,
            'current_source_assignments_complete_next249' => $assignmentsComplete,
            'next_source_visible_after_current_source_assignment_next249' => $nextVisible,
            'current_source_rows_next249' => $currentRows,
            'attempted_next_source_rows_next249' => $nextRows,
            'visible_returning_rows_next249' => $visibleRows,
            'held_next_source_rows_next249' => $heldRows,
            'visible_returning_payloads_next249' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next249' => array_column($heldRows, 'returning'),
            'visible_row_count_next249' => count($visibleRows),
            'held_next_row_count_next249' => count($heldRows),
            'blocked_reasons_next249' => $blockedReasons,
            'current_source_assignment_plan_next249' => [
                'base_next_source_visible' => $baseVisible,
                'source_token_matches' => $sourceMatches,
                'assignment_columns' => $assignmentColumns,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'assignments_complete' => $assignmentsComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-assignments'
                    : 'hold-next-source-until-current-upsert-assignments',
            ],
            'yield_boundary_next249' => $nextVisible
                ? 'recursive-view-upsert-next249-current-assignments-then-next'
                : 'recursive-view-upsert-next249-current-assignments-fence-next',
            'dependency_closure_next249' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-conflict-images-and-adds-do-update-assignment-receipts',
            'dependencies_next249' => array_values(array_unique(array_merge($base['dependencies_next246'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next249',
                'sqlite-instead-of-view-upsert-do-update-assignment-receipts',
                'wordpress-recursive-view-upsert-current-source-next249',
            ]))),
            'non_overlap_next249' => 'adds current-source UPSERT DO UPDATE assignment receipt fencing after accepted next246 conflict-image fencing; avoids accepted next240 conflict-key receipts, next242 statement epoch, next243 cookie fence, next246 old/excluded conflict images, recursive view RETURNING, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $images
     * @param list<string> $assignmentColumns
     * @return list<array<string,mixed>>
     */
    private static function assignmentImages(mixed $images, array $assignmentColumns): array
    {
        if (!is_array($images) || !array_is_list($images)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next249 conflict images must be a list');
        }

        $out = [];
        foreach ($images as $index => $image) {
            if (!is_array($image)) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next249 conflict image is malformed');
            }
            $excluded = isset($image['excluded_values']) && is_array($image['excluded_values']) ? $image['excluded_values'] : [];
            $old = isset($image['old_values']) && is_array($image['old_values']) ? $image['old_values'] : [];
            $assignments = [];
            foreach ($assignmentColumns as $column) {
                $assignments[$column] = [
                    'old' => $old[$column] ?? null,
                    'excluded' => $excluded[$column] ?? null,
                    'final' => $excluded[$column] ?? ($old[$column] ?? null),
                    'source' => array_key_exists($column, $excluded) ? 'excluded' : 'old',
                ];
            }
            $out[] = [
                'ordinal' => (int) ($image['ordinal'] ?? $index),
                'conflict_key' => (string) ($image['conflict_key'] ?? ''),
                'upsert_action' => (string) ($image['upsert_action'] ?? 'insert'),
                'matched_existing_row' => (bool) ($image['matched_existing_row'] ?? false),
                'assignment_columns' => $assignmentColumns,
                'assignments' => $assignments,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $assignments
     * @return list<string>
     */
    private static function assignmentReceipts(array $assignments, string $sourceToken): array
    {
        $receipts = [];
        foreach ($assignments as $assignment) {
            $receipts[] = substr(hash('sha256', json_encode([$sourceToken, $assignment], JSON_THROW_ON_ERROR)), 0, 46);
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
        if (($options['auto_ack_current_source_assignments_next249'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_assignment_receipts_next249'] ?? [], 'acknowledged current source assignment receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{46}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} contain a malformed assignment receipt");
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
            throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} must be a list");
        }

        return $rows;
    }

    /**
     * @param mixed $columns
     * @return list<string>
     */
    private static function columns(mixed $columns, string $label): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} must be a non-empty list");
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '' || preg_match('/\s/', $column) === 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} contain a malformed column");
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $assignmentColumns
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $sourceToken, array $assignmentColumns, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'assignment_phase_next249' => $phase,
                'current_source_assignment_token_next249' => $sourceToken,
                'current_source_assignment_receipt_next249' => $receipts[$index] ?? null,
                'upsert_assignment_columns_next249' => $assignmentColumns,
                'visible_after_current_source_assignment_next249' => $visible,
                'held_by_current_source_assignment_reasons_next249' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missingReceipts
     * @param list<string> $unexpectedReceipts
     * @return list<string>
     */
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $sourceMatches, array $missingReceipts, array $unexpectedReceipts, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next249 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next246-current-source-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-source-assignment-token-mismatch';
        }
        if ($missingReceipts !== []) {
            $reasons[] = 'current-source-assignment-receipt-missing';
        }
        if ($unexpectedReceipts !== []) {
            $reasons[] = 'current-source-assignment-receipt-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-assignment-receipt-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missingReceipts
     * @param list<string> $unexpectedReceipts
     */
    private static function status(bool $baseVisible, bool $sourceMatches, array $missingReceipts, array $unexpectedReceipts, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next249-assignments-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next249-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next249-assignment-token-held';
        }
        if ($missingReceipts !== [] || $unexpectedReceipts !== []) {
            return 'trigger-recursive-view-upsert-current-source-next249-assignment-receipts-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next249-assignment-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next249-assignments-held';
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} is malformed");
        }

        return $value;
    }
}
