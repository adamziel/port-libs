<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext193Plan
{
    /**
     * @param array<string,mixed> $retryHandoff
     * @param list<array<string,mixed>> $readerSlots
     * @param list<int> $expectedPages
     * @return array<string,mixed>
     */
    public static function publishReaderMarks(
        array $retryHandoff,
        array $readerSlots,
        int $generation,
        array $expectedPages
    ): array {
        self::assertRetryHandoff($retryHandoff);
        if ($generation < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next193 generation must be positive');
        }
        if ($expectedPages === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next193 requires expected reader pages');
        }

        $retryToken = (string) $retryHandoff['retry_reader_token'];
        $pageSet = [];
        foreach ($expectedPages as $page) {
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next193 expected pages must be one-based integers');
            }
            $pageSet[$page] = true;
        }

        $rows = [];
        $blocked = [];
        $seenSlots = [];
        $coveredPages = [];
        foreach ($readerSlots as $slot) {
            self::assertSlot($slot);
            $slotId = (int) $slot['slot'];
            $page = (int) $slot['page_number'];
            $token = (string) $slot['reader_token'];
            $slotGeneration = (int) $slot['generation'];
            $frameIndex = $slot['frame_index'];
            $source = (string) $slot['source'];
            $valid = true;
            $reasons = [];

            if (isset($seenSlots[$slotId])) {
                $valid = false;
                $reasons[] = 'duplicate_reader_mark_slot';
                $blocked[] = 'duplicate_reader_mark_slot';
            }
            $seenSlots[$slotId] = true;

            if (!isset($pageSet[$page])) {
                $valid = false;
                $reasons[] = 'reader_mark_page_not_expected';
                $blocked[] = 'reader_mark_page_not_expected';
            } else {
                $coveredPages[$page] = true;
            }
            if ($token !== $retryToken) {
                $valid = false;
                $reasons[] = 'reader_mark_token_not_retry_source';
                $blocked[] = 'reader_mark_token_not_retry_source';
            }
            if ($slotGeneration !== $generation) {
                $valid = false;
                $reasons[] = 'reader_mark_generation_mismatch';
                $blocked[] = 'reader_mark_generation_mismatch';
            }
            if ($source !== 'next-wal' && $source !== 'checkpoint-database') {
                $valid = false;
                $reasons[] = 'reader_mark_source_not_retry_visible';
                $blocked[] = 'reader_mark_source_not_retry_visible';
            }
            if ($source === 'checkpoint-database' && $frameIndex !== null) {
                $valid = false;
                $reasons[] = 'checkpoint_database_reader_mark_has_frame';
                $blocked[] = 'checkpoint_database_reader_mark_has_frame';
            }
            if ($source === 'next-wal' && (!is_int($frameIndex) || $frameIndex < 1)) {
                $valid = false;
                $reasons[] = 'next_wal_reader_mark_missing_frame';
                $blocked[] = 'next_wal_reader_mark_missing_frame';
            }

            $rows[] = [
                'slot' => $slotId,
                'page_number' => $page,
                'source' => $source,
                'frame_index' => $frameIndex,
                'reader_token' => $token,
                'generation' => $slotGeneration,
                'published' => $valid,
                'blocked_reasons' => $reasons,
            ];
        }

        $missingPages = array_values(array_diff($expectedPages, array_keys($coveredPages)));
        if ($missingPages !== []) {
            $blocked[] = 'reader_mark_pages_missing';
        }
        if (($retryHandoff['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next187') {
            $blocked[] = 'next187_retry_handoff_not_admitted';
        }
        if (($retryHandoff['can_admit_retry_checkpoint_source'] ?? false) !== true) {
            $blocked[] = 'retry_checkpoint_source_not_admitted';
        }
        if (($retryHandoff['stale_reader_tokens'] ?? []) !== []) {
            $blocked[] = 'stale_reader_tokens_not_retired_before_mark_publish';
        }

        $blocked = array_values(array_unique($blocked));
        $ready = $blocked === [];
        $publishedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['published']));

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next193'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next193',
            'reason' => $ready
                ? 'retry_wal_reader_marks_published_after_hot_journal_checkpoint_handoff'
                : 'retry_wal_reader_marks_wait_for_clean_current_source_handoff',
            'database_path' => (string) $retryHandoff['database_path'],
            'wal_path' => (string) $retryHandoff['wal_path'],
            'generation' => $generation,
            'retry_reader_token' => $retryToken,
            'expected_pages' => $expectedPages,
            'missing_pages' => $missingPages,
            'reader_mark_rows' => $rows,
            'published_reader_mark_rows' => $publishedRows,
            'published_slot_count' => count($publishedRows),
            'published_pages' => array_values(array_map(static fn (array $row): int => $row['page_number'], $publishedRows)),
            'published_sources' => array_values(array_unique(array_map(static fn (array $row): string => $row['source'], $publishedRows))),
            'requires_reader_reopen' => !$ready,
            'can_publish_reader_marks' => $ready,
            'handoff_transition_digest' => (string) $retryHandoff['retry_transition_digest'],
            'next_wal_sha256' => (string) $retryHandoff['next_wal_sha256'],
            'reader_mark_digest' => self::readerMarkDigest($retryToken, $generation, $rows),
            'blocked_reasons' => $blocked,
            'dependencies' => array_values(array_unique(array_merge(
                is_array($retryHandoff['dependencies'] ?? null) ? $retryHandoff['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next193',
                    'sqlite-wal-retry-reader-mark-publication-after-hot-journal',
                    'wordpress-wal-import-retry-reader-mark-publication',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; composes next187 retry-source admission with lane-local reader-mark slot metadata',
            'non_overlap' => 'next193 publishes retry WAL reader-mark slots after next187 token handoff; it does not repeat hot-journal recovery, VFS apply, rollback-journal apply, savepoint byte truncation, checkpoint transactions, next184 salt/checkpoint separation, or next187 token retirement',
        ];
    }

    /**
     * @param array<string,mixed> $retryHandoff
     */
    private static function assertRetryHandoff(array $retryHandoff): void
    {
        foreach (['database_path', 'wal_path', 'retry_reader_token', 'retry_transition_digest', 'next_wal_sha256'] as $key) {
            if (!array_key_exists($key, $retryHandoff)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next193 missing handoff {$key}");
            }
        }
    }

    /**
     * @param array<string,mixed> $slot
     */
    private static function assertSlot(array $slot): void
    {
        foreach (['slot', 'page_number', 'reader_token', 'generation', 'source', 'frame_index'] as $key) {
            if (!array_key_exists($key, $slot)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next193 missing reader slot {$key}");
            }
        }
        if (!is_int($slot['slot']) || $slot['slot'] < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next193 reader slot ids must be non-negative integers');
        }
        if (!is_int($slot['page_number']) || $slot['page_number'] < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next193 reader slot pages must be one-based integers');
        }
        if (!is_string($slot['reader_token']) || $slot['reader_token'] === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next193 reader slot tokens must be non-empty strings');
        }
        if (!is_int($slot['generation']) || $slot['generation'] < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next193 reader slot generation must be positive');
        }
        if (!is_string($slot['source']) || $slot['source'] === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next193 reader slot source must be a non-empty string');
        }
        if ($slot['frame_index'] !== null && !is_int($slot['frame_index'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next193 reader slot frame indexes must be integers or null');
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function readerMarkDigest(string $retryToken, int $generation, array $rows): string
    {
        return hash('sha256', json_encode([
            'retryToken' => $retryToken,
            'generation' => $generation,
            'rows' => $rows,
        ], JSON_THROW_ON_ERROR));
    }
}
