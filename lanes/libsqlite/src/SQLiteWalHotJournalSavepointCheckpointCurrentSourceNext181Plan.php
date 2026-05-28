<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext181Plan
{
    /**
     * @param array<string,mixed> $prepared
     * @param array<string,mixed> $receipt
     * @return array<string,mixed>
     */
    public static function plan(
        array $prepared,
        array $receipt,
        string $databaseBytes,
        ?string $journalBytes,
        string $walBytes,
        SQLiteWal $reopenedWal
    ): array {
        self::assertPrepared($prepared);
        self::assertReceipt($receipt);

        $expectedDatabase = self::preparedDurableString($prepared, ['base_plan', 'current_durable', 'database_bytes']);
        $expectedWal = self::preparedDurableString($prepared, ['base_plan', 'next_durable', 'wal_bytes']);
        $databaseHash = hash('sha256', $databaseBytes);
        $walHash = hash('sha256', $walBytes);
        $expectedDatabaseHash = hash('sha256', $expectedDatabase);
        $expectedWalHash = hash('sha256', $expectedWal);
        $databaseMatches = hash_equals($expectedDatabaseHash, $databaseHash);
        $walMatches = hash_equals($expectedWalHash, $walHash);
        $journalAbsent = $journalBytes === null;
        $receiptPublishable = ($receipt['can_publish_receipt'] ?? false) === true;
        $receiptDigestPresent = is_string($receipt['receipt_digest'] ?? null) && strlen((string) $receipt['receipt_digest']) === 64;
        $walReopened = $reopenedWal->checksumsValidated
            && $reopenedWal->header->checkpointSequence === SQLiteWal::parse($expectedWal, null, true)->header->checkpointSequence
            && count($reopenedWal->frames) === count(SQLiteWal::parse($expectedWal, null, true)->frames);
        $commitFrames = array_values(array_filter($reopenedWal->frames, static fn (SQLiteWalFrame $frame): bool => $frame->isCommitFrame()));
        $lastCommit = $reopenedWal->lastCommitFrame();
        $lastCommitPageCount = $lastCommit?->databasePageCountAfterCommit;
        $sourceRows = [
            [
                'name' => 'database',
                'expected_sha256' => $expectedDatabaseHash,
                'actual_sha256' => $databaseHash,
                'expected_length' => strlen($expectedDatabase),
                'actual_length' => strlen($databaseBytes),
                'matches' => $databaseMatches,
            ],
            [
                'name' => 'journal',
                'expected_sha256' => null,
                'actual_sha256' => $journalBytes === null ? null : hash('sha256', $journalBytes),
                'expected_length' => null,
                'actual_length' => $journalBytes === null ? null : strlen($journalBytes),
                'matches' => $journalAbsent,
            ],
            [
                'name' => 'wal',
                'expected_sha256' => $expectedWalHash,
                'actual_sha256' => $walHash,
                'expected_length' => strlen($expectedWal),
                'actual_length' => strlen($walBytes),
                'matches' => $walMatches,
            ],
        ];
        $stale = array_values(array_filter($sourceRows, static fn (array $row): bool => !(bool) $row['matches']));
        $ready = $receiptPublishable && $receiptDigestPresent && $databaseMatches && $journalAbsent && $walMatches && $walReopened && $lastCommit !== null;

        $blocked = [];
        if (!$receiptPublishable) {
            $blocked[] = 'post_apply_receipt_not_publishable';
        }
        if (!$receiptDigestPresent) {
            $blocked[] = 'post_apply_receipt_digest_missing';
        }
        foreach ($stale as $row) {
            $blocked[] = 'reopened_' . $row['name'] . '_does_not_match_durable_payload';
        }
        if (!$walReopened) {
            $blocked[] = 'reopened_wal_checksum_or_header_mismatch';
        }
        if ($lastCommit === null) {
            $blocked[] = 'reopened_wal_has_no_commit_frame';
        }

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next181'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next181',
            'reason' => $ready
                ? 'reopened_files_and_wal_frames_confirm_post_apply_checkpoint_publication'
                : 'reopened_files_or_wal_frames_block_checkpoint_publication',
            'database_path' => (string) $prepared['database_path'],
            'journal_path' => (string) $prepared['journal_path'],
            'wal_path' => (string) $prepared['wal_path'],
            'receipt_digest' => $receipt['receipt_digest'] ?? null,
            'receipt_publishable' => $receiptPublishable,
            'can_reopen_publish' => $ready,
            'source_rows' => $sourceRows,
            'matched_source_names' => array_values(array_column(array_filter($sourceRows, static fn (array $row): bool => (bool) $row['matches']), 'name')),
            'stale_source_names' => array_column($stale, 'name'),
            'blocked_reasons' => $blocked,
            'wal_checkpoint_sequence' => $reopenedWal->header->checkpointSequence,
            'wal_frame_count' => count($reopenedWal->frames),
            'wal_commit_frame_count' => count($commitFrames),
            'wal_last_commit_frame' => $lastCommit?->index,
            'wal_last_commit_page_count' => $lastCommitPageCount,
            'wal_checksums_validated' => $reopenedWal->checksumsValidated,
            'reopen_digest' => hash('sha256', implode('|', [
                (string) ($receipt['receipt_digest'] ?? ''),
                $databaseHash,
                $journalAbsent ? 'journal-absent' : hash('sha256', (string) $journalBytes),
                $walHash,
                (string) $reopenedWal->header->checkpointSequence,
                (string) count($reopenedWal->frames),
                (string) ($lastCommit?->index ?? 0),
            ])),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($receipt['dependencies'] ?? null) ? $receipt['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next181',
                    'sqlite-wal-post-apply-reopen-validated',
                    'wordpress-import-hot-journal-checkpoint-reopen-wal-frames',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native WAL checksum parsing plus next178 post-apply receipt payloads',
            'non_overlap' => 'adds reopen-time WAL checksum/frame admission after next178 receipt validation; does not repeat next175 VFS writes, next173 source-hash admission, or next178 post-apply file receipt matching',
        ];
    }

    /**
     * @param array<string,mixed> $prepared
     */
    private static function assertPrepared(array $prepared): void
    {
        foreach (['database_path', 'journal_path', 'wal_path', 'base_plan'] as $key) {
            if (!array_key_exists($key, $prepared)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next181 missing prepared {$key}");
            }
        }
    }

    /**
     * @param array<string,mixed> $receipt
     */
    private static function assertReceipt(array $receipt): void
    {
        foreach (['status', 'can_publish_receipt', 'receipt_digest'] as $key) {
            if (!array_key_exists($key, $receipt)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next181 missing receipt {$key}");
            }
        }
    }

    /**
     * @param array<string,mixed> $prepared
     * @param list<string> $path
     */
    private static function preparedDurableString(array $prepared, array $path): string
    {
        $value = $prepared;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next181 prepared durable payload is missing');
            }
            $value = $value[$key];
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next181 durable payload must be a string');
        }

        return $value;
    }
}
