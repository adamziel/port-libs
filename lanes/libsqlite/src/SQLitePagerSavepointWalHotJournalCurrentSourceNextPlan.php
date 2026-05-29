<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $currentSourcePages
     * @param array<int,string> $currentSavepointWrites
     * @param array<int,string> $nextSavepointWrites
     * @param list<int> $readerPageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $savepoint,
        array $hotJournalPages,
        array $currentSourcePages,
        array $currentSavepointWrites,
        array $nextSavepointWrites,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        SQLiteWal $nextWal,
        string $nextWalBytes,
        array $readerPageNumbers,
        int $readerEndFrame,
        int $currentSourceEpoch = 1,
        bool $reservedLock = false,
        bool $superJournalRequired = false,
        bool $superJournalExists = false,
    ): array {
        if ($currentWalBytes === '' || $nextWalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL hot-journal current-source next148 requires current and next WAL bytes');
        }
        if ($pageSize !== $currentWal->header->pageSize || $pageSize !== $nextWal->header->pageSize) {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL hot-journal current-source next148 WAL page size must match pager page size');
        }
        if ($currentWalBytes !== self::walBytesPrefix($currentWalBytes, $currentWal)) {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL hot-journal current-source next148 current WAL bytes do not match parsed WAL');
        }
        if ($nextWalBytes !== self::walBytesPrefix($nextWalBytes, $nextWal)) {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL hot-journal current-source next148 next WAL bytes do not match parsed WAL');
        }
        if ($readerPageNumbers === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL hot-journal current-source next148 requires reader pages');
        }
        if ($readerEndFrame < 0 || $readerEndFrame > $currentWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL hot-journal current-source next148 reader frame is outside the current WAL range');
        }
        if ($nextWal->header->checkpointSequence <= $currentWal->header->checkpointSequence) {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL hot-journal current-source next148 next WAL checkpoint sequence must advance');
        }
        if ($nextWal->header->salt1 === $currentWal->header->salt1 && $nextWal->header->salt2 === $currentWal->header->salt2) {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL hot-journal current-source next148 next WAL salt pair must change');
        }

        $base = SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan(
            $databasePath,
            $databaseBytes,
            $pageSize,
            $savepoint,
            $hotJournalPages,
            $currentSourcePages,
            $currentSavepointWrites,
            $nextSavepointWrites,
            $currentSourceEpoch,
            $reservedLock,
            $superJournalRequired,
            $superJournalExists,
        );

        $hotDatabaseBytes = (string) ($base['payloads'][$databasePath . '#hot-journal'] ?? $databaseBytes);
        $rolledBackDatabaseBytes = (string) $base['rolled_back_database_bytes'];
        $rows = [];
        foreach ($readerPageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager savepoint WAL hot-journal current-source next148 reader pages must be one-based integers');
            }

            $current = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $readerEndFrame);
            $retry = $currentWal->readerSnapshotPageImage($rolledBackDatabaseBytes, $pageNumber, $readerEndFrame);
            $next = $nextWal->readerSnapshotPageImage($rolledBackDatabaseBytes, $pageNumber, $nextWal->frameCount());
            $currentLabel = self::label((string) $current['image']);
            $retryLabel = self::label((string) $retry['image']);
            $nextLabel = self::label((string) $next['image']);
            $rows[] = [
                'page_number' => $pageNumber,
                'hot_database_label' => self::databaseLabel($hotDatabaseBytes, $pageSize, $pageNumber),
                'rolled_back_label' => self::databaseLabel($rolledBackDatabaseBytes, $pageSize, $pageNumber),
                'current_source' => $current['source'],
                'current_frame' => $current['frame_index'],
                'current_label' => $currentLabel,
                'retry_source' => $retry['source'],
                'retry_frame' => $retry['frame_index'],
                'retry_label' => $retryLabel,
                'next_source' => $next['source'],
                'next_frame' => $next['frame_index'],
                'next_label' => $nextLabel,
                'retry_matches_current_reader' => $retryLabel === $currentLabel && $retry['source'] === $current['source'],
                'next_separated_from_retry' => $nextLabel !== $retryLabel || $next['source'] !== $retry['source'],
                'source_transition' => $current['source'] . '>savepoint-retry>' . $retry['source'] . '>next-wal>' . $next['source'],
            ];
        }

        $retryMatches = !in_array(false, array_column($rows, 'retry_matches_current_reader'), true);
        $separatedRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => (bool) $row['next_separated_from_retry']
        ));
        $nextSourceSeparated = hash('sha256', $currentWalBytes) !== hash('sha256', $nextWalBytes)
            && ($nextWal->header->checkpointSequence > $currentWal->header->checkpointSequence);
        $status = (bool) $base['hot_recovered']
            && $retryMatches
            && $nextSourceSeparated
            && $separatedRows !== []
            ? 'pager-savepoint-wal-hot-journal-current-source-next148'
            : 'pager-savepoint-wal-hot-journal-current-source-blocked-next148';

        return [
            'status' => $status,
            'reason' => $status === 'pager-savepoint-wal-hot-journal-current-source-next148'
                ? 'hot_journal_recovered_before_savepoint_retry_current_wal_reader_pinned_next_wal_separated'
                : 'savepoint_wal_hot_journal_current_source_not_separated',
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'reader_end_frame' => $readerEndFrame,
            'hot_recovered' => (bool) $base['hot_recovered'],
            'retry_matches_current_reader' => $retryMatches,
            'next_source_separated' => $nextSourceSeparated,
            'current_wal_source' => self::walSource($currentWal, $currentWalBytes),
            'next_wal_source' => self::walSource($nextWal, $nextWalBytes),
            'current_sources' => array_column($rows, 'current_source'),
            'retry_sources' => array_column($rows, 'retry_source'),
            'next_sources' => array_column($rows, 'next_source'),
            'current_frame_indexes' => array_column($rows, 'current_frame'),
            'retry_frame_indexes' => array_column($rows, 'retry_frame'),
            'next_frame_indexes' => array_column($rows, 'next_frame'),
            'current_labels' => array_column($rows, 'current_label'),
            'retry_labels' => array_column($rows, 'retry_label'),
            'next_labels' => array_column($rows, 'next_label'),
            'next_separated_page_numbers' => array_column($separatedRows, 'page_number'),
            'next_separated_page_count' => count($separatedRows),
            'source_transitions' => array_column($rows, 'source_transition'),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'base_status' => $base['status'],
            'base_operations' => array_column($base['operations'], 'op'),
            'base_payload_keys' => array_keys($base['payloads']),
            'rows' => $rows,
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'],
                [
                    'sqlite-pager-savepoint-wal-hot-journal-current-source-next148',
                    'sqlite-wal-reader-snapshot',
                    'sqlite-wal-generation-separation',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native PHP hot-journal recovery, savepoint rollback page images, WAL parsing, and reader snapshot helpers',
            'non_overlap' => 'avoids accepted next88 hot-journal savepoint retry, next143 WAL reader restart, and next142 master-journal savepoint slices by proving the current WAL reader remains pinned across savepoint retry while the next savepoint writes move to a distinct WAL source',
        ];
    }

    private static function walBytesPrefix(string $walBytes, SQLiteWal $wal): string
    {
        $frameSize = 24 + $wal->header->pageSize;
        return substr($walBytes, 0, 32 + ($wal->frameCount() * $frameSize));
    }

    /**
     * @return array{checkpoint_sequence:int,salt_1:int,salt_2:int,frame_count:int,sha256:string}
     */
    private static function walSource(SQLiteWal $wal, string $walBytes): array
    {
        return [
            'checkpoint_sequence' => $wal->header->checkpointSequence,
            'salt_1' => $wal->header->salt1,
            'salt_2' => $wal->header->salt2,
            'frame_count' => $wal->frameCount(),
            'sha256' => hash('sha256', $walBytes),
        ];
    }

    private static function databaseLabel(string $databaseBytes, int $pageSize, int $pageNumber): string
    {
        return self::label(substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize));
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
