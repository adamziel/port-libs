<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan
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
        string $nextWalBytes,
        array $pageNumbers,
        int $currentReaderEndFrame
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 requires a database path');
        }
        if ($currentWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 requires current WAL bytes');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 requires database bytes');
        }
        if ($nextWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 requires restarted WAL bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 requires page numbers');
        }
        if ($currentWal->toBytes() !== $currentWalBytes) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 current source bytes do not match parsed WAL');
        }

        $pageSize = $currentWal->header->pageSize;
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 database image must be page aligned');
        }
        if ($currentReaderEndFrame < 0 || $currentReaderEndFrame > $currentWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 reader frame is outside the current WAL range');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 pages must be one-based integers');
            }
        }

        $checkpoint = $currentWal->durableCheckpointResult($databaseBytes, 'restart');
        if ($checkpoint['busy'] || $checkpoint['wal_action'] !== 'restart_wal') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 requires a released RESTART checkpoint');
        }

        $checkpointDatabaseBytes = (string) $checkpoint['database_bytes'];
        $nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
        if ($nextWal->header->checkpointSequence <= $currentWal->header->checkpointSequence) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 next WAL generation must advance the checkpoint sequence');
        }
        if ($nextWal->header->salt1 === $currentWal->header->salt1 && $nextWal->header->salt2 === $currentWal->header->salt2) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source next133 next WAL generation must use a distinct salt pair');
        }

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $current = $currentWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $pathReopen = $nextWal->readerSnapshotPageImage($checkpointDatabaseBytes, $pageNumber);
            $next = $nextWal->readerSnapshotPageImage($checkpointDatabaseBytes, $pageNumber);
            $checkpointed = self::databasePage($checkpointDatabaseBytes, $pageSize, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'current_source' => $current['source'],
                'current_frame' => $current['frame_index'],
                'current_label' => self::label((string) $current['image']),
                'path_reopen_source' => $pathReopen['source'],
                'path_reopen_frame' => $pathReopen['frame_index'],
                'path_reopen_label' => self::label((string) $pathReopen['image']),
                'next_source' => $next['source'],
                'next_frame' => $next['frame_index'],
                'next_label' => self::label((string) $next['image']),
                'checkpointed_source' => $checkpointed['source'],
                'checkpointed_label' => self::label((string) $checkpointed['image']),
                'current_would_change_if_path_reopened' => $current['image'] !== $pathReopen['image'],
                'current_matches_checkpoint_database' => $current['image'] === $checkpointed['image'],
                'next_matches_restarted_path' => $next['image'] === $pathReopen['image'],
                'source_transition' => $current['source'] . '>' . $checkpointed['source'] . '>' . $pathReopen['source'],
            ];
        }

        $changedRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => (bool) $row['current_would_change_if_path_reopened']
        ));

        return [
            'status' => 'wal-reader-checkpoint-restart-current-source-next133',
            'reason' => 'current_reader_keeps_original_wal_source_after_restart_replaces_wal_path',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'mode' => 'restart',
            'page_size' => $pageSize,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'current_checkpoint_sequence' => $currentWal->header->checkpointSequence,
            'next_checkpoint_sequence' => $nextWal->header->checkpointSequence,
            'current_frame_count' => $currentWal->frameCount(),
            'next_frame_count' => $nextWal->frameCount(),
            'checkpointed_frame_count' => $checkpoint['checkpointed_frame_count'],
            'restart_wal_header_bytes_length' => strlen((string) $checkpoint['wal_bytes']),
            'current_wal_bytes_length' => strlen($currentWalBytes),
            'next_wal_bytes_length' => strlen($nextWalBytes),
            'current_wal_sha256' => hash('sha256', $currentWalBytes),
            'next_wal_sha256' => hash('sha256', $nextWalBytes),
            'checkpoint_database_sha256' => hash('sha256', $checkpointDatabaseBytes),
            'current_salt' => [$currentWal->header->salt1, $currentWal->header->salt2],
            'next_salt' => [$nextWal->header->salt1, $nextWal->header->salt2],
            'restart_replaced_wal_path' => true,
            'current_source_handle_is_distinct_from_path' => $currentWalBytes !== $nextWalBytes,
            'current_reader_preserved_by_source_handle' => true,
            'path_reopen_would_change_current_reader' => $changedRows !== [],
            'changed_page_numbers' => array_column($changedRows, 'page_number'),
            'current_sources' => array_column($rows, 'current_source'),
            'checkpointed_sources' => array_column($rows, 'checkpointed_source'),
            'path_reopen_sources' => array_column($rows, 'path_reopen_source'),
            'next_sources' => array_column($rows, 'next_source'),
            'current_frame_indexes' => array_column($rows, 'current_frame'),
            'path_reopen_frame_indexes' => array_column($rows, 'path_reopen_frame'),
            'next_frame_indexes' => array_column($rows, 'next_frame'),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'operations' => [
                ['op' => 'checkpoint_database_write', 'path' => $databasePath, 'reason' => 'restart_checkpoint_committed_current_wal_frames'],
                ['op' => 'replace_wal', 'path' => $databasePath . '-wal', 'reason' => 'restart_checkpoint_installs_new_generation'],
                ['op' => 'keep_reader_source', 'path' => $databasePath . '-wal', 'reason' => 'current_reader_uses_original_wal_handle_not_reopened_path'],
            ],
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                [
                    'sqlite-wal-reader-checkpoint-restart-current-source-next133',
                    'sqlite-wal-current-source-handle',
                    'sqlite-wal-restart-generation-boundary',
                ]
            ))),
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL reader checkpoint restart current-source next133 page {$pageNumber} is outside the checkpoint database");
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
