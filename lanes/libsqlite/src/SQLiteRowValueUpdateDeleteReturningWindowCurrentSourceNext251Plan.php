<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext251Plan
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
        string $savepoint = 'wp_options_rowvalue_window_current_next251',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        string $currentSourceEpoch = 'wp-current-source-251',
        string $nextSourceEpoch = 'wp-next-source-251',
        ?string $expectedCurrentDigest = null,
        ?string $expectedNextDigest = null,
    ): array {
        self::token($currentSourceEpoch, 'current source epoch');
        self::token($nextSourceEpoch, 'next source epoch');
        if ($currentSourceEpoch === $nextSourceEpoch) {
            throw new \InvalidArgumentException('SQLite row-value returning window next251 source epochs must differ');
        }

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext248Plan::execute(
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

        $barrier = $base['publication_barrier_next248'];
        $currentDigest = self::token((string) ($barrier['current_source_digest'] ?? ''), 'current source digest');
        $nextDigest = self::token((string) ($barrier['next_source_digest'] ?? ''), 'next source digest');
        $digestReasons = self::digestReasons($currentDigest, $nextDigest, $expectedCurrentDigest, $expectedNextDigest);
        $sourceReady = (bool) ($barrier['next_source_exposed'] ?? false) && $digestReasons === [];
        $handoffRows = self::handoffRows($base['publication_sequence_next248'], $sourceReady, $currentSourceEpoch, $nextSourceEpoch);
        $retryRows = array_values(array_filter($handoffRows, static fn (array $row): bool => ($row['source_epoch_next251'] ?? null) === $nextSourceEpoch));
        $resume = self::resume($handoffRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next251',
            'source_handoff_barrier_next251' => [
                'savepoint' => $savepoint,
                'current_source_epoch' => $currentSourceEpoch,
                'next_source_epoch' => $nextSourceEpoch,
                'current_source_digest' => $currentDigest,
                'next_source_digest' => $nextDigest,
                'expected_current_source_digest' => $expectedCurrentDigest,
                'expected_next_source_digest' => $expectedNextDigest,
                'current_source_complete' => (bool) ($barrier['current_source_complete'] ?? false),
                'next_source_exposed_by_publication' => (bool) ($barrier['next_source_exposed'] ?? false),
                'next_source_ready' => $sourceReady,
                'blocked_reasons' => array_values(array_merge($barrier['blocked_reasons'] ?? [], $digestReasons)),
                'handoff_token' => self::handoffToken($base, $currentSourceEpoch, $nextSourceEpoch, $digestReasons),
                'retry_visible_count' => count($retryRows),
                'handoff_row_count' => count($handoffRows),
            ],
            'source_handoff_rows_next251' => $handoffRows,
            'source_handoff_tickets_next251' => array_column($handoffRows, 'ticket'),
            'source_handoff_retry_rows_next251' => $retryRows,
            'source_handoff_retry_tickets_next251' => array_column($retryRows, 'ticket'),
            'source_handoff_resume_next251' => $resume,
            'source_handoff_resume_tickets_next251' => array_column($resume['rows'], 'ticket'),
            'source_handoff_state_next251' => $sourceReady
                ? 'current-source-drained-next-source-digest-ready-next251'
                : 'current-source-or-digest-fence-holds-next-source-next251',
            'dependency_closure_next251' => 'no new support component needed; next251 reuses row-value UPDATE/DELETE RETURNING window publication sequencing and adds a source epoch/digest handoff fence for copied WordPress option imports',
            'dependencies_next251' => [
                'sqlite-rowvalue-update-delete-returning-window-source-handoff-next251',
                'sqlite-returning-current-source-digest-fence-next251',
                'wordpress-rowvalue-returning-window-current-next-source-handoff-next251',
            ],
            'non_overlap_next251' => 'adds source epoch/digest handoff fencing after accepted next248 resumable publication; avoids next248 cursor sequencing, next245 yield gates, next244 transition windows, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @return list<string>
     */
    private static function digestReasons(string $currentDigest, string $nextDigest, ?string $expectedCurrentDigest, ?string $expectedNextDigest): array
    {
        $reasons = [];
        if ($expectedCurrentDigest !== null && $expectedCurrentDigest !== $currentDigest) {
            $reasons[] = 'current-source-digest-mismatch-next251';
        }
        if ($expectedNextDigest !== null && $expectedNextDigest !== $nextDigest) {
            $reasons[] = 'next-source-digest-mismatch-next251';
        }

        return $reasons;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function handoffRows(array $rows, bool $sourceReady, string $currentEpoch, string $nextEpoch): array
    {
        $handoffRows = [];
        foreach ($rows as $index => $row) {
            $isRetry = (bool) ($row['next_source_visible_next248'] ?? false);
            $visible = !$isRetry || $sourceReady;
            if (!$visible) {
                continue;
            }
            $epoch = $isRetry ? $nextEpoch : $currentEpoch;
            $row['source_epoch_next251'] = $epoch;
            $row['handoff_visible_next251'] = true;
            $row['handoff_ordinal_next251'] = count($handoffRows) + 1;
            $row['source_handoff_token_next251'] = hash('sha256', $epoch . '|' . ($row['ticket'] ?? '') . '|' . $index);
            $handoffRows[] = $row;
        }

        return $handoffRows;
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
            throw new \InvalidArgumentException('SQLite row-value returning window next251 resume ticket is not in the source handoff rows');
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
     * @param list<string> $digestReasons
     */
    private static function handoffToken(array $base, string $currentEpoch, string $nextEpoch, array $digestReasons): string
    {
        return hash('sha256', json_encode([
            'barrier' => $base['publication_barrier_next248']['barrier_token'] ?? '',
            'currentEpoch' => $currentEpoch,
            'nextEpoch' => $nextEpoch,
            'digestReasons' => $digestReasons,
        ], JSON_THROW_ON_ERROR));
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next251 {$label} is missing");
        }

        return $value;
    }
}
