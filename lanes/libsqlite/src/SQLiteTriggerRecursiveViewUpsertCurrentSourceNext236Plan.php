<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext236Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext233Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rows($base['current_source_rows_next233'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next233'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_upsert_source_next233'] ?? false);
        $imageToken = self::token((string) ($options['current_upsert_row_image_token_next236'] ?? 'wp.current.upsert.row.image.236'), 'current upsert row-image token');
        $expectedImageToken = self::token((string) ($options['expected_current_upsert_row_image_token_next236'] ?? $imageToken), 'expected current upsert row-image token');
        $viewSource = self::token((string) ($options['current_upsert_row_image_view_source_next236'] ?? ($base['current_upsert_view_source_next233'] ?? ($currentView['source'] ?? 'main@view-upsert-236-current'))), 'current upsert row-image view source');
        $expectedViewSource = self::token((string) ($options['expected_current_upsert_row_image_view_source_next236'] ?? $viewSource), 'expected current upsert row-image view source');
        $triggerSource = self::token((string) ($options['current_upsert_row_image_trigger_source_next236'] ?? ($base['current_upsert_trigger_source_next233'] ?? ($currentView['trigger_source'] ?? 'main@trigger-upsert-236-current'))), 'current upsert row-image trigger source');
        $expectedTriggerSource = self::token((string) ($options['expected_current_upsert_row_image_trigger_source_next236'] ?? $triggerSource), 'expected current upsert row-image trigger source');
        $requireOrder = (bool) ($options['require_current_upsert_row_image_order_next236'] ?? true);

        $requiredReceipts = self::rowImageReceipts($currentRows, $imageToken, $viewSource, $triggerSource);
        $acknowledgedReceipts = self::acknowledgedReceipts($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $imageMatches = hash_equals($imageToken, $expectedImageToken);
        $viewMatches = hash_equals($viewSource, $expectedViewSource);
        $triggerMatches = hash_equals($triggerSource, $expectedTriggerSource);
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $currentImages = self::rowImages($currentRows);
        $hasImages = $requiredReceipts !== [] && $currentImages['total'] > 0;
        $imageComplete = $hasImages
            && $imageMatches
            && $viewMatches
            && $triggerMatches
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;
        $nextVisible = $baseVisible && $imageComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next233'] ?? [],
            $baseVisible,
            $hasImages,
            $imageMatches,
            $viewMatches,
            $triggerMatches,
            $missingReceipts,
            $unexpectedReceipts,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredReceipts, $imageToken, $viewSource, $triggerSource, []);
        $nextRows = self::tagRows($nextRows, 'next', $nextVisible, [], $imageToken, $viewSource, $triggerSource, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_upsert_row_image_next236'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_upsert_row_image_next236'],
        ));

        return [
            'status_next236' => self::status($nextVisible, $baseVisible, $hasImages, $imageMatches, $viewMatches, $triggerMatches, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next236' => $baseVisible,
            'current_upsert_row_image_token_next236' => $imageToken,
            'expected_current_upsert_row_image_token_next236' => $expectedImageToken,
            'current_upsert_row_image_token_matches_next236' => $imageMatches,
            'current_upsert_row_image_view_source_next236' => $viewSource,
            'expected_current_upsert_row_image_view_source_next236' => $expectedViewSource,
            'current_upsert_row_image_view_source_matches_next236' => $viewMatches,
            'current_upsert_row_image_trigger_source_next236' => $triggerSource,
            'expected_current_upsert_row_image_trigger_source_next236' => $expectedTriggerSource,
            'current_upsert_row_image_trigger_source_matches_next236' => $triggerMatches,
            'current_upsert_row_images_next236' => $currentImages,
            'current_upsert_row_image_has_rows_next236' => $hasImages,
            'required_current_upsert_row_image_receipts_next236' => $requiredReceipts,
            'acknowledged_current_upsert_row_image_receipts_next236' => $acknowledgedReceipts,
            'missing_current_upsert_row_image_receipts_next236' => $missingReceipts,
            'unexpected_current_upsert_row_image_receipts_next236' => $unexpectedReceipts,
            'require_current_upsert_row_image_order_next236' => $requireOrder,
            'current_upsert_row_image_order_matches_next236' => $orderMatches,
            'current_upsert_row_image_complete_next236' => $imageComplete,
            'next_source_visible_after_current_upsert_row_image_next236' => $nextVisible,
            'current_source_rows_next236' => $currentRows,
            'attempted_next_source_rows_next236' => $nextRows,
            'visible_returning_rows_next236' => $visibleRows,
            'held_next_source_rows_next236' => $heldRows,
            'visible_returning_payloads_next236' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next236' => array_column($heldRows, 'returning'),
            'current_source_row_count_next236' => count($currentRows),
            'attempted_next_source_row_count_next236' => count($nextRows),
            'visible_row_count_next236' => count($visibleRows),
            'held_next_row_count_next236' => count($heldRows),
            'blocked_reasons_next236' => $blockedReasons,
            'current_upsert_row_image_plan_next236' => [
                'base_next_source_visible' => $baseVisible,
                'has_row_images' => $hasImages,
                'image_token_matches' => $imageMatches,
                'view_source_matches' => $viewMatches,
                'trigger_source_matches' => $triggerMatches,
                'row_images' => $currentImages,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'row_image_complete' => $imageComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-view-upsert-row-images'
                    : 'hold-next-source-until-current-view-upsert-row-images',
            ],
            'yield_boundary_next236' => $nextVisible
                ? 'recursive-view-upsert-next236-current-row-images-then-next'
                : 'recursive-view-upsert-next236-current-row-image-fences-next',
            'dependency_closure_next236' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-and-adds-row-image-receipts',
            'dependencies_next236' => array_values(array_unique(array_merge($base['dependencies_next233'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next236',
                'sqlite-current-view-upsert-row-image-receipts',
                'wordpress-recursive-view-upsert-current-source-next236',
            ]))),
            'non_overlap_next236' => 'adds current recursive view UPSERT row-image receipts after accepted next233 conflict-target/update-column seals; avoids next233 UPSERT source seal, recursive view RETURNING generation/source handoffs, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function rowImageReceipts(array $rows, string $imageToken, string $viewSource, string $triggerSource): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $imageToken,
                $viewSource,
                $triggerSource,
                (string) ($row['current_upsert_seal_next233'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['depth_value'] ?? ''),
                (string) ($returning['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 48);
        }

        return $receipts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{total:int,insert:int,update:int,recursive:int,names:list<string>,events:list<string>,max_depth:int}
     */
    private static function rowImages(array $rows): array
    {
        $images = ['total' => 0, 'insert' => 0, 'update' => 0, 'recursive' => 0, 'names' => [], 'events' => [], 'max_depth' => 0];
        foreach ($rows as $row) {
            $returning = $row['returning'];
            $event = strtolower((string) ($returning['event_name'] ?? 'insert'));
            $depth = (int) ($returning['depth_value'] ?? 0);
            ++$images['total'];
            if ($event === 'update') {
                ++$images['update'];
            } else {
                ++$images['insert'];
                $event = 'insert';
            }
            if ($depth > 0) {
                ++$images['recursive'];
            }
            $images['max_depth'] = max($images['max_depth'], $depth);
            $images['names'][] = (string) ($returning['name'] ?? '');
            $images['events'][] = $event;
        }

        return $images;
    }

    /** @param array<string,mixed> $options @param list<string> $required @return list<string> */
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_upsert_row_image_receipts_next236'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_upsert_row_image_receipts_next236'] ?? [], 'acknowledged current upsert row-image receipts');
    }

    /** @param mixed $values @return list<string> */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next236 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next236 {$label} contain a malformed row-image receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /** @param mixed $rows @return list<array<string,mixed>> */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next236 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next236 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $imageToken, string $viewSource, string $triggerSource, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_row_image_phase_next236' => $phase,
                'current_upsert_row_image_token_next236' => $imageToken,
                'current_upsert_row_image_view_source_next236' => $viewSource,
                'current_upsert_row_image_trigger_source_next236' => $triggerSource,
                'current_upsert_row_image_receipt_next236' => $receipts[$index] ?? null,
                'visible_after_current_upsert_row_image_next236' => $visible,
                'held_by_current_upsert_row_image_reasons_next236' => $visible ? [] : $reasons,
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
        bool $baseVisible,
        bool $hasImages,
        bool $imageMatches,
        bool $viewMatches,
        bool $triggerMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        $reasons = is_array($baseReasons) && array_is_list($baseReasons) ? $baseReasons : [];
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'current-upsert-source-base-held';
        }
        if (!$hasImages) {
            $reasons[] = 'current-upsert-row-image-empty';
        }
        if (!$imageMatches) {
            $reasons[] = 'current-upsert-row-image-token-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-upsert-row-image-view-source-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-upsert-row-image-trigger-source-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-upsert-row-image-receipt-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-upsert-row-image-receipt-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-upsert-row-image-receipt-order-mismatch';
        }

        return array_values(array_unique(array_map('strval', $reasons)));
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next236 {$label} is malformed");
        }

        return $value;
    }

    /** @param list<string> $missing @param list<string> $unexpected */
    private static function status(
        bool $nextVisible,
        bool $baseVisible,
        bool $hasImages,
        bool $imageMatches,
        bool $viewMatches,
        bool $triggerMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next236-row-image-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next236-base-held';
        }
        if (!$hasImages) {
            return 'trigger-recursive-view-upsert-current-source-next236-empty-held';
        }
        if (!$imageMatches) {
            return 'trigger-recursive-view-upsert-current-source-next236-row-image-token-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-upsert-current-source-next236-view-source-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next236-trigger-source-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next236-row-image-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next236-row-image-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next236-held';
    }
}
