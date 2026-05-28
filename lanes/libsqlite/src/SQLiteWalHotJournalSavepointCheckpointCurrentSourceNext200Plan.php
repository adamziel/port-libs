<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext200Plan
{
    /**
     * @param array<string,mixed> $sealedGeneration
     * @param list<array<string,mixed>> $durabilityReceipts
     * @return array<string,mixed>
     */
    public static function admitDurableReaders(
        array $sealedGeneration,
        array $durabilityReceipts,
        string $expectedHotJournalDigest,
        int $expectedSavepointGeneration,
        string $expectedCheckpointDigest
    ): array {
        self::assertGeneration($sealedGeneration);
        if ($durabilityReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL current-source next200 requires durability receipts');
        }
        if ($expectedHotJournalDigest === '' || $expectedCheckpointDigest === '') {
            throw new \InvalidArgumentException('SQLite WAL current-source next200 expected digests must be non-empty strings');
        }
        if ($expectedSavepointGeneration < 1) {
            throw new \InvalidArgumentException('SQLite WAL current-source next200 savepoint generation must be positive');
        }

        $blocked = [];
        if (($sealedGeneration['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next194') {
            $blocked[] = 'next194_reader_generation_seal_required';
        }
        if (($sealedGeneration['can_expose_reopened_readers'] ?? false) !== true) {
            $blocked[] = 'next194_reader_generation_not_exposable';
        }

        $allowedTickets = self::stringSet($sealedGeneration['reader_ticket_ids']);
        $allowedPages = self::intSet($sealedGeneration['reader_pages']);
        $expectedPublicationToken = (string) $sealedGeneration['publication_token'];
        $previousReaderEpoch = (int) $sealedGeneration['previous_reader_epoch'];
        $sealedReaderEpoch = (int) ($sealedGeneration['sealed_reader_epoch'] ?? 0);
        $receiptRows = [];
        $seenTickets = [];

        foreach ($durabilityReceipts as $index => $receipt) {
            if (!is_array($receipt)) {
                throw new \InvalidArgumentException('SQLite WAL current-source next200 receipt rows must be arrays');
            }
            $ticketId = self::stringField($receipt, 'ticket_id', $index);
            $pageNumber = self::intField($receipt, 'page_number', $index);
            $readerEpoch = self::intField($receipt, 'reader_epoch', $index);
            $publicationToken = self::stringField($receipt, 'publication_token', $index);
            $hotJournalDigest = self::stringField($receipt, 'hot_journal_recovery_digest', $index);
            $checkpointDigest = self::stringField($receipt, 'checkpoint_database_digest', $index);
            $savepointGeneration = self::intField($receipt, 'savepoint_generation', $index);
            $walSyncReceipt = (bool) ($receipt['wal_sync_receipt'] ?? false);
            $databaseSyncReceipt = (bool) ($receipt['database_sync_receipt'] ?? false);
            $directorySyncReceipt = (bool) ($receipt['directory_sync_receipt'] ?? false);
            $readerReopened = (bool) ($receipt['reader_reopened_after_hot_journal'] ?? false);
            $savepointReleased = (bool) ($receipt['savepoint_release_observed'] ?? false);

            $rowBlocked = [];
            if (isset($seenTickets[$ticketId])) {
                $rowBlocked[] = 'duplicate_durability_receipt_ticket';
            }
            $seenTickets[$ticketId] = true;
            if (!isset($allowedTickets[$ticketId])) {
                $rowBlocked[] = 'durability_receipt_ticket_not_in_next194_generation';
            }
            if (!isset($allowedPages[$pageNumber])) {
                $rowBlocked[] = 'durability_receipt_page_not_in_next194_generation';
            }
            if ($readerEpoch <= $previousReaderEpoch || $readerEpoch > $sealedReaderEpoch) {
                $rowBlocked[] = 'durability_receipt_epoch_outside_sealed_generation';
            }
            if (!hash_equals($expectedPublicationToken, $publicationToken)) {
                $rowBlocked[] = 'durability_receipt_publication_token_mismatch';
            }
            if (!hash_equals($expectedHotJournalDigest, $hotJournalDigest)) {
                $rowBlocked[] = 'durability_receipt_hot_journal_digest_mismatch';
            }
            if (!hash_equals($expectedCheckpointDigest, $checkpointDigest)) {
                $rowBlocked[] = 'durability_receipt_checkpoint_digest_mismatch';
            }
            if ($savepointGeneration !== $expectedSavepointGeneration) {
                $rowBlocked[] = 'durability_receipt_savepoint_generation_mismatch';
            }
            if (!$walSyncReceipt) {
                $rowBlocked[] = 'durability_receipt_missing_wal_sync';
            }
            if (!$databaseSyncReceipt) {
                $rowBlocked[] = 'durability_receipt_missing_database_sync';
            }
            if (!$directorySyncReceipt) {
                $rowBlocked[] = 'durability_receipt_missing_directory_sync';
            }
            if (!$readerReopened) {
                $rowBlocked[] = 'durability_receipt_reader_not_reopened_after_hot_journal';
            }
            if (!$savepointReleased) {
                $rowBlocked[] = 'durability_receipt_savepoint_release_not_observed';
            }

            foreach ($rowBlocked as $reason) {
                $blocked[] = $reason;
            }
            $receiptRows[] = [
                'ticket_id' => $ticketId,
                'page_number' => $pageNumber,
                'reader_epoch' => $readerEpoch,
                'publication_token_matches' => hash_equals($expectedPublicationToken, $publicationToken),
                'hot_journal_digest_matches' => hash_equals($expectedHotJournalDigest, $hotJournalDigest),
                'checkpoint_digest_matches' => hash_equals($expectedCheckpointDigest, $checkpointDigest),
                'savepoint_generation' => $savepointGeneration,
                'wal_sync_receipt' => $walSyncReceipt,
                'database_sync_receipt' => $databaseSyncReceipt,
                'directory_sync_receipt' => $directorySyncReceipt,
                'reader_reopened_after_hot_journal' => $readerReopened,
                'savepoint_release_observed' => $savepointReleased,
                'blocked_reasons' => $rowBlocked,
            ];
        }

        foreach (array_keys($allowedTickets) as $ticketId) {
            if (!isset($seenTickets[$ticketId])) {
                $blocked[] = 'missing_durability_receipt_for_sealed_reader_ticket';
                break;
            }
        }

        $blocked = array_values(array_unique($blocked));
        $ready = $blocked === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next200'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next200',
            'reason' => $ready
                ? 'sealed_reader_generation_has_hot_journal_savepoint_checkpoint_durability_receipts'
                : 'sealed_reader_generation_waits_for_hot_journal_savepoint_checkpoint_durability',
            'database_path' => $sealedGeneration['database_path'],
            'wal_path' => $sealedGeneration['wal_path'],
            'journal_path' => $sealedGeneration['journal_path'],
            'publication_token' => $expectedPublicationToken,
            'previous_reader_epoch' => $previousReaderEpoch,
            'sealed_reader_epoch' => $sealedReaderEpoch,
            'receipt_count' => count($receiptRows),
            'receipt_ticket_ids' => array_column($receiptRows, 'ticket_id'),
            'receipt_pages' => array_column($receiptRows, 'page_number'),
            'receipt_rows' => $receiptRows,
            'expected_hot_journal_recovery_digest' => $expectedHotJournalDigest,
            'expected_savepoint_generation' => $expectedSavepointGeneration,
            'expected_checkpoint_database_digest' => $expectedCheckpointDigest,
            'can_admit_durable_readers' => $ready,
            'blocked_reasons' => $blocked,
            'durability_digest' => hash('sha256', implode('|', array_map(
                static fn (array $row): string => implode(':', [
                    (string) $row['ticket_id'],
                    (string) $row['page_number'],
                    (string) $row['reader_epoch'],
                    $row['hot_journal_digest_matches'] ? 'hot' : 'badhot',
                    $row['checkpoint_digest_matches'] ? 'checkpoint' : 'badcheckpoint',
                    $row['wal_sync_receipt'] ? 'wal-sync' : 'no-wal-sync',
                    $row['database_sync_receipt'] ? 'db-sync' : 'no-db-sync',
                ]),
                $receiptRows
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                $sealedGeneration['dependencies'],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next200',
                    'sqlite-hot-journal-savepoint-checkpoint-durable-reader-admission',
                    'wordpress-import-retry-checkpoint-durable-reader-admission',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next194 reader-generation sealing plus existing WAL, checkpoint, directory-sync, and savepoint-release receipts',
            'non_overlap' => 'next200 adds hot-journal recovery digest, savepoint generation, and checkpoint database digest durability admission after next194 reader sealing; it does not repeat WAL byte truncation, checkpoint file-map publication, VFS writer/sync application, or reopened reader ticket sealing',
        ];
    }

    /**
     * @param array<string,mixed> $generation
     */
    private static function assertGeneration(array $generation): void
    {
        foreach (['database_path', 'wal_path', 'journal_path', 'publication_token', 'previous_reader_epoch', 'sealed_reader_epoch', 'reader_ticket_ids', 'reader_pages', 'dependencies'] as $key) {
            if (!array_key_exists($key, $generation)) {
                throw new \InvalidArgumentException("SQLite WAL current-source next200 generation missing {$key}");
            }
        }
        foreach (['database_path', 'wal_path', 'journal_path', 'publication_token'] as $key) {
            if (!is_string($generation[$key]) || $generation[$key] === '') {
                throw new \InvalidArgumentException("SQLite WAL current-source next200 generation {$key} must be a non-empty string");
            }
        }
        if (!is_int($generation['previous_reader_epoch']) || !is_int($generation['sealed_reader_epoch'])) {
            throw new \InvalidArgumentException('SQLite WAL current-source next200 generation epochs must be integers');
        }
        if (!is_array($generation['reader_ticket_ids']) || $generation['reader_ticket_ids'] === [] || !is_array($generation['reader_pages']) || $generation['reader_pages'] === [] || !is_array($generation['dependencies'])) {
            throw new \InvalidArgumentException('SQLite WAL current-source next200 generation arrays are malformed');
        }
        foreach ($generation['reader_ticket_ids'] as $ticketId) {
            if (!is_string($ticketId) || $ticketId === '') {
                throw new \InvalidArgumentException('SQLite WAL current-source next200 reader ticket ids must be non-empty strings');
            }
        }
        foreach ($generation['reader_pages'] as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL current-source next200 reader pages must be one-based integers');
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
            throw new \InvalidArgumentException("SQLite WAL current-source next200 receipt {$index} {$key} must be a non-empty string");
        }

        return $row[$key];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function intField(array $row, string $key, int $index): int
    {
        if (!isset($row[$key]) || !is_int($row[$key])) {
            throw new \InvalidArgumentException("SQLite WAL current-source next200 receipt {$index} {$key} must be an integer");
        }

        return $row[$key];
    }
}
