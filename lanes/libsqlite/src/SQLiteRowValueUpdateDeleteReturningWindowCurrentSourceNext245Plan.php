<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next245',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $yieldTickets = self::yieldTickets($base['yield_current_row_frames_next236'], 'yield', $rowIdColumn);
        $suppressedTickets = self::yieldTickets($base['suppressed_current_row_frames_next236'], 'suppressed-attempt', $rowIdColumn);
        $retryTickets = self::yieldTickets($base['retry_current_row_frames_next236'], 'retry-release', $rowIdColumn);
        $requiredTickets = array_column($yieldTickets, 'ticket');
        $ack = $acknowledgedYieldTickets ?? $requiredTickets;
        $gate = self::gate($requiredTickets, $ack);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next245',
            'yield_current_source_gate_next245' => $gate,
            'yield_phase_tickets_next245' => $yieldTickets,
            'suppressed_phase_tickets_next245' => $suppressedTickets,
            'retry_phase_tickets_next245' => $retryTickets,
            'required_yield_tickets_next245' => $requiredTickets,
            'acknowledged_yield_tickets_next245' => $ack,
            'next_source_exposed_next245' => $gate['next_source_exposed'],
            'current_source_before_next245' => $gate['current_source_complete'],
            'yield_retry_order_next245' => array_merge(
                array_column($yieldTickets, 'ticket'),
                array_column($retryTickets, 'ticket'),
            ),
            'yield_window_receipt_next245' => self::receipt($base, $yieldTickets, $retryTickets, $gate, $rowIdColumn),
            'dependency_closure_next245' => 'no new support component needed; next245 reuses native PHP row-value UPDATE/DELETE RETURNING, savepoint image retry, and next236 current-row window receipts while adding a current-source yield-ticket gate before next-source exposure',
            'dependencies_next245' => [
                'sqlite-rowvalue-returning-window-yield-current-source-next245',
                'sqlite-returning-current-source-ticket-gate-next245',
                'wordpress-rowvalue-returning-window-yield-gate-next245',
            ],
            'non_overlap_next245' => 'adds yield-ticket admission that exposes retried next-source rows only after all current-source row-value RETURNING window rows are acknowledged; avoids accepted next236 current-row frame receipts, next242 row-value/window behavior, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, and upstream-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return list<array<string,mixed>>
     */
    private static function yieldTickets(array $frames, string $phase, string $rowIdColumn): array
    {
        $tickets = [];
        foreach (array_values($frames) as $index => $frame) {
            if (!array_key_exists($rowIdColumn, $frame)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next245 rowid column {$rowIdColumn} is missing");
            }
            $rowId = $frame[$rowIdColumn];
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next245 rowid column {$rowIdColumn} must be int or string");
            }
            $tokenParts = [
                $phase,
                (string) ($index + 1),
                (string) $rowId,
                (string) ($frame['option_name'] ?? ''),
                (string) ($frame['frame_token'] ?? ''),
            ];
            $tickets[] = [
                'phase' => $phase,
                'ordinal' => $index + 1,
                $rowIdColumn => $rowId,
                'option_name' => (string) ($frame['option_name'] ?? ''),
                'status' => $frame['status'] ?? null,
                'frame_token' => (string) ($frame['frame_token'] ?? ''),
                'running_bytes' => self::intValue($frame['running_bytes'] ?? null),
                'following_bytes' => self::intValue($frame['following_bytes'] ?? null),
                'ticket' => implode(':', $tokenParts),
            ];
        }

        return $tickets;
    }

    /**
     * @param list<string> $required
     * @param list<string> $acknowledged
     * @return array<string,mixed>
     */
    private static function gate(array $required, array $acknowledged): array
    {
        $requiredSet = array_fill_keys($required, true);
        $ackSet = array_fill_keys($acknowledged, true);
        $missing = [];
        foreach ($required as $ticket) {
            if (!isset($ackSet[$ticket])) {
                $missing[] = $ticket;
            }
        }
        $unexpected = [];
        foreach ($acknowledged as $ticket) {
            if (!isset($requiredSet[$ticket])) {
                $unexpected[] = $ticket;
            }
        }

        return [
            'required_count' => count($required),
            'acknowledged_count' => count($acknowledged),
            'missing_tickets' => $missing,
            'unexpected_tickets' => $unexpected,
            'current_source_complete' => $missing === [] && $unexpected === [],
            'next_source_exposed' => $missing === [] && $unexpected === [],
            'yield_boundary' => 'current-source-yield-before-next-source-next245',
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $yieldTickets
     * @param list<array<string,mixed>> $retryTickets
     * @param array<string,mixed> $gate
     * @return array<string,mixed>
     */
    private static function receipt(array $base, array $yieldTickets, array $retryTickets, array $gate, string $rowIdColumn): array
    {
        return [
            'savepoint' => $base['savepoint'],
            'yield_ids' => array_column($yieldTickets, $rowIdColumn),
            'retry_ids' => array_column($retryTickets, $rowIdColumn),
            'yield_tickets' => array_column($yieldTickets, 'ticket'),
            'retry_tickets' => array_column($retryTickets, 'ticket'),
            'gate_status' => $gate['next_source_exposed'] ? 'next-source-exposed-after-current-yield' : 'held-for-current-source-yield',
            'suppressed_attempt_ids' => $base['current_source_receipt_next236']['rolled_back_attempt_ids'],
            'current_source_row_count' => $base['current_source_receipt_next236']['released_table_count'],
            'retry_running_final' => $base['current_source_receipt_next236']['retry_running_final'],
        ];
    }

    private static function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
