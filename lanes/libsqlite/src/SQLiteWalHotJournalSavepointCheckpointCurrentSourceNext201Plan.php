<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext201Plan
{
    /**
     * @param array<string,mixed> $admission
     * @param list<array<string,mixed>> $sourceRows
     * @return array<string,mixed>
     */
    public static function publishCurrentSources(
        array $admission,
        array $sourceRows,
        string $expectedCheckpointDigest,
        string $expectedWalDigest,
        ?string $hotJournalBytes
    ): array {
        self::assertAdmission($admission);
        if ($sourceRows === []) {
            throw new \InvalidArgumentException('SQLite WAL current-source next201 requires current-source rows');
        }
        if ($expectedCheckpointDigest === '' || $expectedWalDigest === '') {
            throw new \InvalidArgumentException('SQLite WAL current-source next201 expected digests must be non-empty strings');
        }

        $blocked = [];
        if (($admission['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next200') {
            $blocked[] = 'next200_durable_reader_admission_required';
        }
        if (($admission['can_admit_durable_readers'] ?? false) !== true) {
            $blocked[] = 'next200_durable_reader_admission_not_publishable';
        }
        if ($hotJournalBytes !== null) {
            $blocked[] = 'hot_journal_still_present_after_current_source_publication';
        }

        $allowedTickets = self::stringSet($admission['receipt_ticket_ids']);
        $allowedPages = self::intSet($admission['receipt_pages']);
        $expectedPublicationToken = (string) $admission['publication_token'];
        $previousEpoch = (int) $admission['previous_reader_epoch'];
        $sealedEpoch = (int) $admission['sealed_reader_epoch'];
        $expectedSavepointGeneration = (int) $admission['expected_savepoint_generation'];
        $publishedRows = [];
        $seenTickets = [];

        foreach ($sourceRows as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite WAL current-source next201 source rows must be arrays');
            }

            $ticketId = self::stringField($row, 'ticket_id', $index);
            $pageNumber = self::intField($row, 'page_number', $index);
            $readerEpoch = self::intField($row, 'reader_epoch', $index);
            $publicationToken = self::stringField($row, 'publication_token', $index);
            $source = self::stringField($row, 'source', $index);
            $sourceDigest = self::stringField($row, 'source_digest', $index);
            $cacheEpoch = self::intField($row, 'cache_epoch', $index);
            $savepointGeneration = self::intField($row, 'savepoint_generation', $index);
            $checkpointVisible = (bool) ($row['checkpoint_visible'] ?? false);
            $readerCacheRebased = (bool) ($row['reader_cache_rebased'] ?? false);

            $rowBlocked = [];
            if (isset($seenTickets[$ticketId])) {
                $rowBlocked[] = 'duplicate_current_source_ticket';
            }
            $seenTickets[$ticketId] = true;
            if (!isset($allowedTickets[$ticketId])) {
                $rowBlocked[] = 'current_source_ticket_not_durably_admitted';
            }
            if (!isset($allowedPages[$pageNumber])) {
                $rowBlocked[] = 'current_source_page_not_durably_admitted';
            }
            if ($readerEpoch <= $previousEpoch || $readerEpoch > $sealedEpoch) {
                $rowBlocked[] = 'current_source_epoch_outside_sealed_generation';
            }
            if (!hash_equals($expectedPublicationToken, $publicationToken)) {
                $rowBlocked[] = 'current_source_publication_token_mismatch';
            }
            if (!in_array($source, ['checkpoint-database', 'next-wal'], true)) {
                $rowBlocked[] = 'current_source_kind_unknown';
            }

            $expectedDigest = $source === 'checkpoint-database' ? $expectedCheckpointDigest : $expectedWalDigest;
            $digestMatches = hash_equals($expectedDigest, $sourceDigest);
            if (!$digestMatches) {
                $rowBlocked[] = 'current_source_digest_mismatch';
            }
            if ($cacheEpoch < $readerEpoch) {
                $rowBlocked[] = 'current_source_cache_epoch_stale';
            }
            if ($savepointGeneration !== $expectedSavepointGeneration) {
                $rowBlocked[] = 'current_source_savepoint_generation_mismatch';
            }
            if (!$checkpointVisible) {
                $rowBlocked[] = 'current_source_checkpoint_not_visible';
            }
            if (!$readerCacheRebased) {
                $rowBlocked[] = 'current_source_reader_cache_not_rebased';
            }

            foreach ($rowBlocked as $reason) {
                $blocked[] = $reason;
            }
            $publishedRows[] = [
                'ticket_id' => $ticketId,
                'page_number' => $pageNumber,
                'reader_epoch' => $readerEpoch,
                'source' => $source,
                'source_digest_matches' => $digestMatches,
                'cache_epoch' => $cacheEpoch,
                'savepoint_generation' => $savepointGeneration,
                'checkpoint_visible' => $checkpointVisible,
                'reader_cache_rebased' => $readerCacheRebased,
                'blocked_reasons' => $rowBlocked,
            ];
        }

        foreach (array_keys($allowedTickets) as $ticketId) {
            if (!isset($seenTickets[$ticketId])) {
                $blocked[] = 'missing_current_source_for_durable_reader_ticket';
                break;
            }
        }

        $blocked = array_values(array_unique($blocked));
        $ready = $blocked === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next201'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next201',
            'reason' => $ready
                ? 'durable_reader_current_sources_rebased_after_hot_journal_savepoint_checkpoint'
                : 'durable_reader_current_sources_wait_for_rebased_checkpoint_visibility',
            'database_path' => $admission['database_path'],
            'wal_path' => $admission['wal_path'],
            'journal_path' => $admission['journal_path'],
            'publication_token' => $expectedPublicationToken,
            'previous_reader_epoch' => $previousEpoch,
            'sealed_reader_epoch' => $sealedEpoch,
            'source_count' => count($publishedRows),
            'source_ticket_ids' => array_column($publishedRows, 'ticket_id'),
            'source_pages' => array_column($publishedRows, 'page_number'),
            'source_kinds' => array_values(array_unique(array_column($publishedRows, 'source'))),
            'checkpoint_source_count' => count(array_filter($publishedRows, static fn (array $row): bool => $row['source'] === 'checkpoint-database')),
            'wal_source_count' => count(array_filter($publishedRows, static fn (array $row): bool => $row['source'] === 'next-wal')),
            'expected_checkpoint_database_digest' => $expectedCheckpointDigest,
            'expected_wal_digest' => $expectedWalDigest,
            'hot_journal_absent' => $hotJournalBytes === null,
            'published_rows' => $publishedRows,
            'can_publish_current_sources' => $ready,
            'blocked_reasons' => $blocked,
            'publication_digest' => hash('sha256', implode('|', array_merge(
                [$expectedPublicationToken, $expectedCheckpointDigest, $expectedWalDigest, $hotJournalBytes === null ? 'journal-absent' : hash('sha256', $hotJournalBytes)],
                array_map(
                    static fn (array $row): string => implode(':', [
                        (string) $row['ticket_id'],
                        (string) $row['page_number'],
                        (string) $row['reader_epoch'],
                        (string) $row['source'],
                        $row['source_digest_matches'] ? 'digest-ok' : 'digest-stale',
                        $row['checkpoint_visible'] ? 'visible' : 'hidden',
                        $row['reader_cache_rebased'] ? 'rebased' : 'stale-cache',
                    ]),
                    $publishedRows
                )
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                $admission['dependencies'],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next201',
                    'sqlite-hot-journal-savepoint-checkpoint-current-source-publication',
                    'wordpress-import-retry-current-source-reader-cache-rebase',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next200 durable reader admission plus existing WAL/checkpoint/source digest receipts',
            'non_overlap' => 'next201 admits rebased current-source rows after next200 durability receipts; it does not repeat WAL byte truncation, VFS writer/sync application, rollback-journal apply, checkpoint transaction planning, next194 reader sealing, or next200 durability receipt validation',
        ];
    }

    /**
     * @param array<string,mixed> $admission
     */
    private static function assertAdmission(array $admission): void
    {
        foreach (['database_path', 'wal_path', 'journal_path', 'publication_token', 'previous_reader_epoch', 'sealed_reader_epoch', 'receipt_ticket_ids', 'receipt_pages', 'expected_savepoint_generation', 'dependencies'] as $key) {
            if (!array_key_exists($key, $admission)) {
                throw new \InvalidArgumentException("SQLite WAL current-source next201 admission missing {$key}");
            }
        }
        foreach (['database_path', 'wal_path', 'journal_path', 'publication_token'] as $key) {
            if (!is_string($admission[$key]) || $admission[$key] === '') {
                throw new \InvalidArgumentException("SQLite WAL current-source next201 admission {$key} must be a non-empty string");
            }
        }
        if (!is_int($admission['previous_reader_epoch']) || !is_int($admission['sealed_reader_epoch']) || !is_int($admission['expected_savepoint_generation'])) {
            throw new \InvalidArgumentException('SQLite WAL current-source next201 admission epochs and savepoint generation must be integers');
        }
        if (!is_array($admission['receipt_ticket_ids']) || $admission['receipt_ticket_ids'] === [] || !is_array($admission['receipt_pages']) || $admission['receipt_pages'] === [] || !is_array($admission['dependencies'])) {
            throw new \InvalidArgumentException('SQLite WAL current-source next201 admission arrays are malformed');
        }
        foreach ($admission['receipt_ticket_ids'] as $ticketId) {
            if (!is_string($ticketId) || $ticketId === '') {
                throw new \InvalidArgumentException('SQLite WAL current-source next201 admission ticket ids must be non-empty strings');
            }
        }
        foreach ($admission['receipt_pages'] as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL current-source next201 admission pages must be one-based integers');
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
     * @param array<string,mixed> $row
     */
    private static function stringField(array $row, string $key, int $index): string
    {
        if (!isset($row[$key]) || !is_string($row[$key]) || $row[$key] === '') {
            throw new \InvalidArgumentException("SQLite WAL current-source next201 row {$index} {$key} must be a non-empty string");
        }

        return $row[$key];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function intField(array $row, string $key, int $index): int
    {
        if (!isset($row[$key]) || !is_int($row[$key])) {
            throw new \InvalidArgumentException("SQLite WAL current-source next201 row {$index} {$key} must be an integer");
        }

        return $row[$key];
    }
}
