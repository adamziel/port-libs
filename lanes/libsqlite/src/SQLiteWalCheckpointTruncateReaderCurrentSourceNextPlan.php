<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan
{
    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $nextTransactions
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        SQLiteWal $wal,
        string $walBytes,
        string $readerWalBytes,
        string $databaseBytes,
        string $databasePath,
        array $nextTransactions,
        array $pageNumbers,
        int $currentReaderEndFrame
    ): array {
        if ($walBytes === '' || $readerWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 requires WAL bytes');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 requires database bytes');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 requires a database path');
        }
        if ($nextTransactions === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 requires next transactions');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 requires page numbers');
        }
        if ($currentReaderEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 reader frame must be non-negative');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 source bytes do not match parsed WAL');
        }

        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 database image must be page aligned');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 pages must be one-based integers');
            }
        }

        $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
        if ($currentReaderEndFrame > $readerWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 reader frame exceeds reader WAL');
        }
        $readerSourceMatchesCurrent = hash_equals(hash('sha256', $walBytes), hash('sha256', $readerWalBytes));

        $pinned = $wal->durableCheckpointResult($databaseBytes, 'truncate', $currentReaderEndFrame);
        $released = $wal->durableCheckpointResult($databaseBytes, 'truncate', null);
        $nextWal = self::freshWalAfterTruncate($wal, $released);
        $append = SQLiteWalAppendPlan::appendTransactions($nextWal, $databasePath, $nextTransactions);
        $nextWalAfterAppend = SQLiteWal::parse((string) $append['wal_bytes'], $pageSize, true);

        $currentRows = [];
        $pinnedRows = [];
        $nextRows = [];
        foreach ($pageNumbers as $pageNumber) {
            $current = $readerWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $pinnedCurrentWal = $pinned['wal_bytes'] === ''
                ? null
                : SQLiteWal::parse((string) $pinned['wal_bytes'], $pageSize, true);
            $pinnedAfter = $pinnedCurrentWal === null
                ? self::databasePage((string) $pinned['database_bytes'], $pageSize, $pageNumber, 'checkpoint-database')
                : $pinnedCurrentWal->readerSnapshotPageImage((string) $pinned['database_bytes'], $pageNumber, $currentReaderEndFrame);
            $next = $nextWalAfterAppend->readerSnapshotPageImage((string) $released['database_bytes'], $pageNumber, $nextWalAfterAppend->frameCount());

            $currentRows[] = $current;
            $pinnedRows[] = $pinnedAfter;
            $nextRows[] = $next;
        }

        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $rows[] = [
                'page_number' => $pageNumber,
                'current_source' => $currentRows[$index]['source'],
                'pinned_after_checkpoint_source' => $pinnedRows[$index]['source'],
                'next_source' => $nextRows[$index]['source'],
                'current_frame' => $currentRows[$index]['frame_index'],
                'pinned_after_checkpoint_frame' => $pinnedRows[$index]['frame_index'],
                'next_frame' => $nextRows[$index]['frame_index'],
                'current_label' => self::label((string) $currentRows[$index]['image']),
                'pinned_after_checkpoint_label' => self::label((string) $pinnedRows[$index]['image']),
                'next_label' => self::label((string) $nextRows[$index]['image']),
                'current_preserved' => $currentRows[$index]['image'] === $pinnedRows[$index]['image'],
                'next_matches_current' => $nextRows[$index]['image'] === $currentRows[$index]['image'],
                'source_transition' => $currentRows[$index]['source'] . '>' . $pinnedRows[$index]['source'] . '>' . $nextRows[$index]['source'],
            ];
        }

        $currentSources = array_column($rows, 'current_source');
        $pinnedSources = array_column($rows, 'pinned_after_checkpoint_source');
        $nextSources = array_column($rows, 'next_source');

        $readerPinsReset = $pinned['reason'] === 'reader_blocks_wal_reset';

        return [
            'status' => $readerSourceMatchesCurrent && $readerPinsReset && $pinned['busy'] && !$released['busy'] && $released['wal_action'] === 'truncate_wal'
                ? 'wal-checkpoint-truncate-reader-current-source-next134'
                : 'wal-checkpoint-truncate-reader-current-source-blocked-next134',
            'reason' => $readerSourceMatchesCurrent
                ? 'current_reader_source_pins_truncate_until_released_next_source_starts_fresh_wal_generation'
                : 'reader_wal_source_mismatch_requires_reopen_before_truncate_checkpoint',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'reader_source_matches_current' => $readerSourceMatchesCurrent,
            'current_wal_sha256' => hash('sha256', $walBytes),
            'reader_wal_sha256' => hash('sha256', $readerWalBytes),
            'current_frame_count' => $wal->frameCount(),
            'pinned_checkpoint_busy' => $pinned['busy'],
            'pinned_checkpoint_reason' => $pinned['reason'],
            'pinned_wal_action' => $pinned['wal_action'],
            'pinned_wal_bytes_length' => strlen((string) $pinned['wal_bytes']),
            'released_checkpoint_busy' => $released['busy'],
            'released_checkpoint_reason' => $released['reason'],
            'released_wal_action' => $released['wal_action'],
            'released_wal_bytes_length' => strlen((string) $released['wal_bytes']),
            'released_database_sha256' => hash('sha256', (string) $released['database_bytes']),
            'fresh_wal_checkpoint_sequence' => $nextWal->header->checkpointSequence,
            'fresh_wal_salt' => [$nextWal->header->salt1, $nextWal->header->salt2],
            'next_append_start_frame' => $append['start_frame'],
            'next_append_end_frame' => $append['end_frame'],
            'next_append_frame_count' => $append['appended_frame_count'],
            'next_append_last_commit_frame' => $append['last_commit_frame'],
            'next_wal_bytes_length' => $append['wal_bytes_length'],
            'current_sources' => $currentSources,
            'pinned_after_checkpoint_sources' => $pinnedSources,
            'next_sources' => $nextSources,
            'current_source_counts' => array_count_values($currentSources),
            'pinned_after_checkpoint_source_counts' => array_count_values($pinnedSources),
            'next_source_counts' => array_count_values($nextSources),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'current_reader_preserved_by_pinned_checkpoint' => !in_array(false, array_column($rows, 'current_preserved'), true),
            'next_source_separated_from_current_reader' => in_array(false, array_column($rows, 'next_matches_current'), true),
            'reader_release_unblocked_truncate' => (bool) $pinned['busy'] && !(bool) $released['busy'],
            'current_reader_pins_reset' => $readerPinsReset,
            'truncate_removed_old_wal_sidecar' => $released['wal_action'] === 'truncate_wal' && $released['wal_bytes'] === '',
            'next_reader_uses_fresh_wal_generation' => in_array('wal', $nextSources, true) && $nextWal->frameCount() === 0,
            'append_operations' => $append['operations'],
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $pinned['dependencies'],
                $released['dependencies'],
                $append['dependencies'],
                [
                    'sqlite-wal-checkpoint-truncate-reader-current-source-next134',
                    'sqlite-wal-current-reader-source-pin',
                    'sqlite-wal-truncate-next-source-generation',
                ]
            ))),
        ];
    }

    /**
     * @param array<string,mixed> $released
     */
    private static function freshWalAfterTruncate(SQLiteWal $wal, array $released): SQLiteWal
    {
        if (($released['wal_action'] ?? null) !== 'truncate_wal' || ($released['wal_bytes'] ?? null) !== '') {
            throw new \RuntimeException('SQLite WAL checkpoint truncate reader current-source next134 requires a released truncate checkpoint');
        }

        $salt = $released['next_wal_header_salt'];
        $headerBytes = pack(
            'N*',
            $wal->header->magic,
            $wal->header->formatVersion,
            $wal->header->pageSize,
            ($wal->header->checkpointSequence + 1) & 0xffffffff,
            $salt[0],
            $salt[1]
        );
        $checksum = SQLiteWal::checksumPair($headerBytes, $wal->header->usesLittleEndianChecksums());

        return SQLiteWal::parse($headerBytes . pack('N*', $checksum[0], $checksum[1]), $wal->header->pageSize, true);
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber, string $source): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader current-source next134 database image must be page aligned');
        }
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber < 1 || $pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL checkpoint truncate reader current-source next134 page {$pageNumber} is outside the database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => $source,
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
