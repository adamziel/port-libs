<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext256Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext253Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_view_materialization_next253'] ?? false);
        $materialized = self::rows($base['current_source_view_materialization_rows_next253'] ?? [], 'materialized rows');
        $currentRows = self::rows($base['current_source_rows_next253'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next253'] ?? [], 'attempted next source rows');
        $handoffToken = self::token((string) ($options['current_source_view_upsert_handoff_token_next256'] ?? 'wp.current.source.view.upsert.handoff.256'), 'handoff token');
        $expectedToken = self::token((string) ($options['expected_current_source_view_upsert_handoff_token_next256'] ?? $handoffToken), 'expected handoff token');
        $batchSize = self::positiveInt($options['current_source_view_upsert_handoff_batch_size_next256'] ?? 1, 'handoff batch size');
        $requireOrder = (bool) ($options['require_current_source_view_upsert_handoff_order_next256'] ?? true);
        $batches = self::handoffBatches($materialized, $handoffToken, $batchSize);
        $required = array_column($batches, 'handoff_receipt');
        $acknowledged = self::acknowledgedReceipts($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $missing !== [] || $unexpected !== [] || $required === $acknowledged;
        $tokenMatches = hash_equals($handoffToken, $expectedToken);
        $complete = $required !== []
            && $baseVisible
            && $tokenMatches
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next253'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $currentTagged = self::tagRows($currentRows, true, $handoffToken, $batches, $blockedReasons);
        $nextTagged = self::tagRows($nextRows, $complete, $handoffToken, [], $complete ? [] : $blockedReasons);
        $visibleRows = $complete ? array_merge($currentTagged, $nextTagged) : $currentTagged;
        $heldRows = $complete ? [] : $nextTagged;

        return [
            'status_next256' => self::status($complete, $baseVisible, $tokenMatches, $missing, $unexpected, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next256' => $baseVisible,
            'current_source_view_upsert_handoff_token_next256' => $handoffToken,
            'expected_current_source_view_upsert_handoff_token_next256' => $expectedToken,
            'current_source_view_upsert_handoff_token_matches_next256' => $tokenMatches,
            'current_source_view_upsert_handoff_batch_size_next256' => $batchSize,
            'current_source_view_upsert_handoff_batches_next256' => $batches,
            'required_current_source_view_upsert_handoff_receipts_next256' => $required,
            'acknowledged_current_source_view_upsert_handoff_receipts_next256' => $acknowledged,
            'missing_current_source_view_upsert_handoff_receipts_next256' => $missing,
            'unexpected_current_source_view_upsert_handoff_receipts_next256' => $unexpected,
            'require_current_source_view_upsert_handoff_order_next256' => $requireOrder,
            'current_source_view_upsert_handoff_order_matches_next256' => $orderMatches,
            'current_source_view_upsert_handoff_complete_next256' => $complete,
            'next_source_visible_after_current_source_view_upsert_handoff_next256' => $complete,
            'current_source_rows_next256' => $currentTagged,
            'attempted_next_source_rows_next256' => $nextTagged,
            'visible_returning_rows_next256' => $visibleRows,
            'held_next_source_rows_next256' => $heldRows,
            'visible_returning_payloads_next256' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next256' => array_column($heldRows, 'returning'),
            'visible_row_count_next256' => count($visibleRows),
            'held_next_row_count_next256' => count($heldRows),
            'blocked_reasons_next256' => $blockedReasons,
            'current_source_view_upsert_handoff_plan_next256' => [
                'base_next_source_visible' => $baseVisible,
                'token_matches' => $tokenMatches,
                'batch_size' => $batchSize,
                'batch_count' => count($batches),
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'handoff_complete' => $complete,
                'decision' => $complete
                    ? 'publish-next-source-after-current-recursive-view-upsert-handoff'
                    : 'hold-next-source-until-current-recursive-view-upsert-handoff',
            ],
            'yield_boundary_next256' => $complete
                ? 'recursive-view-upsert-next256-current-handoff-then-next'
                : 'recursive-view-upsert-next256-current-handoff-fence-next',
            'dependency_closure_next256' => 'no-new-support-component-reuses-native-recursive-view-upsert-materialization-and-adds-current-source-handoff-batch-receipts',
            'dependencies_next256' => array_values(array_unique(array_merge($base['dependencies_next253'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next256',
                'sqlite-instead-of-view-trigger-upsert-current-source-handoff',
                'wordpress-recursive-view-upsert-current-source-next256',
            ]))),
            'non_overlap_next256' => 'adds ordered batch-level current-source handoff receipts after accepted next253 recursive view UPSERT materialization; avoids next253 materialized projection receipts, next250 rowid provenance, next247 statement sequence, recursive view RETURNING-only, row-value/window RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite evidence clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function handoffBatches(array $rows, string $token, int $batchSize): array
    {
        $batches = [];
        foreach (array_chunk($rows, $batchSize) as $batchIndex => $batchRows) {
            $ordinals = array_map(static fn (array $row): int => (int) ($row['ordinal'] ?? -1), $batchRows);
            $projectionHashes = array_map(static fn (array $row): string => (string) ($row['projection_hash'] ?? ''), $batchRows);
            $rowidReceipts = array_map(static fn (array $row): ?string => isset($row['rowid_receipt_next250']) ? (string) $row['rowid_receipt_next250'] : null, $batchRows);
            foreach ($ordinals as $ordinal) {
                if ($ordinal < 0) {
                    throw new InvalidArgumentException('SQLite recursive view UPSERT next256 handoff ordinal is malformed');
                }
            }
            foreach ($projectionHashes as $hash) {
                if (preg_match('/^[a-f0-9]{48}$/', $hash) !== 1) {
                    throw new InvalidArgumentException('SQLite recursive view UPSERT next256 projection hash is malformed');
                }
            }
            $receipt = substr(hash('sha256', json_encode([$token, $batchIndex, $ordinals, $projectionHashes, $rowidReceipts], JSON_THROW_ON_ERROR)), 0, 52);
            $batches[] = [
                'batch_index' => $batchIndex,
                'first_ordinal' => min($ordinals),
                'last_ordinal' => max($ordinals),
                'row_count' => count($batchRows),
                'projection_hashes' => $projectionHashes,
                'rowid_receipts_next250' => $rowidReceipts,
                'handoff_receipt' => $receipt,
            ];
        }

        return $batches;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_view_upsert_handoff_next256'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_view_upsert_handoff_receipts_next256'] ?? [], 'acknowledged handoff receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{52}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} contain a malformed receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $batches
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, bool $visible, string $token, array $batches, array $reasons): array
    {
        $receiptByOrdinal = [];
        foreach ($batches as $batch) {
            for ($ordinal = (int) $batch['first_ordinal']; $ordinal <= (int) $batch['last_ordinal']; $ordinal++) {
                $receiptByOrdinal[$ordinal] = $batch['handoff_receipt'];
            }
        }

        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'current_source_view_upsert_handoff_token_next256' => $token,
                'current_source_view_upsert_handoff_receipt_next256' => $receiptByOrdinal[$index] ?? null,
                'visible_after_current_source_view_upsert_handoff_next256' => $visible,
                'held_by_current_source_view_upsert_handoff_reasons_next256' => $visible ? [] : $reasons,
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next256 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next253-view-materialization-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-view-upsert-handoff-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-view-upsert-handoff-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-view-upsert-handoff-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-view-upsert-handoff-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} is malformed");
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} must be positive");
        }

        return $value;
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $complete, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($complete) {
            return 'trigger-recursive-view-upsert-current-source-next256-handoff-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next256-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next256-handoff-token-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next256-handoff-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next256-handoff-unexpected-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next256-handoff-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next256-held';
    }
}
