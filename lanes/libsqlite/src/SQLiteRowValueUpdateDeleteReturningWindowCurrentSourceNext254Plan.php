<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext254Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<array<string,mixed>>|null $rowReceipts
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next254',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        string $currentSourceEpoch = 'wp-current-source-254',
        string $nextSourceEpoch = 'wp-next-source-254',
        ?string $expectedCurrentDigest = null,
        ?string $expectedNextDigest = null,
        ?array $rowReceipts = null,
        bool $requireNextReceipts = true,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext251Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
            $currentSourceEpoch,
            $nextSourceEpoch,
            $expectedCurrentDigest,
            $expectedNextDigest,
        );

        $handoffRows = $base['source_handoff_rows_next251'];
        $expectedReceipts = self::expectedReceipts($handoffRows, $rowIdColumn);
        $receipts = $rowReceipts ?? $expectedReceipts;
        $receiptIndex = self::receiptIndex($receipts);
        $admissionRows = self::admissionRows($handoffRows, $receiptIndex, $rowIdColumn, $nextSourceEpoch, $requireNextReceipts);
        $blocked = self::blockedReasons($base, $admissionRows, $requireNextReceipts);
        $readyRows = array_values(array_filter($admissionRows, static fn (array $row): bool => (bool) $row['admitted_next254']));
        $nextRows = array_values(array_filter($readyRows, static fn (array $row): bool => ($row['source_epoch_next251'] ?? null) === $nextSourceEpoch));
        $currentRows = array_values(array_filter($readyRows, static fn (array $row): bool => ($row['source_epoch_next251'] ?? null) !== $nextSourceEpoch));
        $resume = self::resume($readyRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next254',
            'admission_barrier_next254' => [
                'savepoint' => $savepoint,
                'rowid_column' => $rowIdColumn,
                'current_source_epoch' => $currentSourceEpoch,
                'next_source_epoch' => $nextSourceEpoch,
                'require_next_receipts' => $requireNextReceipts,
                'source_handoff_ready' => (bool) ($base['source_handoff_barrier_next251']['next_source_ready'] ?? false),
                'expected_receipt_count' => count($expectedReceipts),
                'provided_receipt_count' => count($receipts),
                'admitted_row_count' => count($readyRows),
                'admitted_current_row_count' => count($currentRows),
                'admitted_next_row_count' => count($nextRows),
                'blocked_reasons' => $blocked,
                'admission_token' => self::admissionToken($base, $admissionRows, $blocked),
            ],
            'expected_row_receipts_next254' => $expectedReceipts,
            'provided_row_receipts_next254' => array_values($receipts),
            'admission_rows_next254' => $admissionRows,
            'admitted_rows_next254' => $readyRows,
            'admitted_tickets_next254' => array_column($readyRows, 'ticket'),
            'admitted_next_rows_next254' => $nextRows,
            'admitted_next_tickets_next254' => array_column($nextRows, 'ticket'),
            'admitted_current_rows_next254' => $currentRows,
            'admission_resume_next254' => $resume,
            'admission_resume_tickets_next254' => array_column($resume['rows'], 'ticket'),
            'admission_state_next254' => $blocked === []
                ? 'current-source-next254-window-receipts-admitted'
                : 'current-source-next254-window-receipts-held',
            'dependency_closure_next254' => 'no new support component needed; next254 reuses row-value UPDATE/DELETE RETURNING window publication, source epoch/digest handoff rows, and adds per-row window receipt admission for copied WordPress option imports',
            'dependencies_next254' => [
                'sqlite-rowvalue-update-delete-returning-window-current-source-next254',
                'sqlite-returning-window-row-receipt-admission-next254',
                'wordpress-rowvalue-returning-window-current-source-next254',
            ],
            'non_overlap_next254' => 'adds row-level window receipt admission after accepted next251 source epoch/digest handoff; avoids next251 digest fencing, next248 publication cursors, next245 yield tickets, savepoint-only row-value RETURNING, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function expectedReceipts(array $rows, string $rowIdColumn): array
    {
        $receipts = [];
        foreach ($rows as $row) {
            $ticket = self::stringValue($row['ticket'] ?? null, 'ticket');
            $epoch = self::stringValue($row['source_epoch_next251'] ?? null, 'source epoch');
            $frameToken = self::stringValue($row['frame_token'] ?? null, 'frame token');
            $rowId = $row[$rowIdColumn] ?? null;
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next254 rowid column {$rowIdColumn} must be int or string");
            }
            $runningBytes = self::intValue($row['running_bytes'] ?? null, 'running bytes');
            $followingBytes = self::intValue($row['following_bytes'] ?? null, 'following bytes');
            $receiptToken = self::receiptToken($ticket, $epoch, $frameToken, $rowId, $runningBytes, $followingBytes);
            $receipts[] = [
                'ticket' => $ticket,
                'source_epoch' => $epoch,
                $rowIdColumn => $rowId,
                'frame_token' => $frameToken,
                'running_bytes' => $runningBytes,
                'following_bytes' => $followingBytes,
                'receipt_token' => $receiptToken,
            ];
        }

        return $receipts;
    }

    /**
     * @param list<array<string,mixed>> $receipts
     * @return array<string,array<string,mixed>>
     */
    private static function receiptIndex(array $receipts): array
    {
        $indexed = [];
        foreach ($receipts as $receipt) {
            $ticket = self::stringValue($receipt['ticket'] ?? null, 'receipt ticket');
            $indexed[$ticket] = $receipt;
        }

        return $indexed;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array<string,mixed>> $receiptIndex
     * @return list<array<string,mixed>>
     */
    private static function admissionRows(array $rows, array $receiptIndex, string $rowIdColumn, string $nextSourceEpoch, bool $requireNextReceipts): array
    {
        $admitted = [];
        foreach ($rows as $index => $row) {
            $ticket = self::stringValue($row['ticket'] ?? null, 'ticket');
            $epoch = self::stringValue($row['source_epoch_next251'] ?? null, 'source epoch');
            $rowId = $row[$rowIdColumn] ?? null;
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next254 rowid column {$rowIdColumn} must be int or string");
            }
            $frameToken = self::stringValue($row['frame_token'] ?? null, 'frame token');
            $runningBytes = self::intValue($row['running_bytes'] ?? null, 'running bytes');
            $followingBytes = self::intValue($row['following_bytes'] ?? null, 'following bytes');
            $expected = self::receiptToken($ticket, $epoch, $frameToken, $rowId, $runningBytes, $followingBytes);
            $receipt = $receiptIndex[$ticket] ?? null;
            $reasons = self::rowReasons($receipt, $expected, $epoch, $frameToken, $rowIdColumn, $rowId, $runningBytes, $followingBytes);
            if (!$requireNextReceipts && $epoch === $nextSourceEpoch && in_array('missing-row-receipt-next254', $reasons, true)) {
                $reasons = [];
            }
            $row['expected_receipt_token_next254'] = $expected;
            $row['provided_receipt_token_next254'] = is_array($receipt) ? ($receipt['receipt_token'] ?? null) : null;
            $row['admission_reasons_next254'] = $reasons;
            $row['admitted_next254'] = $reasons === [];
            $row['admission_ordinal_next254'] = $row['admitted_next254'] ? count(array_filter($admitted, static fn (array $item): bool => (bool) $item['admitted_next254'])) + 1 : null;
            $row['admission_token_next254'] = hash('sha256', $expected . '|' . $index . '|' . json_encode($reasons, JSON_THROW_ON_ERROR));
            $admitted[] = $row;
        }

        return $admitted;
    }

    /**
     * @param array<string,mixed>|null $receipt
     * @return list<string>
     */
    private static function rowReasons(?array $receipt, string $expected, string $epoch, string $frameToken, string $rowIdColumn, int|string $rowId, int $runningBytes, int $followingBytes): array
    {
        if ($receipt === null) {
            return ['missing-row-receipt-next254'];
        }

        $reasons = [];
        if (($receipt['receipt_token'] ?? null) !== $expected || !self::receiptSelfTokenMatches($receipt, $rowIdColumn)) {
            $reasons[] = 'row-receipt-token-mismatch-next254';
        }
        if (($receipt['source_epoch'] ?? null) !== $epoch) {
            $reasons[] = 'row-receipt-source-epoch-mismatch-next254';
        }
        if (($receipt['frame_token'] ?? null) !== $frameToken) {
            $reasons[] = 'row-receipt-window-frame-mismatch-next254';
        }
        if (($receipt[$rowIdColumn] ?? null) !== $rowId) {
            $reasons[] = 'row-receipt-rowid-mismatch-next254';
        }
        if (($receipt['running_bytes'] ?? null) !== $runningBytes) {
            $reasons[] = 'row-receipt-running-bytes-mismatch-next254';
        }
        if (($receipt['following_bytes'] ?? null) !== $followingBytes) {
            $reasons[] = 'row-receipt-following-bytes-mismatch-next254';
        }

        return $reasons;
    }

    /**
     * @param array<string,mixed> $receipt
     */
    private static function receiptSelfTokenMatches(array $receipt, string $rowIdColumn): bool
    {
        $ticket = $receipt['ticket'] ?? null;
        $epoch = $receipt['source_epoch'] ?? null;
        $frameToken = $receipt['frame_token'] ?? null;
        $rowId = $receipt[$rowIdColumn] ?? null;
        $runningBytes = $receipt['running_bytes'] ?? null;
        $followingBytes = $receipt['following_bytes'] ?? null;
        if (!is_string($ticket) || $ticket === '' || !is_string($epoch) || $epoch === '' || !is_string($frameToken) || $frameToken === '') {
            return false;
        }
        if (!is_int($rowId) && !is_string($rowId)) {
            return false;
        }
        if (!is_int($runningBytes) || !is_int($followingBytes)) {
            return false;
        }

        return ($receipt['receipt_token'] ?? null) === self::receiptToken($ticket, $epoch, $frameToken, $rowId, $runningBytes, $followingBytes);
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function blockedReasons(array $base, array $rows, bool $requireNextReceipts): array
    {
        $reasons = [];
        if (!(bool) ($base['source_handoff_barrier_next251']['next_source_ready'] ?? false)) {
            $reasons[] = 'source-handoff-not-ready-next254';
        }
        foreach ($rows as $row) {
            foreach ($row['admission_reasons_next254'] as $reason) {
                $reasons[] = $reason;
            }
        }
        if (!$requireNextReceipts) {
            $reasons[] = 'next-source-receipts-not-required-next254';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function resume(array $rows, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return [
                'resume_after_ticket' => null,
                'resume_offset' => 0,
                'rows' => $rows,
                'remaining_count' => count($rows),
                'exhausted' => $rows === [],
            ];
        }

        $offset = null;
        foreach ($rows as $index => $row) {
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $offset = $index + 1;
                break;
            }
        }
        if ($offset === null) {
            throw new \InvalidArgumentException('SQLite row-value returning window next254 resume ticket is not admitted');
        }

        $remaining = array_slice($rows, $offset);

        return [
            'resume_after_ticket' => $resumeAfterTicket,
            'resume_offset' => $offset,
            'rows' => $remaining,
            'remaining_count' => count($remaining),
            'exhausted' => $remaining === [],
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blocked
     */
    private static function admissionToken(array $base, array $rows, array $blocked): string
    {
        return hash('sha256', json_encode([
            'handoff' => $base['source_handoff_barrier_next251']['handoff_token'] ?? '',
            'rows' => array_column($rows, 'admission_token_next254'),
            'blocked' => $blocked,
        ], JSON_THROW_ON_ERROR));
    }

    private static function receiptToken(string $ticket, string $epoch, string $frameToken, int|string $rowId, int $runningBytes, int $followingBytes): string
    {
        return hash('sha256', $ticket . '|' . $epoch . '|' . $frameToken . '|' . (string) $rowId . '|' . $runningBytes . '|' . $followingBytes);
    }

    private static function stringValue(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next254 {$label} is missing");
        }

        return $value;
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException("SQLite row-value returning window next254 {$label} must be an integer");
        }

        return $value;
    }
}
