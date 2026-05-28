<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext261Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<array<string,mixed>>|null $rowReceipts
     * @param array<string,string>|null $segmentWatermarks
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next261',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        string $currentSourceEpoch = 'wp-current-source-261',
        string $nextSourceEpoch = 'wp-next-source-261',
        ?string $expectedCurrentDigest = null,
        ?string $expectedNextDigest = null,
        ?array $rowReceipts = null,
        bool $requireNextReceipts = true,
        ?array $segmentWatermarks = null,
        bool $requireNextSegmentWatermark = true,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext254Plan::execute(
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
            $rowReceipts,
            $requireNextReceipts,
        );

        $admittedRows = $base['admitted_rows_next254'];
        $currentRows = self::rowsForEpoch($admittedRows, $nextSourceEpoch, false);
        $nextRows = self::rowsForEpoch($admittedRows, $nextSourceEpoch, true);
        $expectedWatermarks = [
            'current' => self::segmentWatermark($currentRows, $rowIdColumn, 'current'),
            'next' => self::segmentWatermark($nextRows, $rowIdColumn, 'next'),
        ];
        $providedWatermarks = $segmentWatermarks ?? [
            'current' => $expectedWatermarks['current']['watermark_token'],
            'next' => $expectedWatermarks['next']['watermark_token'],
        ];
        $reasons = self::blockedReasons($base, $expectedWatermarks, $providedWatermarks, $requireNextSegmentWatermark);
        $segmentsReady = $reasons === [];
        $publicationRows = self::publicationRows($currentRows, $nextRows, $segmentsReady, $rowIdColumn, $nextSourceEpoch);
        $resume = self::resume($publicationRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next261',
            'source_window_barrier_next261' => [
                'savepoint' => $savepoint,
                'rowid_column' => $rowIdColumn,
                'current_source_epoch' => $currentSourceEpoch,
                'next_source_epoch' => $nextSourceEpoch,
                'admission_ready' => $base['admission_state_next254'] === 'current-source-next254-window-receipts-admitted',
                'require_next_segment_watermark' => $requireNextSegmentWatermark,
                'expected_current_watermark' => $expectedWatermarks['current']['watermark_token'],
                'expected_next_watermark' => $expectedWatermarks['next']['watermark_token'],
                'provided_current_watermark' => $providedWatermarks['current'] ?? null,
                'provided_next_watermark' => $providedWatermarks['next'] ?? null,
                'current_segment_row_count' => count($currentRows),
                'next_segment_row_count' => count($nextRows),
                'published_row_count' => count($publicationRows),
                'published_next_row_count' => count(self::rowsForEpoch($publicationRows, $nextSourceEpoch, true)),
                'blocked_reasons' => $reasons,
                'barrier_token' => self::barrierToken($base, $expectedWatermarks, $providedWatermarks, $reasons),
            ],
            'expected_source_window_watermarks_next261' => $expectedWatermarks,
            'provided_source_window_watermarks_next261' => $providedWatermarks,
            'published_rows_next261' => $publicationRows,
            'published_tickets_next261' => array_column($publicationRows, 'ticket'),
            'published_next_rows_next261' => self::rowsForEpoch($publicationRows, $nextSourceEpoch, true),
            'published_next_tickets_next261' => array_column(self::rowsForEpoch($publicationRows, $nextSourceEpoch, true), 'ticket'),
            'source_window_resume_next261' => $resume,
            'source_window_resume_tickets_next261' => array_column($resume['rows'], 'ticket'),
            'source_window_state_next261' => $segmentsReady
                ? 'current-source-window-watermarks-admit-next-source-next261'
                : 'current-source-window-watermarks-hold-next-source-next261',
            'dependency_closure_next261' => 'no new support component needed; next261 reuses row-value UPDATE/DELETE RETURNING window row receipts and adds current/next source window segment watermarks for copied WordPress option imports',
            'dependencies_next261' => [
                'sqlite-rowvalue-update-delete-returning-window-current-source-next261',
                'sqlite-returning-window-segment-watermark-next261',
                'wordpress-rowvalue-returning-window-source-watermark-next261',
            ],
            'non_overlap_next261' => 'adds source-window segment watermarks after accepted next254 row-level receipt admission; avoids next254 row receipt matching, next251 digest fencing, next248 publication cursors, next245 yield gates, savepoint-only row-value RETURNING, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsForEpoch(array $rows, string $nextSourceEpoch, bool $next): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($nextSourceEpoch, $next): bool {
            $isNext = ($row['source_epoch_next251'] ?? null) === $nextSourceEpoch;

            return $next ? $isNext : !$isNext;
        }));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function segmentWatermark(array $rows, string $rowIdColumn, string $segment): array
    {
        $items = [];
        foreach ($rows as $index => $row) {
            $ticket = self::stringValue($row['ticket'] ?? null, 'ticket');
            $frameToken = self::stringValue($row['frame_token'] ?? null, 'frame token');
            $epoch = self::stringValue($row['source_epoch_next251'] ?? null, 'source epoch');
            $rowId = $row[$rowIdColumn] ?? null;
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next261 rowid column {$rowIdColumn} must be int or string");
            }
            $items[] = [
                'ordinal' => $index + 1,
                'ticket' => $ticket,
                'source_epoch' => $epoch,
                $rowIdColumn => $rowId,
                'frame_token' => $frameToken,
                'running_bytes' => self::intValue($row['running_bytes'] ?? null, 'running bytes'),
                'following_bytes' => self::intValue($row['following_bytes'] ?? null, 'following bytes'),
                'admission_token' => self::stringValue($row['admission_token_next254'] ?? null, 'admission token'),
            ];
        }

        return [
            'segment' => $segment,
            'row_count' => count($items),
            'row_ids' => array_column($items, $rowIdColumn),
            'tickets' => array_column($items, 'ticket'),
            'window_frame_tokens' => array_column($items, 'frame_token'),
            'running_bytes_final' => $items === [] ? 0 : $items[array_key_last($items)]['running_bytes'],
            'following_bytes_total' => array_sum(array_column($items, 'following_bytes')),
            'watermark_token' => hash('sha256', json_encode($items, JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,array<string,mixed>> $expected
     * @param array<string,string> $provided
     * @return list<string>
     */
    private static function blockedReasons(array $base, array $expected, array $provided, bool $requireNextSegmentWatermark): array
    {
        $reasons = [];
        if (($base['admission_state_next254'] ?? null) !== 'current-source-next254-window-receipts-admitted') {
            $reasons[] = 'row-receipt-admission-not-ready-next261';
        }
        if (($provided['current'] ?? null) !== $expected['current']['watermark_token']) {
            $reasons[] = 'current-source-window-watermark-mismatch-next261';
        }
        if ($requireNextSegmentWatermark && ($provided['next'] ?? null) !== $expected['next']['watermark_token']) {
            $reasons[] = 'next-source-window-watermark-mismatch-next261';
        }
        if (!$requireNextSegmentWatermark) {
            $reasons[] = 'next-source-window-watermark-not-required-next261';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<array<string,mixed>>
     */
    private static function publicationRows(array $currentRows, array $nextRows, bool $segmentsReady, string $rowIdColumn, string $nextSourceEpoch): array
    {
        $rows = $segmentsReady ? array_merge($currentRows, $nextRows) : $currentRows;
        foreach ($rows as $index => $row) {
            $row['source_window_ordinal_next261'] = $index + 1;
            $row['source_window_segment_next261'] = ($row['source_epoch_next251'] ?? null) === $nextSourceEpoch ? 'next' : 'current';
            $row['source_window_row_token_next261'] = hash('sha256', implode('|', [
                (string) $row['source_window_segment_next261'],
                (string) $row['source_window_ordinal_next261'],
                (string) ($row['ticket'] ?? ''),
                (string) ($row[$rowIdColumn] ?? ''),
                (string) ($row['frame_token'] ?? ''),
            ]));
            $rows[$index] = $row;
        }

        return $rows;
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
            throw new \InvalidArgumentException('SQLite row-value returning window next261 resume ticket is not published');
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
     * @param array<string,array<string,mixed>> $expected
     * @param array<string,string> $provided
     * @param list<string> $reasons
     */
    private static function barrierToken(array $base, array $expected, array $provided, array $reasons): string
    {
        return hash('sha256', json_encode([
            'admission' => $base['admission_barrier_next254']['admission_token'] ?? '',
            'expected' => $expected,
            'provided' => $provided,
            'reasons' => $reasons,
        ], JSON_THROW_ON_ERROR));
    }

    private static function stringValue(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next261 {$label} is missing");
        }

        return $value;
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException("SQLite row-value returning window next261 {$label} must be an integer");
        }

        return $value;
    }
}
