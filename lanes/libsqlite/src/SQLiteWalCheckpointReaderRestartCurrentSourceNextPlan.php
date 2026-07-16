<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan
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
        string $restartedWalBytes,
        array $pageNumbers,
        int $oldReaderEndFrame,
        int $restartedCurrentReaderEndFrame
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 requires a database path');
        }
        if ($currentWalBytes === '' || $restartedWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 requires WAL byte sources');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 requires database bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 requires page numbers');
        }
        if ($currentWal->toBytes() !== $currentWalBytes) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 current source bytes do not match parsed WAL');
        }

        $pageSize = $currentWal->header->pageSize;
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 database image must be page aligned');
        }
        if ($oldReaderEndFrame < 0 || $oldReaderEndFrame > $currentWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 old reader frame is outside the current WAL range');
        }
        if ($restartedCurrentReaderEndFrame < $oldReaderEndFrame || $restartedCurrentReaderEndFrame > $currentWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 restarted reader frame must stay on the current source and not move backwards');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 pages must be one-based integers');
            }
        }

        $checkpoint = $currentWal->durableCheckpointResult($databaseBytes, 'restart');
        if ($checkpoint['busy'] || $checkpoint['wal_action'] !== 'restart_wal') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 requires an unpinned RESTART checkpoint');
        }

        $restartedWal = SQLiteWal::parse($restartedWalBytes, $pageSize, true);
        self::assertRestartGeneration($currentWal, $restartedWal);
        $checkpointDatabaseBytes = (string) $checkpoint['database_bytes'];

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $oldReader = $currentWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $oldReaderEndFrame);
            $currentRestart = $currentWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $restartedCurrentReaderEndFrame);
            $checkpointPage = self::databasePage($checkpointDatabaseBytes, $pageSize, $pageNumber);
            $pathRestart = $restartedWal->readerSnapshotPageImage($checkpointDatabaseBytes, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'old_reader_source' => $oldReader['source'],
                'old_reader_frame' => $oldReader['frame_index'],
                'old_reader_label' => self::label((string) $oldReader['image']),
                'current_restart_source' => $currentRestart['source'],
                'current_restart_frame' => $currentRestart['frame_index'],
                'current_restart_label' => self::label((string) $currentRestart['image']),
                'checkpoint_source' => $checkpointPage['source'],
                'checkpoint_label' => self::label((string) $checkpointPage['image']),
                'path_restart_source' => $pathRestart['source'],
                'path_restart_frame' => $pathRestart['frame_index'],
                'path_restart_label' => self::label((string) $pathRestart['image']),
                'current_restart_uses_original_source' => $currentRestart['source'] === 'wal',
                'current_restart_moved_from_old_reader' => $oldReader['image'] !== $currentRestart['image'],
                'checkpoint_matches_current_restart' => $checkpointPage['image'] === $currentRestart['image'],
                'path_restart_separated_from_current_source' => $pathRestart['image'] !== $currentRestart['image'],
                'source_transition' => $oldReader['source'] . '>' . $currentRestart['source'] . '>' . $checkpointPage['source'] . '>' . $pathRestart['source'],
            ];
        }

        $advancedRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => (bool) $row['current_restart_moved_from_old_reader']
        ));
        $separatedRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => (bool) $row['path_restart_separated_from_current_source']
        ));

        return [
            'status' => 'wal-checkpoint-reader-restart-current-source-next140',
            'reason' => 'reader_restart_reuses_current_wal_source_after_path_restart_checkpoint',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'mode' => 'restart',
            'page_size' => $pageSize,
            'old_reader_end_frame' => $oldReaderEndFrame,
            'restarted_current_reader_end_frame' => $restartedCurrentReaderEndFrame,
            'current_checkpoint_sequence' => $currentWal->header->checkpointSequence,
            'path_restart_checkpoint_sequence' => $restartedWal->header->checkpointSequence,
            'current_frame_count' => $currentWal->frameCount(),
            'path_restart_frame_count' => $restartedWal->frameCount(),
            'checkpointed_frame_count' => $checkpoint['checkpointed_frame_count'],
            'checkpoint_wal_bytes_length' => strlen((string) $checkpoint['wal_bytes']),
            'current_wal_sha256' => hash('sha256', $currentWalBytes),
            'path_restart_wal_sha256' => hash('sha256', $restartedWalBytes),
            'checkpoint_database_sha256' => hash('sha256', $checkpointDatabaseBytes),
            'current_salt' => [$currentWal->header->salt1, $currentWal->header->salt2],
            'path_restart_salt' => [$restartedWal->header->salt1, $restartedWal->header->salt2],
            'old_reader_sources' => array_column($rows, 'old_reader_source'),
            'current_restart_sources' => array_column($rows, 'current_restart_source'),
            'checkpoint_sources' => array_column($rows, 'checkpoint_source'),
            'path_restart_sources' => array_column($rows, 'path_restart_source'),
            'old_reader_frame_indexes' => array_column($rows, 'old_reader_frame'),
            'current_restart_frame_indexes' => array_column($rows, 'current_restart_frame'),
            'path_restart_frame_indexes' => array_column($rows, 'path_restart_frame'),
            'current_restart_advanced_pages' => array_column($advancedRows, 'page_number'),
            'path_restart_separated_pages' => array_column($separatedRows, 'page_number'),
            'current_reader_restart_uses_original_source' => $advancedRows !== [],
            'fresh_path_reader_uses_restarted_generation' => $separatedRows !== [],
            'source_transitions' => array_column($rows, 'source_transition'),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'rows' => $rows,
            'operations' => [
                ['op' => 'checkpoint_database_write', 'path' => $databasePath, 'generation' => 'current-to-path-restart'],
                ['op' => 'replace_wal', 'path' => $databasePath . '-wal', 'generation' => 'path-restart'],
                ['op' => 'restart_reader_on_current_source', 'path' => $databasePath . '-wal', 'generation' => 'original-current-source'],
                ['op' => 'open_fresh_reader_on_path', 'path' => $databasePath . '-wal', 'generation' => 'path-restart'],
            ],
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                [
                    'sqlite-wal-checkpoint-reader-restart-current-source-next140',
                    'sqlite-wal-current-source-reader-restart',
                    'sqlite-wal-restart-generation-boundary',
                    'durable-sidecar-write',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses the native PHP WAL parser, durable checkpoint result, and reader snapshot page image helpers',
            'non_overlap' => 'avoids accepted next136 consecutive restart generations, next133 single path replacement, savepoint byte truncation, checkpoint transaction, and VFS writer/apply clusters by proving a restarted read transaction still bound to the original current WAL source while fresh path readers use the restarted generation',
        ];
    }

    private static function assertRestartGeneration(SQLiteWal $previous, SQLiteWal $next): void
    {
        if ($next->header->checkpointSequence <= $previous->header->checkpointSequence) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 path restart generation must advance the checkpoint sequence');
        }
        if ($next->header->salt1 === $previous->header->salt1 && $next->header->salt2 === $previous->header->salt2) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart current-source next140 path restart generation must use a distinct salt pair');
        }
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL checkpoint reader restart current-source next140 page {$pageNumber} is outside the checkpoint database");
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
