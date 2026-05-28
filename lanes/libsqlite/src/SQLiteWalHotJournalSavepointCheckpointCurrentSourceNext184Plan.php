<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext184Plan
{
    /**
     * @param array<string,mixed> $reopen
     * @param list<int> $readerPages
     * @return array<string,mixed>
     */
    public static function plan(
        array $reopen,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        SQLiteWal $nextWal,
        string $nextWalBytes,
        array $readerPages
    ): array {
        self::assertReopen($reopen);
        if ($readerPages === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next184 requires reader pages');
        }
        if (!hash_equals(self::walPrefix($currentWal), $currentWalBytes)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next184 current WAL bytes do not match parsed WAL');
        }
        if (!hash_equals(self::walPrefix($nextWal), $nextWalBytes)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next184 next WAL bytes do not match parsed WAL');
        }

        $currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];
        $nextSalt = [$nextWal->header->salt1, $nextWal->header->salt2];
        $saltRotated = $currentSalt !== $nextSalt;
        $checkpointAdvanced = $nextWal->header->checkpointSequence > $currentWal->header->checkpointSequence;
        $nextReopened = ($reopen['can_reopen_publish'] ?? false) === true
            && ($reopen['wal_checksums_validated'] ?? false) === true
            && (int) ($reopen['wal_checkpoint_sequence'] ?? -1) === $nextWal->header->checkpointSequence
            && (int) ($reopen['wal_frame_count'] ?? -1) === count($nextWal->frames);
        $currentValidated = $currentWal->checksumsValidated;
        $nextValidated = $nextWal->checksumsValidated;

        $readerRows = [];
        foreach ($readerPages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next184 reader pages must be one-based integers');
            }
            $currentFrame = self::lastFrameForPage($currentWal, $pageNumber);
            $nextFrame = self::lastFrameForPage($nextWal, $pageNumber);
            $readerRows[] = [
                'page_number' => $pageNumber,
                'current_frame' => $currentFrame?->index,
                'next_frame' => $nextFrame?->index,
                'current_source' => $currentFrame === null ? 'database' : 'current-wal',
                'next_source' => $nextFrame === null ? 'checkpoint-database' : 'next-wal',
                'source_separated' => $saltRotated && ($currentFrame?->index !== $nextFrame?->index || $currentFrame?->pageImage !== $nextFrame?->pageImage),
            ];
        }

        $allReadersSeparated = !in_array(false, array_column($readerRows, 'source_separated'), true);
        $ready = $nextReopened && $currentValidated && $nextValidated && $saltRotated && $checkpointAdvanced && $allReadersSeparated;
        $blocked = [];
        if (!$nextReopened) {
            $blocked[] = 'next181_reopen_not_publishable_for_next_wal_source';
        }
        if (!$currentValidated) {
            $blocked[] = 'current_wal_checksums_not_validated';
        }
        if (!$nextValidated) {
            $blocked[] = 'next_wal_checksums_not_validated';
        }
        if (!$saltRotated) {
            $blocked[] = 'next_wal_salt_pair_not_rotated';
        }
        if (!$checkpointAdvanced) {
            $blocked[] = 'next_wal_checkpoint_sequence_not_advanced';
        }
        if (!$allReadersSeparated) {
            $blocked[] = 'reader_pages_not_separated_from_current_wal_source';
        }

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next184'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next184',
            'reason' => $ready
                ? 'reopened_next_wal_source_is_distinct_before_reader_marks_are_reused'
                : 'reopened_next_wal_source_cannot_reuse_reader_marks',
            'database_path' => (string) $reopen['database_path'],
            'wal_path' => (string) $reopen['wal_path'],
            'can_reuse_reader_marks' => $ready,
            'current_checkpoint_sequence' => $currentWal->header->checkpointSequence,
            'next_checkpoint_sequence' => $nextWal->header->checkpointSequence,
            'checkpoint_sequence_advanced' => $checkpointAdvanced,
            'current_salt_pair' => $currentSalt,
            'next_salt_pair' => $nextSalt,
            'salt_pair_rotated' => $saltRotated,
            'current_wal_sha256' => hash('sha256', $currentWalBytes),
            'next_wal_sha256' => hash('sha256', $nextWalBytes),
            'current_frame_count' => count($currentWal->frames),
            'next_frame_count' => count($nextWal->frames),
            'current_commit_frame' => $currentWal->lastCommitFrame()?->index,
            'next_commit_frame' => $nextWal->lastCommitFrame()?->index,
            'reader_rows' => $readerRows,
            'reader_page_numbers' => array_column($readerRows, 'page_number'),
            'reader_current_sources' => array_column($readerRows, 'current_source'),
            'reader_next_sources' => array_column($readerRows, 'next_source'),
            'all_reader_pages_separated' => $allReadersSeparated,
            'blocked_reasons' => $blocked,
            'source_transition_digest' => hash('sha256', implode('|', [
                (string) ($reopen['reopen_digest'] ?? ''),
                hash('sha256', $currentWalBytes),
                hash('sha256', $nextWalBytes),
                implode(',', $currentSalt),
                implode(',', $nextSalt),
                implode(',', array_map('strval', $readerPages)),
            ])),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($reopen['dependencies'] ?? null) ? $reopen['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next184',
                    'sqlite-wal-reader-mark-source-separation-after-reopen',
                    'wordpress-import-retry-wal-salt-checkpoint-fence',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native WAL checksum parsing and next181 reopen admission evidence',
            'non_overlap' => 'adds post-reopen WAL salt/checkpoint source-separation before reader marks can be reused; does not repeat next178 receipt matching, next181 reopen validation, VFS writer/sync application, rollback-journal apply, or savepoint byte truncation',
        ];
    }

    /**
     * @param array<string,mixed> $reopen
     */
    private static function assertReopen(array $reopen): void
    {
        foreach (['database_path', 'wal_path', 'can_reopen_publish', 'wal_checkpoint_sequence', 'wal_frame_count'] as $key) {
            if (!array_key_exists($key, $reopen)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next184 missing reopen {$key}");
            }
        }
    }

    private static function walPrefix(SQLiteWal $wal): string
    {
        $pageSize = $wal->header->pageSize;
        $bytes = pack(
            'N*',
            $wal->header->magic,
            $wal->header->formatVersion,
            $pageSize,
            $wal->header->checkpointSequence,
            $wal->header->salt1,
            $wal->header->salt2,
            $wal->header->checksum1,
            $wal->header->checksum2
        );
        foreach ($wal->frames as $frame) {
            $bytes .= pack(
                'N*',
                $frame->pageNumber,
                $frame->databasePageCountAfterCommit,
                $frame->salt1,
                $frame->salt2,
                $frame->checksum1,
                $frame->checksum2
            ) . $frame->pageImage;
        }

        return $bytes;
    }

    private static function lastFrameForPage(SQLiteWal $wal, int $pageNumber): ?SQLiteWalFrame
    {
        $match = null;
        foreach ($wal->frames as $frame) {
            if ($frame->pageNumber === $pageNumber) {
                $match = $frame;
            }
        }

        return $match;
    }
}
