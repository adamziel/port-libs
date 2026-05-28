<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext190Plan
{
    /**
     * @param array<string,mixed> $readerFence
     * @param array<string,string|null> $files
     * @return array<string,mixed>
     */
    public static function plan(
        array $readerFence,
        array $files,
        string $expectedDatabaseBytes,
        int $expectedPageSize,
        int $expectedCheckpointSequence,
        bool $requireDirectorySync = true
    ): array {
        self::assertFence($readerFence);
        $files = self::normalizeFiles($files);
        if ($expectedDatabaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next190 expected database bytes cannot be empty');
        }
        if ($expectedPageSize < 512 || ($expectedPageSize & ($expectedPageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next190 page size must be a power of two at least 512');
        }
        if ($expectedCheckpointSequence < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next190 checkpoint sequence must be non-negative');
        }

        $databasePath = (string) $readerFence['database_path'];
        $walPath = (string) $readerFence['wal_path'];
        $journalPath = $databasePath . '-journal';
        $databaseBytes = $files[$databasePath] ?? null;
        $walBytes = $files[$walPath] ?? null;
        $journalBytes = $files[$journalPath] ?? null;
        $blocked = [];
        $wal = null;
        $walHeader = null;
        $walCommitFrames = [];

        $fenceReady = ($readerFence['status'] ?? '') === 'wal-hot-journal-savepoint-checkpoint-current-source-next187'
            && ($readerFence['can_admit_retry_checkpoint_source'] ?? false) === true
            && ($readerFence['requires_reader_reopen'] ?? true) === false
            && ($readerFence['post_apply_token_retired'] ?? false) === true
            && ($readerFence['hot_journal_observed'] ?? false) === true;
        if (!$fenceReady) {
            $blocked[] = 'next187_reader_token_fence_not_admitted';
        }

        if ($databaseBytes === null) {
            $blocked[] = 'database_file_missing_after_retry_checkpoint_publication';
        } elseif (!hash_equals(hash('sha256', $expectedDatabaseBytes), hash('sha256', $databaseBytes))) {
            $blocked[] = 'database_file_digest_drift_after_retry_checkpoint_publication';
        }

        if ($walBytes === null || $walBytes === '') {
            $blocked[] = 'wal_file_missing_after_retry_checkpoint_publication';
        } else {
            try {
                $wal = SQLiteWal::parse($walBytes, $expectedPageSize, true);
                $walHeader = $wal->header->toArray();
                foreach ($wal->frames as $frame) {
                    if ($frame->isCommitFrame()) {
                        $walCommitFrames[] = [
                            'frame_index' => $frame->index,
                            'page_number' => $frame->pageNumber,
                            'commit_page_count' => $frame->databasePageCountAfterCommit,
                        ];
                    }
                }
                if ($wal->header->checkpointSequence !== $expectedCheckpointSequence) {
                    $blocked[] = 'wal_checkpoint_sequence_drift_after_retry_checkpoint_publication';
                }
                if ($walCommitFrames === []) {
                    $blocked[] = 'wal_retry_checkpoint_publication_has_no_commit_frame';
                }
            } catch (\InvalidArgumentException $exception) {
                $blocked[] = 'wal_retry_checkpoint_publication_checksum_or_header_invalid';
                $walHeader = ['error' => $exception->getMessage()];
            }
        }

        if ($journalBytes !== null) {
            $blocked[] = 'hot_journal_file_must_be_absent_after_retry_checkpoint_publication';
        }

        $readerToken = (string) $readerFence['retry_reader_token'];
        $publicationToken = 'wal-hot-journal-savepoint-checkpoint-next190:publish:' . substr(hash('sha256', implode('|', [
            $readerToken,
            hash('sha256', (string) $databaseBytes),
            hash('sha256', (string) $walBytes),
            (string) $expectedCheckpointSequence,
            (string) $expectedPageSize,
        ])), 0, 32);

        if ($requireDirectorySync && !in_array('directory_sync_verified', $readerFence['dependencies'], true)) {
            $blocked[] = 'directory_sync_evidence_required_for_retry_checkpoint_publication';
        }

        $ready = $blocked === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next190'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next190',
            'reason' => $ready
                ? 'retry_checkpoint_publication_matches_reader_fence_and_current_files'
                : 'retry_checkpoint_publication_waits_for_current_source_file_map',
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'reader_retry_token' => $readerToken,
            'publication_token' => $publicationToken,
            'database_sha256' => $databaseBytes === null ? null : hash('sha256', $databaseBytes),
            'expected_database_sha256' => hash('sha256', $expectedDatabaseBytes),
            'wal_sha256' => $walBytes === null ? null : hash('sha256', $walBytes),
            'expected_wal_sha256' => $readerFence['next_wal_sha256'],
            'journal_present' => $journalBytes !== null,
            'wal_header' => $walHeader,
            'wal_byte_order' => $wal?->header->byteOrder(),
            'wal_checksums_validated' => $wal?->checksumsValidated ?? false,
            'wal_frame_count' => count($wal?->frames ?? []),
            'wal_commit_frames' => $walCommitFrames,
            'wal_commit_frame_count' => count($walCommitFrames),
            'last_commit_page_count' => $walCommitFrames[array_key_last($walCommitFrames)]['commit_page_count'] ?? null,
            'expected_checkpoint_sequence' => $expectedCheckpointSequence,
            'expected_page_size' => $expectedPageSize,
            'reader_page_numbers' => $readerFence['reader_page_numbers'],
            'reader_next_sources' => $readerFence['reader_next_sources'],
            'can_publish_retry_checkpoint_source' => $ready,
            'requires_directory_sync' => $requireDirectorySync,
            'blocked_reasons' => array_values(array_unique($blocked)),
            'dependencies' => array_values(array_unique(array_merge(
                $readerFence['dependencies'],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next190',
                    'sqlite-retry-checkpoint-publication-current-file-map',
                    'wordpress-import-retry-checkpoint-current-source-publication',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses accepted WAL parser/checksum validation plus next187 reader-token fencing',
            'non_overlap' => 'next190 verifies final retry-checkpoint file-map publication after the next187 reader-token fence; it does not repeat WAL byte truncation, rollback-journal apply, checkpoint transaction planning, reader-cache token fencing, or WAL header source parsing',
        ];
    }

    /**
     * @param array<string,mixed> $readerFence
     */
    private static function assertFence(array $readerFence): void
    {
        foreach (['database_path', 'wal_path', 'retry_reader_token', 'next_wal_sha256', 'reader_page_numbers', 'reader_next_sources', 'dependencies'] as $key) {
            if (!array_key_exists($key, $readerFence)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next190 missing reader fence {$key}");
            }
        }
        if (!is_string($readerFence['database_path']) || $readerFence['database_path'] === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next190 database path must be non-empty');
        }
        if (!is_string($readerFence['wal_path']) || $readerFence['wal_path'] === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next190 WAL path must be non-empty');
        }
        if (!is_string($readerFence['retry_reader_token']) || $readerFence['retry_reader_token'] === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next190 retry token must be non-empty');
        }
        if (!is_array($readerFence['reader_page_numbers']) || !is_array($readerFence['reader_next_sources']) || !is_array($readerFence['dependencies'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next190 reader fence arrays are malformed');
        }
    }

    /**
     * @param array<string,string|null> $files
     * @return array<string,string|null>
     */
    private static function normalizeFiles(array $files): array
    {
        ksort($files, SORT_STRING);
        foreach ($files as $path => $bytes) {
            if (!is_string($path) || $path === '') {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next190 file paths must be non-empty strings');
            }
            if ($bytes !== null && !is_string($bytes)) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next190 file bytes must be strings or null');
            }
        }

        return $files;
    }
}
