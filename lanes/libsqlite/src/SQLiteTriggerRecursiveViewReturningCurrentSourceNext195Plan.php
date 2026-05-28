<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext195Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int,current_source_generation?:string,expected_current_source_generation?:string,trigger_source_generation?:string,expected_trigger_source_generation?:string,returning_cursor_generation?:string,nested_epoch?:string,expected_nested_epoch?:string,drained_nested_depths?:list<int>,required_nested_depths?:list<int>,outer_publish_requested?:bool,current_watermark?:string,expected_current_watermark?:string,acknowledged_current_ordinals?:list<int>,auto_ack_current_ordinals?:bool,require_contiguous_ordinals?:bool,fingerprint_salt?:string,expected_fingerprint_salt?:string,acknowledged_current_fingerprints?:list<string>,auto_ack_current_fingerprints?:bool,require_fingerprint_order?:bool,current_source_token_next195?:string,expected_current_source_token_next195?:string,next_resume_token_next195?:string,expected_next_resume_token_next195?:string,acknowledged_current_source_receipts_next195?:list<string>,auto_ack_current_source_receipts_next195?:bool,require_receipt_order_next195?:bool} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $sourceToken = self::token((string) ($options['current_source_token_next195'] ?? 'wp.recursive.view.current.source.195'), 'current source token');
        $expectedSourceToken = self::token((string) ($options['expected_current_source_token_next195'] ?? $sourceToken), 'expected current source token');
        $resumeToken = self::token((string) ($options['next_resume_token_next195'] ?? 'wp.recursive.view.next.resume.195'), 'next resume token');
        $expectedResumeToken = self::token((string) ($options['expected_next_resume_token_next195'] ?? $resumeToken), 'expected next resume token');
        $requireOrder = (bool) ($options['require_receipt_order_next195'] ?? true);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext191Plan::execute(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + [
                'auto_ack_current_ordinals' => true,
                'auto_ack_current_fingerprints' => true,
            ],
        );

        $currentRows = self::rows($base['current_fingerprint_rows_next191'] ?? [], 'current rows');
        $nextRows = self::rows($base['attempted_next_fingerprint_rows_next191'] ?? [], 'attempted next rows');
        $required = self::sourceReceipts($currentRows, $sourceToken, $resumeToken);
        $acknowledged = self::acknowledgedReceipts($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $sourceMatches = hash_equals($sourceToken, $expectedSourceToken);
        $resumeMatches = hash_equals($resumeToken, $expectedResumeToken);
        $basePublishAllowed = (bool) ($base['next_source_publish_allowed_next191'] ?? false);
        $receiptFenceClear = $missing === [] && $unexpected === [] && $orderMatches;
        $resumeNext = $basePublishAllowed && $sourceMatches && $resumeMatches && $receiptFenceClear;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next191'] ?? [],
            $basePublishAllowed,
            $sourceMatches,
            $resumeMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $currentTagged = self::tagCurrentRows($currentRows, $required, $sourceToken, $resumeToken);
        $nextTagged = self::tagNextRows($nextRows, $resumeNext, $sourceToken, $resumeToken, $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentTagged, $nextTagged),
            static fn (array $row): bool => $row['visible_after_current_source_receipts_next195']
        ));
        $heldRows = array_values(array_filter(
            $nextTagged,
            static fn (array $row): bool => !$row['visible_after_current_source_receipts_next195']
        ));

        return $base + [
            'status_next195' => self::status($basePublishAllowed, $sourceMatches, $resumeMatches, $receiptFenceClear, $resumeNext),
            'current_source_token_next195' => $sourceToken,
            'expected_current_source_token_next195' => $expectedSourceToken,
            'current_source_token_matches_next195' => $sourceMatches,
            'next_resume_token_next195' => $resumeToken,
            'expected_next_resume_token_next195' => $expectedResumeToken,
            'next_resume_token_matches_next195' => $resumeMatches,
            'required_current_source_receipts_next195' => $required,
            'acknowledged_current_source_receipts_next195' => $acknowledged,
            'missing_current_source_receipts_next195' => $missing,
            'unexpected_current_source_receipts_next195' => $unexpected,
            'require_receipt_order_next195' => $requireOrder,
            'current_source_receipt_order_matches_next195' => $orderMatches,
            'current_source_receipt_fence_clear_next195' => $receiptFenceClear,
            'next_source_resume_allowed_next195' => $resumeNext,
            'current_source_receipt_rows_next195' => $currentTagged,
            'attempted_next_source_receipt_rows_next195' => $nextTagged,
            'visible_returning_rows_next195' => $visibleRows,
            'held_next_source_rows_next195' => $heldRows,
            'visible_returning_payloads_next195' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next195' => array_column($heldRows, 'returning'),
            'current_source_receipt_row_count_next195' => count($currentTagged),
            'attempted_next_source_receipt_row_count_next195' => count($nextTagged),
            'visible_row_count_next195' => count($visibleRows),
            'held_next_row_count_next195' => count($heldRows),
            'blocked_reasons_next195' => $blockedReasons,
            'current_source_receipt_plan_next195' => [
                'base_publish_allowed' => $basePublishAllowed,
                'source_token_matches' => $sourceMatches,
                'resume_token_matches' => $resumeMatches,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'receipt_fence_clear' => $receiptFenceClear,
                'next_source_resume_allowed' => $resumeNext,
                'decision' => $resumeNext ? 'resume-next-source-after-current-source-receipts' : 'hold-next-source-until-current-source-receipts',
            ],
            'yield_boundary_next195' => $resumeNext
                ? 'recursive-view-returning-next195-current-source-receipts-then-next'
                : 'recursive-view-returning-next195-current-source-receipts-fence-next',
            'dependencies_next195' => [
                'sqlite-trigger-recursive-view-returning-current-source-next195',
                'sqlite-returning-current-source-drain-receipts',
                'sqlite-view-trigger-next-source-resume-token',
                'wordpress-recursive-view-returning-current-source-next195',
            ],
            'dependency_closure_next195' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-fingerprint-fence-and-adds-drain-receipt-resume-model',
            'non_overlap_next195' => 'adds current-source drain receipts before next-source resume after next191 fingerprint admission; avoids accepted next191 fingerprint fencing, next188 ordinal watermarks, savepoint rollback, row-value RETURNING, schema reparse, WAL/VFS, and trigger/FK cascade clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sourceReceipts(array $rows, string $sourceToken, string $resumeToken): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $sourceToken,
                $resumeToken,
                (string) ($row['current_row_fingerprint_next191'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning_option_name'] ?? ''),
                (string) ($row['source_signature_next188'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 28);
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
        if (($options['auto_ack_current_source_receipts_next195'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_receipts_next195'] ?? [], 'acknowledged current source receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next195 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{28}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next195 {$label} contain a malformed receipt");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next195 {$label} are malformed");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next195 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentRows(array $rows, array $receipts, string $sourceToken, string $resumeToken): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'receipt_phase_next195' => 'current',
                'current_source_token_next195' => $sourceToken,
                'next_resume_token_next195' => $resumeToken,
                'current_source_receipt_next195' => $receipts[$index] ?? null,
                'visible_after_current_source_receipts_next195' => true,
                'held_by_current_source_receipt_reasons_next195' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRows(array $rows, bool $visible, string $sourceToken, string $resumeToken, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'receipt_phase_next195' => 'next',
                'current_source_token_next195' => $sourceToken,
                'next_resume_token_next195' => $resumeToken,
                'current_source_receipt_next195' => null,
                'visible_after_current_source_receipts_next195' => $visible,
                'held_by_current_source_receipt_reasons_next195' => $visible ? [] : $reasons,
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
        bool $sourceMatches,
        bool $resumeMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next195 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$basePublishAllowed && $reasons === []) {
            $reasons[] = 'base-next191-current-source-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-source-token-mismatch';
        }
        if (!$resumeMatches) {
            $reasons[] = 'next-resume-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-receipt-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-receipt-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-receipt-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $basePublishAllowed, bool $sourceMatches, bool $resumeMatches, bool $receiptFenceClear, bool $resumeNext): string
    {
        if ($resumeNext) {
            return 'trigger-recursive-view-returning-current-source-receipts-released-next195';
        }
        if (!$basePublishAllowed) {
            return 'trigger-recursive-view-returning-current-source-receipts-base-held-next195';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-returning-current-source-receipts-source-held-next195';
        }
        if (!$resumeMatches) {
            return 'trigger-recursive-view-returning-current-source-receipts-resume-held-next195';
        }
        if (!$receiptFenceClear) {
            return 'trigger-recursive-view-returning-current-source-receipts-held-next195';
        }

        return 'trigger-recursive-view-returning-current-source-receipts-pending-next195';
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next195 {$label} is malformed");
        }

        return $value;
    }
}
