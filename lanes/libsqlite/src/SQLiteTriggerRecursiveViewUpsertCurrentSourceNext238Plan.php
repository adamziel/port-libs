<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext238Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentViewRows
     * @param list<array<string,mixed>> $nextViewRows
     * @param array{name:string,source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentViewRows,
        array $nextViewRows,
        array $view,
        array $uniqueColumns,
        array $triggers,
        array $options = [],
    ): array {
        $baseOptions = $options;
        $baseOptions['auto_ack_current_yield_tickets_next235'] = true;
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext235Plan::execute(
            $rows,
            $currentViewRows,
            $nextViewRows,
            $view,
            $uniqueColumns,
            $triggers,
            $baseOptions,
        );

        $resumeSource = self::token((string) ($options['current_resume_source_next238'] ?? 'wp.current.resume.source.238'), 'current resume source');
        $expectedResumeSource = self::token((string) ($options['expected_current_resume_source_next238'] ?? $resumeSource), 'expected current resume source');
        $resumeCursor = self::token((string) ($options['current_resume_cursor_next238'] ?? 'wp.current.resume.cursor.238'), 'current resume cursor');
        $expectedResumeCursor = self::token((string) ($options['expected_current_resume_cursor_next238'] ?? $resumeCursor), 'expected current resume cursor');
        $resumeEpoch = self::token((string) ($options['current_resume_epoch_next238'] ?? 'wp.current.resume.epoch.238'), 'current resume epoch');
        $expectedResumeEpoch = self::token((string) ($options['expected_current_resume_epoch_next238'] ?? $resumeEpoch), 'expected current resume epoch');
        $requireOrder = (bool) ($options['require_current_resume_receipt_order_next238'] ?? true);

        $currentRows = self::rows($base['current_yield_stream_next235'] ?? [], 'current yield stream');
        $attemptedNextRows = self::rows($base['attempted_next_yield_stream_next235'] ?? [], 'attempted next yield stream');
        $requiredReceipts = self::receipts($currentRows, $resumeSource, $resumeCursor, $resumeEpoch);
        $acknowledgedReceipts = self::acknowledgedReceipts($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $sourceMatches = hash_equals($resumeSource, $expectedResumeSource);
        $cursorMatches = hash_equals($resumeCursor, $expectedResumeCursor);
        $epochMatches = hash_equals($resumeEpoch, $expectedResumeEpoch);
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $baseReleased = (bool) ($base['next_source_visible_after_current_yield_tickets_next235'] ?? false);
        $resumeComplete = $requiredReceipts !== []
            && $baseReleased
            && $sourceMatches
            && $cursorMatches
            && $epochMatches
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;

        $blockedReasons = self::blockedReasons($baseReleased, $sourceMatches, $cursorMatches, $epochMatches, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches);
        $currentTagged = self::tagRows($currentRows, 'current', true, $requiredReceipts, $resumeSource, $resumeCursor, $resumeEpoch, []);
        $nextTagged = self::tagRows($attemptedNextRows, 'next', $resumeComplete, [], $resumeSource, $resumeCursor, $resumeEpoch, $resumeComplete ? [] : $blockedReasons);
        $currentReturning = self::tagRows(self::rows($base['current_returning_rows_next235'] ?? [], 'current returning rows'), 'current', true, $requiredReceipts, $resumeSource, $resumeCursor, $resumeEpoch, []);
        $nextReturning = self::tagRows(self::rows($base['attempted_next_returning_rows_next235'] ?? [], 'attempted next returning rows'), 'next', $resumeComplete, [], $resumeSource, $resumeCursor, $resumeEpoch, $resumeComplete ? [] : $blockedReasons);

        return [
            'status_next238' => self::status($resumeComplete, $baseReleased, $sourceMatches, $cursorMatches, $epochMatches, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base_next238' => $base,
            'current_resume_source_next238' => $resumeSource,
            'expected_current_resume_source_next238' => $expectedResumeSource,
            'current_resume_source_matches_next238' => $sourceMatches,
            'current_resume_cursor_next238' => $resumeCursor,
            'expected_current_resume_cursor_next238' => $expectedResumeCursor,
            'current_resume_cursor_matches_next238' => $cursorMatches,
            'current_resume_epoch_next238' => $resumeEpoch,
            'expected_current_resume_epoch_next238' => $expectedResumeEpoch,
            'current_resume_epoch_matches_next238' => $epochMatches,
            'required_current_resume_receipts_next238' => $requiredReceipts,
            'acknowledged_current_resume_receipts_next238' => $acknowledgedReceipts,
            'missing_current_resume_receipts_next238' => $missingReceipts,
            'unexpected_current_resume_receipts_next238' => $unexpectedReceipts,
            'require_current_resume_receipt_order_next238' => $requireOrder,
            'current_resume_receipt_order_matches_next238' => $orderMatches,
            'current_resume_receipt_complete_next238' => $resumeComplete,
            'base_yield_ticket_released_next238' => $baseReleased,
            'next_source_visible_after_current_resume_receipts_next238' => $resumeComplete,
            'current_resume_stream_next238' => $currentTagged,
            'attempted_next_resume_stream_next238' => $nextTagged,
            'visible_resume_stream_next238' => $resumeComplete ? array_merge($currentTagged, $nextTagged) : $currentTagged,
            'held_next_resume_stream_next238' => $resumeComplete ? [] : $nextTagged,
            'current_returning_rows_next238' => $currentReturning,
            'attempted_next_returning_rows_next238' => $nextReturning,
            'visible_returning_rows_next238' => $resumeComplete ? array_merge($currentReturning, $nextReturning) : $currentReturning,
            'held_next_returning_rows_next238' => $resumeComplete ? [] : $nextReturning,
            'visible_change_count_next238' => $resumeComplete ? (int) ($base['visible_change_count_next235'] ?? 0) : (int) ($base['base_next235']['current_change_count_next232'] ?? 0),
            'after_savepoint_next238' => $resumeComplete ? ($base['after_savepoint_next235'] ?? []) : ($base['base_next235']['base']['after_savepoint'] ?? []),
            'blocked_reasons_next238' => $blockedReasons,
            'current_resume_receipt_plan_next238' => [
                'base_yield_ticket_released' => $baseReleased,
                'resume_source_matches' => $sourceMatches,
                'resume_cursor_matches' => $cursorMatches,
                'resume_epoch_matches' => $epochMatches,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'resume_complete' => $resumeComplete,
                'decision' => $resumeComplete
                    ? 'publish-next-source-after-current-recursive-view-upsert-resume'
                    : 'hold-next-source-until-current-recursive-view-upsert-resume',
            ],
            'yield_boundary_next238' => $resumeComplete
                ? 'recursive-view-upsert-next238-current-resume-receipts-then-next'
                : 'recursive-view-upsert-next238-current-resume-receipts-fence-next',
            'dependency_closure_next238' => 'no-new-support-component-reuses-native-recursive-view-upsert-yield-tickets-and-adds-current-resume-receipts',
            'dependencies_next238' => [
                'sqlite-trigger-recursive-view-upsert-current-source-next238',
                'sqlite-current-recursive-view-upsert-resume-receipt',
                'sqlite-current-recursive-view-upsert-yield-ticket',
                'wordpress-recursive-view-upsert-resume-receipt-next238',
            ],
            'non_overlap_next238' => 'adds current-source resume receipt fencing after next235 yield tickets; avoids accepted next232 conflict seals, next235 yield tickets, recursive view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function receipts(array $rows, string $source, string $cursor, string $epoch): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = is_array($row['returning'] ?? null) ? $row['returning'] : $row;
            $nextRow = is_array($row['next_row'] ?? null) ? $row['next_row'] : [];
            $currentRow = is_array($row['current_row'] ?? null) ? $row['current_row'] : [];
            $parts = [
                $source,
                $cursor,
                $epoch,
                (string) ($row['current_yield_ticket_next235'] ?? ''),
                (string) ($row['current_view_source_next232'] ?? ''),
                (string) ($row['current_trigger_program_next232'] ?? ''),
                (string) ($row['phase'] ?? ''),
                (string) ($row['event'] ?? $returning['event'] ?? ''),
                (string) ($row['depth'] ?? $returning['depth'] ?? ''),
                (string) ($row['ordinal'] ?? $returning['ordinal'] ?? $index),
                (string) ($row['trigger'] ?? $returning['trigger'] ?? ''),
                (string) ($nextRow['option_name'] ?? $returning['option_name'] ?? ''),
                (string) ($currentRow['option_value'] ?? $returning['old_value'] ?? ''),
                (string) ($nextRow['option_value'] ?? $returning['option_value'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 52);
        }

        return $receipts;
    }

    /** @param array<string,mixed> $options @param list<string> $required @return list<string> */
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_resume_receipts_next238'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_resume_receipts_next238'] ?? [], 'acknowledged current resume receipts');
    }

    /** @param mixed $values @return list<string> */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next238 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{52}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next238 {$label} contain a malformed resume receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /** @param mixed $rows @return list<array<string,mixed>> */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next238 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next238 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $receipts @param list<string> $reasons @return list<array<string,mixed>> */
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $source, string $cursor, string $epoch, array $reasons): array
    {
        $tagged = [];
        foreach ($rows as $index => $row) {
            $tagged[] = $row + [
                'resume_receipt_phase_next238' => $phase,
                'current_resume_source_next238' => $source,
                'current_resume_cursor_next238' => $cursor,
                'current_resume_epoch_next238' => $epoch,
                'current_resume_receipt_next238' => $receipts[$index] ?? null,
                'visible_after_current_resume_receipt_next238' => $visible,
                'held_by_current_resume_receipt_reasons_next238' => $visible ? [] : $reasons,
            ];
        }

        return $tagged;
    }

    /** @param list<string> $missing @param list<string> $unexpected @return list<string> */
    private static function blockedReasons(bool $baseReleased, bool $sourceMatches, bool $cursorMatches, bool $epochMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        $reasons = [];
        if (!$baseReleased) {
            $reasons[] = 'base-current-yield-ticket-not-released';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-resume-source-mismatch';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-resume-cursor-mismatch';
        }
        if (!$epochMatches) {
            $reasons[] = 'current-resume-epoch-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-resume-receipt-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-resume-receipt-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-resume-receipt-order-mismatch';
        }

        return $reasons;
    }

    /** @param list<string> $missing @param list<string> $unexpected */
    private static function status(bool $complete, bool $baseReleased, bool $sourceMatches, bool $cursorMatches, bool $epochMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($complete) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-released';
        }
        if (!$baseReleased) {
            return 'trigger-recursive-view-upsert-current-source-next238-base-yield-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-source-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-cursor-held';
        }
        if (!$epochMatches) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-epoch-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-receipt-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next238-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next238 {$label} is malformed");
        }

        return $token;
    }
}
