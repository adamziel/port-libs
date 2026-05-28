<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext189Plan
{
    /**
     * @param array<string,mixed> $applyResult
     * @param array<string,string|null> $files
     * @param list<int> $readerPages
     * @param list<string> $readerCacheTokens
     * @param array<int,string> $databasePages
     * @return array<string,mixed>
     */
    public static function readerSnapshotPlan(
        array $applyResult,
        array $files,
        int $expectedCheckpointSequence,
        int $expectedPageSize,
        int $readerEndFrame,
        array $readerPages,
        array $readerCacheTokens = [],
        array $databasePages = [],
        int $readerEpoch = 1
    ): array {
        if ($readerEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL current-source next189 reader end frame must be non-negative');
        }
        if ($readerPages === []) {
            throw new \InvalidArgumentException('SQLite WAL current-source next189 requires reader pages');
        }

        $source = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext186Plan::verifyWalSource(
            $applyResult,
            $files,
            $expectedCheckpointSequence,
            $expectedPageSize,
            $readerCacheTokens,
            $readerEpoch
        );

        $blocked = $source['blocked_reasons'] ?? [];
        $walPath = (string) ($applyResult['wal_path'] ?? '');
        $walBytes = $walPath === '' ? null : ($files[$walPath] ?? null);
        $wal = null;
        $lastCommitFrame = null;
        $commitPageCount = null;
        $uncommittedTailFrames = [];
        $readerRows = [];

        if (($source['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next186') {
            $blocked[] = 'next186_retained_wal_source_required';
        }

        if (!is_string($walBytes)) {
            $blocked[] = 'retained_wal_payload_missing_for_reader_snapshot';
        } else {
            try {
                $wal = SQLiteWal::parse($walBytes, $expectedPageSize, true);
                foreach ($wal->frames as $frame) {
                    if ($frame->isCommitFrame()) {
                        $lastCommitFrame = $frame->index;
                        $commitPageCount = $frame->databasePageCountAfterCommit;
                    }
                }
                foreach ($wal->frames as $frame) {
                    if ($lastCommitFrame !== null && $frame->index > $lastCommitFrame) {
                        $uncommittedTailFrames[] = $frame->index;
                    }
                }
                if ($lastCommitFrame === null) {
                    $blocked[] = 'reader_snapshot_has_no_committed_wal_frame';
                } elseif ($readerEndFrame > $lastCommitFrame) {
                    $blocked[] = 'reader_end_frame_extends_past_last_committed_frame';
                }
            } catch (\InvalidArgumentException) {
                $blocked[] = 'retained_wal_payload_unreadable_for_reader_snapshot';
            }
        }

        foreach ($readerPages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL current-source next189 reader pages must be one-based integers');
            }
            $frame = $wal === null ? null : self::lastFrameForPage($wal, $pageNumber, $readerEndFrame);
            $databaseImage = $databasePages[$pageNumber] ?? null;
            if ($databaseImage !== null && !is_string($databaseImage)) {
                throw new \InvalidArgumentException('SQLite WAL current-source next189 database page images must be strings');
            }
            $readerRows[] = [
                'page_number' => $pageNumber,
                'source' => $frame === null ? 'checkpoint-database' : 'retained-wal',
                'frame_index' => $frame?->index,
                'has_database_fallback' => $frame === null && $databaseImage !== null,
                'image_sha256' => $frame === null
                    ? ($databaseImage === null ? null : hash('sha256', $databaseImage))
                    : hash('sha256', $frame->pageImage),
            ];
        }

        $missingDatabaseFallbacks = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter(
                $readerRows,
                static fn (array $row): bool => $row['source'] === 'checkpoint-database' && $row['has_database_fallback'] === false
            )
        ));
        if ($missingDatabaseFallbacks !== []) {
            $blocked[] = 'checkpoint_database_fallback_missing_for_reader_page';
        }

        $blocked = array_values(array_unique($blocked));
        $status = $blocked === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next189'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next189';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next189'
                ? 'reader_snapshot_frames_are_bounded_by_retained_committed_wal_source'
                : 'reader_snapshot_current_source_blocked_after_savepoint_checkpoint',
            'database_path' => $applyResult['database_path'] ?? null,
            'journal_path' => $applyResult['journal_path'] ?? null,
            'wal_path' => $walPath,
            'reader_epoch' => $readerEpoch,
            'reader_end_frame' => $readerEndFrame,
            'last_commit_frame' => $lastCommitFrame,
            'last_commit_page_count' => $commitPageCount,
            'uncommitted_tail_frames' => $uncommittedTailFrames,
            'has_uncommitted_tail' => $uncommittedTailFrames !== [],
            'reader_rows' => $readerRows,
            'reader_page_numbers' => array_column($readerRows, 'page_number'),
            'reader_sources' => array_column($readerRows, 'source'),
            'reader_frame_indexes' => array_column($readerRows, 'frame_index'),
            'missing_database_fallback_pages' => $missingDatabaseFallbacks,
            'reader_source_token' => $source['reader_source_token'] ?? null,
            'base_status' => $source['status'] ?? null,
            'base_blocked_reasons' => $source['blocked_reasons'] ?? [],
            'blocked_reasons' => $blocked,
            'snapshot_digest' => hash('sha256', implode('|', array_map(
                static fn (array $row): string => implode(':', [
                    (string) $row['page_number'],
                    (string) $row['source'],
                    (string) ($row['frame_index'] ?? 'db'),
                    (string) ($row['image_sha256'] ?? 'missing'),
                ]),
                $readerRows
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($source['dependencies'] ?? null) ? $source['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next189',
                    'sqlite-wal-reader-snapshot-retained-commit-frame-admission',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native WAL parsing/checksum validation and next186 retained WAL current-source admission',
            'non_overlap' => 'next189 selects reader snapshot page sources bounded by the retained committed WAL frame after next186 source admission; it does not repeat WAL header token validation, checkpoint transaction planning, VFS writer/sync application, savepoint byte truncation, or rollback-journal apply',
        ];
    }

    private static function lastFrameForPage(SQLiteWal $wal, int $pageNumber, int $readerEndFrame): ?SQLiteWalFrame
    {
        $match = null;
        foreach ($wal->frames as $frame) {
            if ($frame->index > $readerEndFrame) {
                break;
            }
            if ($frame->pageNumber === $pageNumber) {
                $match = $frame;
            }
        }

        return $match;
    }
}
