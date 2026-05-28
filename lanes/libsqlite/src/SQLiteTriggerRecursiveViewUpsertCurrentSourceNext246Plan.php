<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext246Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext243Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_upsert_current_source_next243'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next243'] ?? [], 'current rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next243'] ?? [], 'next rows');
        $conflictColumns = self::columns($options['upsert_conflict_columns_next246'] ?? ['name'], 'conflict columns');
        $excludedColumns = self::columns($options['upsert_excluded_columns_next246'] ?? ['value', 'autoload_flag'], 'excluded columns');
        $oldRows = self::oldRows($baseRows, (string) ($options['key'] ?? 'option_name'));
        $sourceToken = self::token((string) ($options['current_source_conflict_image_token_next246'] ?? 'wp.current.source.conflict.image.246'), 'conflict image token');
        $expectedSourceToken = self::token((string) ($options['expected_current_source_conflict_image_token_next246'] ?? $sourceToken), 'expected conflict image token');
        $requireOrder = (bool) ($options['require_current_source_conflict_image_order_next246'] ?? true);
        $sourceMatches = hash_equals($sourceToken, $expectedSourceToken);
        $requiredReceipts = self::conflictImageReceipts($currentRows, $oldRows, $conflictColumns, $excludedColumns, $sourceToken);
        $acknowledgedReceipts = self::acknowledgedReceipts($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $conflictImagesComplete = $requiredReceipts !== []
            && $sourceMatches
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;
        $nextVisible = $baseVisible && $conflictImagesComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next243'] ?? [],
            $baseVisible,
            $sourceMatches,
            $missingReceipts,
            $unexpectedReceipts,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current-conflict-image', true, $requiredReceipts, $sourceToken, $conflictColumns, $excludedColumns, []);
        $nextRows = self::tagRows($nextRows, 'next-source', $nextVisible, [], $sourceToken, $conflictColumns, $excludedColumns, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_conflict_image_next246'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_conflict_image_next246'],
        ));

        return [
            'status_next246' => self::status($baseVisible, $sourceMatches, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next246' => $baseVisible,
            'current_source_conflict_image_token_next246' => $sourceToken,
            'expected_current_source_conflict_image_token_next246' => $expectedSourceToken,
            'current_source_conflict_image_token_matches_next246' => $sourceMatches,
            'upsert_conflict_columns_next246' => $conflictColumns,
            'upsert_excluded_columns_next246' => $excludedColumns,
            'required_current_source_conflict_image_receipts_next246' => $requiredReceipts,
            'acknowledged_current_source_conflict_image_receipts_next246' => $acknowledgedReceipts,
            'missing_current_source_conflict_image_receipts_next246' => $missingReceipts,
            'unexpected_current_source_conflict_image_receipts_next246' => $unexpectedReceipts,
            'require_current_source_conflict_image_order_next246' => $requireOrder,
            'current_source_conflict_image_order_matches_next246' => $orderMatches,
            'current_source_conflict_images_complete_next246' => $conflictImagesComplete,
            'next_source_visible_after_current_source_conflict_image_next246' => $nextVisible,
            'current_source_rows_next246' => $currentRows,
            'attempted_next_source_rows_next246' => $nextRows,
            'visible_returning_rows_next246' => $visibleRows,
            'held_next_source_rows_next246' => $heldRows,
            'visible_returning_payloads_next246' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next246' => array_column($heldRows, 'returning'),
            'current_source_row_count_next246' => count($currentRows),
            'attempted_next_source_row_count_next246' => count($nextRows),
            'visible_row_count_next246' => count($visibleRows),
            'held_next_row_count_next246' => count($heldRows),
            'blocked_reasons_next246' => $blockedReasons,
            'current_source_conflict_images_next246' => self::conflictImages($currentRows, $oldRows, $conflictColumns, $excludedColumns),
            'current_source_conflict_image_plan_next246' => [
                'base_next_source_visible' => $baseVisible,
                'source_token_matches' => $sourceMatches,
                'conflict_columns' => $conflictColumns,
                'excluded_columns' => $excludedColumns,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'conflict_images_complete' => $conflictImagesComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-conflict-images'
                    : 'hold-next-source-until-current-upsert-conflict-images',
            ],
            'yield_boundary_next246' => $nextVisible
                ? 'recursive-view-upsert-next246-current-conflict-images-then-next'
                : 'recursive-view-upsert-next246-current-conflict-images-fence-next',
            'dependency_closure_next246' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-cookies-and-adds-conflict-image-receipts',
            'dependencies_next246' => array_values(array_unique(array_merge($base['dependencies_next243'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next246',
                'sqlite-instead-of-view-upsert-conflict-image-receipts',
                'wordpress-recursive-view-upsert-current-source-next246',
            ]))),
            'non_overlap_next246' => 'adds current-source UPSERT old/excluded conflict-image receipt fencing after accepted next243 source-cookie fencing; avoids accepted next240 conflict-key receipts, next242 statement epoch, next243 cookie fence, recursive view RETURNING, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array<string,mixed>> $oldRows
     * @param list<string> $conflictColumns
     * @param list<string> $excludedColumns
     * @return list<string>
     */
    private static function conflictImageReceipts(array $rows, array $oldRows, array $conflictColumns, array $excludedColumns, string $sourceToken): array
    {
        $receipts = [];
        foreach (self::conflictImages($rows, $oldRows, $conflictColumns, $excludedColumns) as $image) {
            $receipts[] = substr(hash('sha256', json_encode([$sourceToken, $image], JSON_THROW_ON_ERROR)), 0, 46);
        }

        return $receipts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array<string,mixed>> $oldRows
     * @param list<string> $conflictColumns
     * @param list<string> $excludedColumns
     * @return list<array<string,mixed>>
     */
    private static function conflictImages(array $rows, array $oldRows, array $conflictColumns, array $excludedColumns): array
    {
        $images = [];
        foreach ($rows as $index => $row) {
            $returning = self::returning($row);
            $key = self::imageKey($returning, $conflictColumns);
            $old = $oldRows[$key] ?? null;
            $excluded = [];
            foreach ($excludedColumns as $column) {
                $excluded[$column] = $returning[$column] ?? ($returning[self::inputAlias($column)] ?? null);
            }
            $images[] = [
                'ordinal' => $index,
                'conflict_key' => $key,
                'conflict_columns' => self::valuesFor($returning, $conflictColumns),
                'old_values' => $old === null ? [] : self::valuesFor($old, array_keys($old)),
                'excluded_values' => $excluded,
                'matched_existing_row' => $old !== null,
                'upsert_action' => $old === null ? 'insert' : 'do-update',
            ];
        }

        return $images;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function oldRows(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                continue;
            }
            $out[self::scalarKey($row[$key])] = $row;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @param list<string> $columns
     */
    private static function imageKey(array $payload, array $columns): string
    {
        $parts = [];
        foreach ($columns as $column) {
            $value = $payload[$column] ?? ($payload[self::inputAlias($column)] ?? null);
            $parts[] = self::scalarKey($value);
        }

        return implode('|', $parts);
    }

    /**
     * @param array<string,mixed> $payload
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function valuesFor(array $payload, array $columns): array
    {
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = $payload[$column] ?? ($payload[self::inputAlias($column)] ?? null);
        }

        return $values;
    }

    private static function inputAlias(string $column): string
    {
        return match ($column) {
            'name' => 'option_name',
            'value' => 'option_value',
            'autoload_flag' => 'autoload',
            default => $column,
        };
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_conflict_images_next246'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_conflict_image_receipts_next246'] ?? [], 'acknowledged current source conflict image receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{46}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} contain a malformed conflict image receipt");
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
            throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} must be a list");
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function returning(array $row): array
    {
        if (!isset($row['returning']) || !is_array($row['returning'])) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next246 row missing RETURNING payload');
        }

        return $row['returning'];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $conflictColumns
     * @param list<string> $excludedColumns
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $sourceToken, array $conflictColumns, array $excludedColumns, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'conflict_image_phase_next246' => $phase,
                'current_source_conflict_image_token_next246' => $sourceToken,
                'current_source_conflict_image_receipt_next246' => $receipts[$index] ?? null,
                'upsert_conflict_columns_next246' => $conflictColumns,
                'upsert_excluded_columns_next246' => $excludedColumns,
                'visible_after_current_source_conflict_image_next246' => $visible,
                'held_by_current_source_conflict_image_reasons_next246' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $columns
     * @return list<string>
     */
    private static function columns(mixed $columns, string $label): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} must be a non-empty list");
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '' || preg_match('/\s/', $column) === 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} contain a malformed column");
            }
        }

        return array_values(array_unique($columns));
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
            throw new InvalidArgumentException('SQLite recursive view UPSERT next246 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next243-current-source-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-source-conflict-image-token-mismatch';
        }
        if ($missingReceipts !== []) {
            $reasons[] = 'current-source-conflict-image-receipt-missing';
        }
        if ($unexpectedReceipts !== []) {
            $reasons[] = 'current-source-conflict-image-receipt-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-conflict-image-receipt-order-mismatch';
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
            return 'trigger-recursive-view-upsert-current-source-next246-conflict-images-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next246-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next246-conflict-image-token-held';
        }
        if ($missingReceipts !== [] || $unexpectedReceipts !== []) {
            return 'trigger-recursive-view-upsert-current-source-next246-conflict-image-receipts-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next246-conflict-image-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next246-conflict-images-held';
    }

    private static function scalarKey(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? 'BOOL:1' : 'BOOL:0',
            is_int($value) => 'INT:' . $value,
            is_float($value) => 'FLOAT:' . sprintf('%.17g', $value),
            is_string($value) => 'TEXT:' . $value,
            default => throw new InvalidArgumentException('SQLite recursive view UPSERT next246 conflict value must be scalar'),
        };
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} is malformed");
        }

        return $value;
    }
}
