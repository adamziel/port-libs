<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext244Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext241Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_close_next241'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next241'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next241'] ?? [], 'attempted next source rows');
        $statementId = self::token((string) ($options['current_source_upsert_statement_id_next244'] ?? 'wp.current.source.upsert.statement.244'), 'statement id');
        $expectedStatementId = self::token((string) ($options['expected_current_source_upsert_statement_id_next244'] ?? $statementId), 'expected statement id');
        $watermark = self::token((string) ($options['current_source_upsert_commit_watermark_next244'] ?? 'wp.current.source.upsert.commit.244'), 'commit watermark');
        $expectedWatermark = self::token((string) ($options['expected_current_source_upsert_commit_watermark_next244'] ?? $watermark), 'expected commit watermark');
        $viewCookie = self::token((string) ($options['current_upsert_commit_view_cookie_next244'] ?? ($base['current_upsert_close_view_cookie_next241'] ?? 'main@view-cookie-244-current')), 'view cookie');
        $expectedViewCookie = self::token((string) ($options['expected_current_upsert_commit_view_cookie_next244'] ?? $viewCookie), 'expected view cookie');
        $triggerCookie = self::token((string) ($options['current_upsert_commit_trigger_cookie_next244'] ?? ($base['current_upsert_close_trigger_cookie_next241'] ?? 'main@trigger-cookie-244-current')), 'trigger cookie');
        $expectedTriggerCookie = self::token((string) ($options['expected_current_upsert_commit_trigger_cookie_next244'] ?? $triggerCookie), 'expected trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_upsert_commit_order_next244'] ?? true);

        $required = self::commitReceipts($currentRows, $statementId, $watermark, $viewCookie, $triggerCookie);
        $acknowledged = self::acknowledgedReceipts($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $statementMatches = hash_equals($statementId, $expectedStatementId);
        $watermarkMatches = hash_equals($watermark, $expectedWatermark);
        $viewMatches = hash_equals($viewCookie, $expectedViewCookie);
        $triggerMatches = hash_equals($triggerCookie, $expectedTriggerCookie);
        $commitComplete = $required !== []
            && $statementMatches
            && $watermarkMatches
            && $viewMatches
            && $triggerMatches
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $nextVisible = $baseVisible && $commitComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next241'] ?? [],
            $baseVisible,
            $statementMatches,
            $watermarkMatches,
            $viewMatches,
            $triggerMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRows($currentRows, 'current-commit', true, $required, $statementId, $watermark, $viewCookie, $triggerCookie, []);
        $taggedNext = self::tagRows($nextRows, 'next-source', $nextVisible, [], $statementId, $watermark, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next244' => self::status($nextVisible, $baseVisible, $statementMatches, $watermarkMatches, $viewMatches, $triggerMatches, $missing, $unexpected, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next244' => $baseVisible,
            'current_source_upsert_statement_id_next244' => $statementId,
            'expected_current_source_upsert_statement_id_next244' => $expectedStatementId,
            'current_source_upsert_statement_id_matches_next244' => $statementMatches,
            'current_source_upsert_commit_watermark_next244' => $watermark,
            'expected_current_source_upsert_commit_watermark_next244' => $expectedWatermark,
            'current_source_upsert_commit_watermark_matches_next244' => $watermarkMatches,
            'current_upsert_commit_view_cookie_next244' => $viewCookie,
            'expected_current_upsert_commit_view_cookie_next244' => $expectedViewCookie,
            'current_upsert_commit_view_cookie_matches_next244' => $viewMatches,
            'current_upsert_commit_trigger_cookie_next244' => $triggerCookie,
            'expected_current_upsert_commit_trigger_cookie_next244' => $expectedTriggerCookie,
            'current_upsert_commit_trigger_cookie_matches_next244' => $triggerMatches,
            'required_current_source_upsert_commit_receipts_next244' => $required,
            'acknowledged_current_source_upsert_commit_receipts_next244' => $acknowledged,
            'missing_current_source_upsert_commit_receipts_next244' => $missing,
            'unexpected_current_source_upsert_commit_receipts_next244' => $unexpected,
            'require_current_source_upsert_commit_order_next244' => $requireOrder,
            'current_source_upsert_commit_order_matches_next244' => $orderMatches,
            'current_source_upsert_commit_complete_next244' => $commitComplete,
            'next_source_visible_after_current_source_upsert_commit_next244' => $nextVisible,
            'current_source_rows_next244' => $taggedCurrent,
            'attempted_next_source_rows_next244' => $taggedNext,
            'visible_returning_rows_next244' => $visibleRows,
            'held_next_source_rows_next244' => $heldRows,
            'visible_returning_payloads_next244' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next244' => array_column($heldRows, 'returning'),
            'current_source_row_count_next244' => count($taggedCurrent),
            'attempted_next_source_row_count_next244' => count($taggedNext),
            'visible_row_count_next244' => count($visibleRows),
            'held_next_row_count_next244' => count($heldRows),
            'blocked_reasons_next244' => $blockedReasons,
            'current_source_upsert_commit_plan_next244' => [
                'base_next_source_visible' => $baseVisible,
                'statement_id_matches' => $statementMatches,
                'commit_watermark_matches' => $watermarkMatches,
                'view_cookie_matches' => $viewMatches,
                'trigger_cookie_matches' => $triggerMatches,
                'required_commit_receipts' => $required,
                'acknowledged_commit_receipts' => $acknowledged,
                'missing_commit_receipts' => $missing,
                'unexpected_commit_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'commit_complete' => $commitComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-commit-watermark'
                    : 'hold-next-source-until-current-recursive-view-upsert-commit-watermark',
            ],
            'yield_boundary_next244' => $nextVisible
                ? 'recursive-view-upsert-next244-current-commit-then-next'
                : 'recursive-view-upsert-next244-current-commit-fence-next',
            'dependency_closure_next244' => 'no-new-support-component-reuses-native-recursive-view-upsert-close-seals-and-adds-statement-commit-watermarks',
            'dependencies_next244' => array_values(array_unique(array_merge($base['dependencies_next241'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next244',
                'sqlite-instead-of-view-trigger-upsert-statement-commit-watermark',
                'wordpress-recursive-view-upsert-current-source-next244',
            ]))),
            'non_overlap_next244' => 'adds statement-level UPSERT commit watermark admission after accepted next241 current-source close seals; avoids next241 close-seal duplication, recursive view RETURNING cursor/ticket/generation surfaces, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function commitReceipts(array $rows, string $statementId, string $watermark, string $viewCookie, string $triggerCookie): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                $statementId,
                $watermark,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_source_upsert_close_receipt_next241'] ?? ''),
                (string) $index,
                (string) ($returning['name'] ?? ''),
                (string) ($returning['event_name'] ?? $returning['event'] ?? ''),
                (string) ($returning['depth_value'] ?? ''),
            ])), 0, 56);
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
        if (($options['auto_ack_current_source_upsert_commits_next244'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_upsert_commit_receipts_next244'] ?? [], 'acknowledged current source upsert commit receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next244 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{56}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next244 {$label} contain a malformed commit receipt");
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
            throw new InvalidArgumentException("SQLite recursive view UPSERT next244 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next244 {$label} contain a malformed row");
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
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $statementId, string $watermark, string $viewCookie, string $triggerCookie, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_commit_phase_next244' => $phase,
                'current_source_upsert_statement_id_next244' => $statementId,
                'current_source_upsert_commit_watermark_next244' => $watermark,
                'current_upsert_commit_view_cookie_next244' => $viewCookie,
                'current_upsert_commit_trigger_cookie_next244' => $triggerCookie,
                'current_source_upsert_commit_receipt_next244' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_commit_next244' => $visible,
                'held_by_current_source_upsert_commit_reasons_next244' => $visible ? [] : $reasons,
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $statementMatches, bool $watermarkMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next244 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next241-current-source-upsert-close-not-published';
        }
        if (!$statementMatches) {
            $reasons[] = 'current-source-upsert-statement-id-mismatch';
        }
        if (!$watermarkMatches) {
            $reasons[] = 'current-source-upsert-commit-watermark-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-source-upsert-commit-view-cookie-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-source-upsert-commit-trigger-cookie-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-commit-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-commit-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-upsert-commit-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $nextVisible, bool $baseVisible, bool $statementMatches, bool $watermarkMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next244-commit-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next244-base-held';
        }
        if (!$statementMatches) {
            return 'trigger-recursive-view-upsert-current-source-next244-statement-held';
        }
        if (!$watermarkMatches) {
            return 'trigger-recursive-view-upsert-current-source-next244-watermark-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-upsert-current-source-next244-view-cookie-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next244-trigger-cookie-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next244-commit-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next244-commit-held';
    }

    private static function token(string $token, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{3,180}$/', $token)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next244 invalid {$label}");
        }

        return $token;
    }
}
