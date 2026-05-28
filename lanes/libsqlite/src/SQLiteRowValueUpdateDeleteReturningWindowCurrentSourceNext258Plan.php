<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext258Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next258',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        ?string $acknowledgedTransitionToken = null,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext252Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
        );

        $transition = self::transition($base);
        $admitted = $transition['transition_complete_next258'];
        $rows = self::admittedRows($base['current_source_publication_windows_next252'], $admitted);
        $resumeRows = self::resumeRows($rows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next258',
            'current_source_transition_next258' => $transition,
            'required_transition_token_next258' => $transition['required_transition_token_next258'],
            'acknowledged_transition_token_next258' => $acknowledgedTransitionToken,
            'transition_acknowledged_next258' => $acknowledgedTransitionToken !== null
                && hash_equals($transition['required_transition_token_next258'], $acknowledgedTransitionToken),
            'next_source_admitted_next258' => $admitted
                && $acknowledgedTransitionToken !== null
                && hash_equals($transition['required_transition_token_next258'], $acknowledgedTransitionToken),
            'publication_rows_next258' => self::admitNextRows(
                $rows,
                $acknowledgedTransitionToken !== null
                    && hash_equals($transition['required_transition_token_next258'], $acknowledgedTransitionToken),
            ),
            'publication_row_count_next258' => count(self::admitNextRows(
                $rows,
                $acknowledgedTransitionToken !== null
                    && hash_equals($transition['required_transition_token_next258'], $acknowledgedTransitionToken),
            )),
            'resume_rows_next258' => $resumeRows,
            'resume_tickets_next258' => array_column($resumeRows, 'ticket'),
            'blocked_reasons_next258' => self::blockedReasons($base, $transition, $acknowledgedTransitionToken),
            'dependency_closure_next258' => 'no new support component needed; next258 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next248 publication cursors, and next252 current-source window high-water rows while adding a transition-token fence for admitting next-source retry rows',
            'dependencies_next258' => [
                'sqlite-rowvalue-returning-current-source-transition-token-next258',
                'sqlite-rowvalue-returning-next-source-admission-after-window-high-water-next258',
                'wordpress-rowvalue-returning-window-transition-current-source-next258',
            ],
            'non_overlap_next258' => 'adds a transition-token acknowledgement after the accepted next252 row-number/high-water fence so next-source retry rows remain quarantined until the current high-water and first retry window boundary is acknowledged; avoids accepted next252 row-number fences, next248 publication cursor barriers, next245 yield tickets, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function transition(array $base): array
    {
        $fence = $base['publication_window_fence_next252'];
        $currentHighWater = self::stringOrNull($base['current_source_high_water_ticket_next252'] ?? null);
        $firstRetry = self::stringOrNull($base['next_source_first_ticket_next252'] ?? null);
        $complete = (bool) ($fence['current_source_complete'] ?? false);
        $retryAfterHighWater = (bool) ($fence['retry_after_current_high_water'] ?? false);
        $windowDigest = self::stringValue($fence['window_digest'] ?? null, 'window_digest');
        $required = hash('sha256', json_encode([
            'savepoint' => $base['savepoint'] ?? null,
            'currentHighWater' => $currentHighWater,
            'firstRetry' => $firstRetry,
            'currentOrdinal' => $fence['current_high_water_ordinal'] ?? null,
            'firstRetryOrdinal' => $fence['first_retry_ordinal'] ?? null,
            'windowDigest' => $windowDigest,
        ], JSON_THROW_ON_ERROR));

        return [
            'current_source_complete_next258' => $complete,
            'next_source_available_next258' => $firstRetry !== null,
            'retry_after_current_high_water_next258' => $retryAfterHighWater,
            'current_high_water_ticket_next258' => $currentHighWater,
            'first_retry_ticket_next258' => $firstRetry,
            'current_high_water_ordinal_next258' => $fence['current_high_water_ordinal'] ?? null,
            'first_retry_ordinal_next258' => $fence['first_retry_ordinal'] ?? null,
            'window_digest_next258' => $windowDigest,
            'required_transition_token_next258' => $required,
            'transition_complete_next258' => $complete && $retryAfterHighWater && $firstRetry !== null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function admittedRows(array $rows, bool $transitionComplete): array
    {
        $out = [];
        foreach ($rows as $row) {
            $isNext = (bool) ($row['window_is_next_source_next252'] ?? false);
            $row['transition_ready_next258'] = $transitionComplete;
            $row['next_source_quarantined_next258'] = $isNext && !$transitionComplete;
            $row['publication_phase_next258'] = $isNext ? 'next-source-transition' : 'current-source-window';
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function admitNextRows(array $rows, bool $acknowledged): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (($row['window_is_next_source_next252'] ?? false) && !$acknowledged) {
                continue;
            }
            $row['next_source_admitted_next258'] = !($row['window_is_next_source_next252'] ?? false) || $acknowledged;
            $out[] = $row;
        }

        foreach ($out as $index => $row) {
            $out[$index]['publication_ordinal_next258'] = $index + 1;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function resumeRows(array $rows, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return $rows;
        }

        $copy = false;
        $out = [];
        foreach ($rows as $row) {
            if ($copy) {
                $out[] = $row;
                continue;
            }
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $copy = true;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $transition
     * @return list<string>
     */
    private static function blockedReasons(array $base, array $transition, ?string $acknowledgedTransitionToken): array
    {
        $reasons = $base['publication_window_fence_next252']['blocked_reasons'] ?? [];
        if (!is_array($reasons)) {
            $reasons = [];
        }
        if (($transition['next_source_available_next258'] ?? false) && $acknowledgedTransitionToken === null) {
            $reasons[] = 'missing-current-source-transition-token-next258';
        }
        if ($acknowledgedTransitionToken !== null && !hash_equals($transition['required_transition_token_next258'], $acknowledgedTransitionToken)) {
            $reasons[] = 'unexpected-current-source-transition-token-next258';
        }

        return array_values($reasons);
    }

    private static function stringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next258 {$name} is missing");
        }

        return $value;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite row-value returning window next258 transition ticket is malformed');
        }

        return $value;
    }
}
