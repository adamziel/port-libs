<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $databaseBytes,
        string $firstRestartWalBytes,
        string $secondRestartWalBytes,
        array $pageNumbers,
        int $currentReaderEndFrame
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint current-source next136 requires a database path');
        }
        if ($currentWalBytes === '' || $firstRestartWalBytes === '' || $secondRestartWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint current-source next136 requires all WAL byte sources');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint current-source next136 requires database bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint current-source next136 requires page numbers');
        }
        if ($currentWal->toBytes() !== $currentWalBytes) {
            throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint current-source next136 current source bytes do not match parsed WAL');
        }

        $pageSize = $currentWal->header->pageSize;
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint current-source next136 database image must be page aligned');
        }
        if ($currentReaderEndFrame < 0 || $currentReaderEndFrame > $currentWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint current-source next136 reader frame is outside the current WAL range');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint current-source next136 pages must be one-based integers');
            }
        }

        $firstCheckpoint = $currentWal->durableCheckpointResult($databaseBytes, 'restart');
        if ($firstCheckpoint['busy'] || $firstCheckpoint['wal_action'] !== 'restart_wal') {
            throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint current-source next136 requires an unpinned first RESTART checkpoint');
        }

        $firstRestartWal = SQLiteWal::parse($firstRestartWalBytes, $pageSize, true);
        self::assertNextGeneration($currentWal, $firstRestartWal, 'first');
        $firstCheckpointDatabaseBytes = (string) $firstCheckpoint['database_bytes'];

        $secondCheckpoint = $firstRestartWal->durableCheckpointResult($firstCheckpointDatabaseBytes, 'restart');
        if ($secondCheckpoint['busy'] || $secondCheckpoint['wal_action'] !== 'restart_wal') {
            throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint current-source next136 requires an unpinned second RESTART checkpoint');
        }

        $secondRestartWal = SQLiteWal::parse($secondRestartWalBytes, $pageSize, true);
        self::assertNextGeneration($firstRestartWal, $secondRestartWal, 'second');
        $secondCheckpointDatabaseBytes = (string) $secondCheckpoint['database_bytes'];

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $current = $currentWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $firstCheckpointPage = self::databasePage($firstCheckpointDatabaseBytes, $pageSize, $pageNumber);
            $firstRestart = $firstRestartWal->readerSnapshotPageImage($firstCheckpointDatabaseBytes, $pageNumber);
            $secondCheckpointPage = self::databasePage($secondCheckpointDatabaseBytes, $pageSize, $pageNumber);
            $secondRestart = $secondRestartWal->readerSnapshotPageImage($secondCheckpointDatabaseBytes, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'current_source' => $current['source'],
                'current_frame' => $current['frame_index'],
                'current_label' => self::label((string) $current['image']),
                'first_checkpoint_source' => $firstCheckpointPage['source'],
                'first_checkpoint_label' => self::label((string) $firstCheckpointPage['image']),
                'first_restart_source' => $firstRestart['source'],
                'first_restart_frame' => $firstRestart['frame_index'],
                'first_restart_label' => self::label((string) $firstRestart['image']),
                'second_checkpoint_source' => $secondCheckpointPage['source'],
                'second_checkpoint_label' => self::label((string) $secondCheckpointPage['image']),
                'second_restart_source' => $secondRestart['source'],
                'second_restart_frame' => $secondRestart['frame_index'],
                'second_restart_label' => self::label((string) $secondRestart['image']),
                'current_survives_first_restart' => $current['image'] !== $firstRestart['image'],
                'current_survives_second_restart' => $current['image'] !== $secondRestart['image'],
                'first_restart_checkpointed_before_second' => $firstRestart['image'] === $secondCheckpointPage['image'],
                'second_restart_matches_reopened_path' => $secondRestart['image'] !== $current['image'],
                'source_transition' => $current['source'] . '>' . $firstCheckpointPage['source'] . '>' . $firstRestart['source'] . '>' . $secondCheckpointPage['source'] . '>' . $secondRestart['source'],
            ];
        }

        $changedRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => (bool) $row['current_survives_second_restart']
        ));

        return [
            'status' => 'wal-reader-restart-checkpoint-current-source-next136',
            'reason' => 'current_reader_keeps_original_wal_source_across_consecutive_restart_checkpoints',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'mode' => 'restart-restart',
            'page_size' => $pageSize,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'current_checkpoint_sequence' => $currentWal->header->checkpointSequence,
            'first_restart_checkpoint_sequence' => $firstRestartWal->header->checkpointSequence,
            'second_restart_checkpoint_sequence' => $secondRestartWal->header->checkpointSequence,
            'current_frame_count' => $currentWal->frameCount(),
            'first_restart_frame_count' => $firstRestartWal->frameCount(),
            'second_restart_frame_count' => $secondRestartWal->frameCount(),
            'first_checkpointed_frame_count' => $firstCheckpoint['checkpointed_frame_count'],
            'second_checkpointed_frame_count' => $secondCheckpoint['checkpointed_frame_count'],
            'first_restart_header_bytes_length' => strlen((string) $firstCheckpoint['wal_bytes']),
            'second_restart_header_bytes_length' => strlen((string) $secondCheckpoint['wal_bytes']),
            'current_wal_sha256' => hash('sha256', $currentWalBytes),
            'first_restart_wal_sha256' => hash('sha256', $firstRestartWalBytes),
            'second_restart_wal_sha256' => hash('sha256', $secondRestartWalBytes),
            'first_checkpoint_database_sha256' => hash('sha256', $firstCheckpointDatabaseBytes),
            'second_checkpoint_database_sha256' => hash('sha256', $secondCheckpointDatabaseBytes),
            'current_salt' => [$currentWal->header->salt1, $currentWal->header->salt2],
            'first_restart_salt' => [$firstRestartWal->header->salt1, $firstRestartWal->header->salt2],
            'second_restart_salt' => [$secondRestartWal->header->salt1, $secondRestartWal->header->salt2],
            'current_reader_preserved_by_source_handle' => $changedRows !== [],
            'second_restart_replaced_wal_path' => true,
            'changed_page_numbers' => array_column($changedRows, 'page_number'),
            'current_sources' => array_column($rows, 'current_source'),
            'first_restart_sources' => array_column($rows, 'first_restart_source'),
            'second_restart_sources' => array_column($rows, 'second_restart_source'),
            'first_restart_frame_indexes' => array_column($rows, 'first_restart_frame'),
            'second_restart_frame_indexes' => array_column($rows, 'second_restart_frame'),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'operations' => [
                ['op' => 'checkpoint_database_write', 'path' => $databasePath, 'generation' => 'current-to-first-restart'],
                ['op' => 'replace_wal', 'path' => $databasePath . '-wal', 'generation' => 'first-restart'],
                ['op' => 'checkpoint_database_write', 'path' => $databasePath, 'generation' => 'first-to-second-restart'],
                ['op' => 'replace_wal', 'path' => $databasePath . '-wal', 'generation' => 'second-restart'],
                ['op' => 'keep_reader_source', 'path' => $databasePath . '-wal', 'generation' => 'original-current-reader'],
            ],
            'dependencies' => array_values(array_unique(array_merge(
                $firstCheckpoint['dependencies'],
                $secondCheckpoint['dependencies'],
                [
                    'sqlite-wal-reader-restart-checkpoint-current-source-next136',
                    'sqlite-wal-current-source-handle',
                    'sqlite-wal-restart-generation-boundary',
                    'durable-sidecar-write',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses the existing native PHP WAL parser, durable checkpoint result, and current-source reader snapshot helpers',
            'non_overlap' => 'avoids accepted next133 single restart replacement, next119 restart/truncate read-mark, checkpoint transaction, savepoint byte truncation, and VFS writer/apply clusters by proving consecutive restart generations against one pinned current reader source',
        ];
    }

    private static function assertNextGeneration(SQLiteWal $previous, SQLiteWal $next, string $label): void
    {
        if ($next->header->checkpointSequence <= $previous->header->checkpointSequence) {
            throw new \InvalidArgumentException("SQLite WAL reader restart checkpoint current-source next136 {$label} generation must advance the checkpoint sequence");
        }
        if ($next->header->salt1 === $previous->header->salt1 && $next->header->salt2 === $previous->header->salt2) {
            throw new \InvalidArgumentException("SQLite WAL reader restart checkpoint current-source next136 {$label} generation must use a distinct salt pair");
        }
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL reader restart checkpoint current-source next136 page {$pageNumber} is outside the checkpoint database");
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $pageCount,
        ];
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
