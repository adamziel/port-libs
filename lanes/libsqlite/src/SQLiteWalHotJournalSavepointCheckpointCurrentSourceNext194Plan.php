<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext194Plan
{
    /**
     * @param array<string,mixed> $publication
     * @param list<array<string,mixed>> $readerTickets
     * @return array<string,mixed>
     */
    public static function sealReaderGeneration(
        array $publication,
        array $readerTickets,
        int $previousReaderEpoch,
        bool $requireExclusiveCheckpointLock = true
    ): array {
        self::assertPublication($publication);
        if ($previousReaderEpoch < 0) {
            throw new \InvalidArgumentException('SQLite WAL current-source next194 previous reader epoch must be non-negative');
        }
        if ($readerTickets === []) {
            throw new \InvalidArgumentException('SQLite WAL current-source next194 requires reader tickets');
        }

        $blocked = [];
        if (($publication['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next190') {
            $blocked[] = 'next190_retry_checkpoint_publication_required';
        }
        if (($publication['can_publish_retry_checkpoint_source'] ?? false) !== true) {
            $blocked[] = 'next190_retry_checkpoint_source_not_publishable';
        }
        if (($publication['journal_present'] ?? true) !== false) {
            $blocked[] = 'hot_journal_must_be_absent_before_reader_generation_seal';
        }
        if (($publication['wal_checksums_validated'] ?? false) !== true) {
            $blocked[] = 'retry_checkpoint_wal_checksums_required_before_reader_generation_seal';
        }
        if (($publication['wal_commit_frame_count'] ?? 0) < 1) {
            $blocked[] = 'retry_checkpoint_commit_frame_required_before_reader_generation_seal';
        }

        $expectedPublicationToken = (string) $publication['publication_token'];
        $expectedDatabaseHash = (string) $publication['database_sha256'];
        $expectedWalHash = (string) $publication['wal_sha256'];
        $expectedCheckpoint = (int) $publication['expected_checkpoint_sequence'];
        $expectedPageSize = (int) $publication['expected_page_size'];
        $allowedPages = self::intSet($publication['reader_page_numbers']);
        $allowedSources = self::stringSet($publication['reader_next_sources']);
        $ticketRows = [];
        $seenTickets = [];

        foreach ($readerTickets as $index => $ticket) {
            if (!is_array($ticket)) {
                throw new \InvalidArgumentException('SQLite WAL current-source next194 reader ticket rows must be arrays');
            }
            $ticketId = self::stringField($ticket, 'ticket_id', $index);
            if (isset($seenTickets[$ticketId])) {
                $blocked[] = 'duplicate_reader_ticket_id_after_retry_checkpoint_publication';
            }
            $seenTickets[$ticketId] = true;

            $epoch = self::intField($ticket, 'reader_epoch', $index);
            $pageNumber = self::intField($ticket, 'page_number', $index);
            $source = self::stringField($ticket, 'source', $index);
            $publicationToken = self::stringField($ticket, 'publication_token', $index);
            $databaseHash = self::stringField($ticket, 'database_sha256', $index);
            $walHash = self::stringField($ticket, 'wal_sha256', $index);
            $checkpoint = self::intField($ticket, 'checkpoint_sequence', $index);
            $pageSize = self::intField($ticket, 'page_size', $index);
            $hotJournalDigest = $ticket['hot_journal_sha256'] ?? null;
            if ($hotJournalDigest !== null && !is_string($hotJournalDigest)) {
                throw new \InvalidArgumentException("SQLite WAL current-source next194 reader ticket {$index} hot journal digest must be a string or null");
            }
            $hasSyncReceipt = (bool) ($ticket['has_directory_sync_receipt'] ?? false);
            $hasLockReceipt = (bool) ($ticket['has_exclusive_checkpoint_lock_receipt'] ?? false);
            $savepointClosed = (bool) ($ticket['savepoint_closed'] ?? false);

            $ticketBlocked = [];
            if ($epoch <= $previousReaderEpoch) {
                $ticketBlocked[] = 'reader_epoch_must_advance_after_retry_checkpoint_publication';
            }
            if (!isset($allowedPages[$pageNumber])) {
                $ticketBlocked[] = 'reader_ticket_page_not_in_next190_reader_page_set';
            }
            if (!isset($allowedSources[$source])) {
                $ticketBlocked[] = 'reader_ticket_source_not_in_next190_reader_source_set';
            }
            if (!hash_equals($expectedPublicationToken, $publicationToken)) {
                $ticketBlocked[] = 'reader_ticket_publication_token_mismatch';
            }
            if (!hash_equals($expectedDatabaseHash, $databaseHash)) {
                $ticketBlocked[] = 'reader_ticket_database_digest_mismatch';
            }
            if (!hash_equals($expectedWalHash, $walHash)) {
                $ticketBlocked[] = 'reader_ticket_wal_digest_mismatch';
            }
            if ($checkpoint !== $expectedCheckpoint) {
                $ticketBlocked[] = 'reader_ticket_checkpoint_sequence_mismatch';
            }
            if ($pageSize !== $expectedPageSize) {
                $ticketBlocked[] = 'reader_ticket_page_size_mismatch';
            }
            if ($hotJournalDigest !== null && $hotJournalDigest !== '') {
                $ticketBlocked[] = 'reader_ticket_retains_hot_journal_digest';
            }
            if (!$hasSyncReceipt) {
                $ticketBlocked[] = 'reader_ticket_missing_directory_sync_receipt';
            }
            if ($requireExclusiveCheckpointLock && !$hasLockReceipt) {
                $ticketBlocked[] = 'reader_ticket_missing_exclusive_checkpoint_lock_receipt';
            }
            if (!$savepointClosed) {
                $ticketBlocked[] = 'reader_ticket_savepoint_not_closed';
            }

            foreach ($ticketBlocked as $reason) {
                $blocked[] = $reason;
            }
            $ticketRows[] = [
                'ticket_id' => $ticketId,
                'reader_epoch' => $epoch,
                'page_number' => $pageNumber,
                'source' => $source,
                'checkpoint_sequence' => $checkpoint,
                'page_size' => $pageSize,
                'publication_token_matches' => hash_equals($expectedPublicationToken, $publicationToken),
                'database_digest_matches' => hash_equals($expectedDatabaseHash, $databaseHash),
                'wal_digest_matches' => hash_equals($expectedWalHash, $walHash),
                'has_directory_sync_receipt' => $hasSyncReceipt,
                'has_exclusive_checkpoint_lock_receipt' => $hasLockReceipt,
                'savepoint_closed' => $savepointClosed,
                'hot_journal_digest_retained' => $hotJournalDigest !== null && $hotJournalDigest !== '',
                'blocked_reasons' => $ticketBlocked,
            ];
        }

        $blocked = array_values(array_unique($blocked));
        $ready = $blocked === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next194'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next194',
            'reason' => $ready
                ? 'reopened_reader_generation_sealed_to_retry_checkpoint_publication'
                : 'reopened_reader_generation_waits_for_retry_checkpoint_receipts',
            'database_path' => $publication['database_path'],
            'wal_path' => $publication['wal_path'],
            'journal_path' => $publication['journal_path'],
            'publication_token' => $expectedPublicationToken,
            'previous_reader_epoch' => $previousReaderEpoch,
            'sealed_reader_epoch' => $ready ? max(array_column($ticketRows, 'reader_epoch')) : null,
            'reader_ticket_count' => count($ticketRows),
            'reader_ticket_ids' => array_column($ticketRows, 'ticket_id'),
            'reader_pages' => array_column($ticketRows, 'page_number'),
            'reader_sources' => array_column($ticketRows, 'source'),
            'ticket_rows' => $ticketRows,
            'requires_exclusive_checkpoint_lock' => $requireExclusiveCheckpointLock,
            'can_expose_reopened_readers' => $ready,
            'blocked_reasons' => $blocked,
            'seal_digest' => hash('sha256', implode('|', array_map(
                static fn (array $row): string => implode(':', [
                    (string) $row['ticket_id'],
                    (string) $row['reader_epoch'],
                    (string) $row['page_number'],
                    (string) $row['source'],
                    $row['publication_token_matches'] ? 'pub' : 'badpub',
                    $row['database_digest_matches'] ? 'db' : 'baddb',
                    $row['wal_digest_matches'] ? 'wal' : 'badwal',
                ]),
                $ticketRows
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                $publication['dependencies'],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next194',
                    'sqlite-reopened-reader-generation-seal',
                    'wordpress-import-retry-checkpoint-reader-exposure',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next190 retry checkpoint publication evidence plus directory-sync and checkpoint-lock receipts',
            'non_overlap' => 'next194 seals reopened reader tickets to an already-published next190 retry checkpoint; it does not repeat WAL byte truncation, rollback-journal apply, checkpoint transaction planning, VFS writer/sync application, or next190 file-map publication',
        ];
    }

    /**
     * @param array<string,mixed> $publication
     */
    private static function assertPublication(array $publication): void
    {
        foreach (['database_path', 'wal_path', 'journal_path', 'publication_token', 'database_sha256', 'wal_sha256', 'expected_checkpoint_sequence', 'expected_page_size', 'reader_page_numbers', 'reader_next_sources', 'dependencies'] as $key) {
            if (!array_key_exists($key, $publication)) {
                throw new \InvalidArgumentException("SQLite WAL current-source next194 publication missing {$key}");
            }
        }
        foreach (['database_path', 'wal_path', 'journal_path', 'publication_token', 'database_sha256', 'wal_sha256'] as $key) {
            if (!is_string($publication[$key]) || $publication[$key] === '') {
                throw new \InvalidArgumentException("SQLite WAL current-source next194 publication {$key} must be a non-empty string");
            }
        }
        if (!is_int($publication['expected_checkpoint_sequence']) || $publication['expected_checkpoint_sequence'] < 0) {
            throw new \InvalidArgumentException('SQLite WAL current-source next194 publication checkpoint sequence must be a non-negative integer');
        }
        if (!is_int($publication['expected_page_size']) || $publication['expected_page_size'] < 512 || (($publication['expected_page_size'] & ($publication['expected_page_size'] - 1)) !== 0)) {
            throw new \InvalidArgumentException('SQLite WAL current-source next194 publication page size must be a power of two at least 512');
        }
        if (!is_array($publication['reader_page_numbers']) || $publication['reader_page_numbers'] === [] || !is_array($publication['reader_next_sources']) || $publication['reader_next_sources'] === [] || !is_array($publication['dependencies'])) {
            throw new \InvalidArgumentException('SQLite WAL current-source next194 publication reader/dependency arrays are malformed');
        }
        foreach ($publication['reader_page_numbers'] as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL current-source next194 publication reader pages must be one-based integers');
            }
        }
        foreach ($publication['reader_next_sources'] as $source) {
            if (!is_string($source) || $source === '') {
                throw new \InvalidArgumentException('SQLite WAL current-source next194 publication reader sources must be non-empty strings');
            }
        }
    }

    /**
     * @param list<int> $values
     * @return array<int,true>
     */
    private static function intSet(array $values): array
    {
        $set = [];
        foreach ($values as $value) {
            $set[$value] = true;
        }

        return $set;
    }

    /**
     * @param list<string> $values
     * @return array<string,true>
     */
    private static function stringSet(array $values): array
    {
        $set = [];
        foreach ($values as $value) {
            $set[$value] = true;
        }

        return $set;
    }

    /**
     * @param array<string,mixed> $ticket
     */
    private static function stringField(array $ticket, string $key, int $index): string
    {
        if (!isset($ticket[$key]) || !is_string($ticket[$key]) || $ticket[$key] === '') {
            throw new \InvalidArgumentException("SQLite WAL current-source next194 reader ticket {$index} {$key} must be a non-empty string");
        }

        return $ticket[$key];
    }

    /**
     * @param array<string,mixed> $ticket
     */
    private static function intField(array $ticket, string $key, int $index): int
    {
        if (!isset($ticket[$key]) || !is_int($ticket[$key])) {
            throw new \InvalidArgumentException("SQLite WAL current-source next194 reader ticket {$index} {$key} must be an integer");
        }

        return $ticket[$key];
    }
}
