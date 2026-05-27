<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalAppendPlan
{
    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,mode:string,database_path:string,wal_path:string,checkpoint:array<string,mixed>,append:array<string,mixed>,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_stable_after_checkpoint:bool,next_uses_checkpoint_database:bool,next_uses_appended_wal:bool,operations:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function checkpointAppendCurrentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint append current/next requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint append current/next requires restart or truncate mode');
        }

        $checkpoint = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        if ($checkpoint['busy']) {
            return [
                'status' => 'busy',
                'reason' => $checkpoint['reason'],
                'mode' => $mode,
                'database_path' => $databasePath,
                'wal_path' => $databasePath . '-wal',
                'checkpoint' => $checkpoint,
                'append' => [],
                'current_reader_end_frame' => $readerEndFrame ?? $wal->frameCount(),
                'next_reader_end_frame' => 0,
                'current_reader' => [],
                'next_reader' => [],
                'current_reader_sources' => [],
                'next_reader_sources' => [],
                'current_reader_frame_indexes' => [],
                'next_reader_frame_indexes' => [],
                'current_reader_errors' => [],
                'next_reader_errors' => [],
                'current_stable_after_checkpoint' => false,
                'next_uses_checkpoint_database' => false,
                'next_uses_appended_wal' => false,
                'operations' => [],
                'dependencies' => array_values(array_unique(array_merge($checkpoint['dependencies'], ['sqlite-wal-checkpoint-append-current-next']))),
            ];
        }

        $checkpointWal = self::walAfterCheckpoint($wal, $checkpoint);
        $append = self::appendTransactions($checkpointWal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $currentEndFrame = $readerEndFrame ?? $wal->frameCount();
        $nextEndFrame = $nextWal->frameCount();

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint append current/next pages must be integers');
            }
            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame);
            $next[] = self::safeReaderVisibility($nextWal, $checkpoint['database_bytes'], $pageNumber, $nextEndFrame);
        }

        return [
            'status' => 'planned',
            'reason' => 'checkpoint_then_append_current_next_visibility',
            'mode' => $mode,
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'checkpoint' => $checkpoint,
            'append' => $append,
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_stable_after_checkpoint' => true,
            'next_uses_checkpoint_database' => $checkpoint['database_bytes'] !== $databaseBytes,
            'next_uses_appended_wal' => $nextWal->frameCount() > 0,
            'operations' => array_values(array_merge($append['operations'])),
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                $append['dependencies'],
                ['sqlite-wal-checkpoint-append-current-next']
            ))),
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @return array{status:string,reason:string,database_path:string,wal_path:string,start_offset:int,append_bytes:string,append_bytes_length:int,wal_bytes:string,wal_bytes_length:int,start_frame:int,end_frame:int,appended_frame_count:int,committed_transaction_count:int,uncommitted_transaction_count:int,last_commit_frame:int|null,last_database_page_count:int|null,frames:list<array{frame_index:int,page_number:int,commit:int,checksum1:int,checksum2:int,transaction:int,committed:bool}>,operations:list<array{op:string,path:string,offset?:int,bytes?:int,durable?:bool,reason?:string}>,dependencies:list<string>}
     */
    public static function appendTransactions(
        SQLiteWal $wal,
        string $databasePath,
        array $transactions,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL append plan requires a database path');
        }
        if ($transactions === []) {
            throw new \InvalidArgumentException('SQLite WAL append plan requires at least one transaction');
        }

        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL append plan requires a concrete WAL page size');
        }

        $walBytes = $wal->toBytes();
        $appendBytes = '';
        $frames = [];
        $checksum = self::checksumSeed($wal);
        $frameIndex = $wal->frameCount();
        $committed = 0;
        $uncommitted = 0;
        $lastDatabasePageCount = $wal->lastCommitFrame()?->databasePageCountAfterCommit;

        foreach (array_values($transactions) as $transactionIndex => $transaction) {
            $pages = $transaction['pages'] ?? null;
            if (!is_array($pages) || $pages === []) {
                throw new \InvalidArgumentException('SQLite WAL append transaction requires at least one page image');
            }

            $commit = (bool) ($transaction['commit'] ?? true);
            $databasePageCount = $transaction['database_page_count'] ?? null;
            if ($commit) {
                if (!is_int($databasePageCount) || $databasePageCount < 1) {
                    throw new \InvalidArgumentException('SQLite WAL committed append transaction requires a database page count');
                }
                if ($databasePageCount < max(array_keys($pages))) {
                    throw new \InvalidArgumentException('SQLite WAL committed append database page count cannot shrink below written pages');
                }
                $committed++;
                $lastDatabasePageCount = $databasePageCount;
            } else {
                $databasePageCount = 0;
                $uncommitted++;
            }

            foreach ($pages as $pageNumber => $pageImage) {
                if (!is_int($pageNumber) || $pageNumber < 1) {
                    throw new \InvalidArgumentException('SQLite WAL append page numbers must be one-based integers');
                }
                if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                    throw new \InvalidArgumentException("SQLite WAL append page {$pageNumber} must match the WAL page size");
                }

                $frameIndex++;
                $isCommitFrame = $commit && $pageNumber === array_key_last($pages);
                $commitPageCount = $isCommitFrame ? $databasePageCount : 0;
                $framePrefix = pack('N*', $pageNumber, $commitPageCount, $wal->header->salt1, $wal->header->salt2);
                $checksum = SQLiteWal::checksumPair(
                    substr($framePrefix, 0, 8) . $pageImage,
                    $wal->header->usesLittleEndianChecksums(),
                    $checksum[0],
                    $checksum[1]
                );
                $frameBytes = $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $pageImage;
                $appendBytes .= $frameBytes;
                $walBytes .= $frameBytes;
                $frames[] = [
                    'frame_index' => $frameIndex,
                    'page_number' => $pageNumber,
                    'commit' => $commitPageCount,
                    'checksum1' => $checksum[0],
                    'checksum2' => $checksum[1],
                    'transaction' => $transactionIndex,
                    'committed' => $isCommitFrame,
                ];
            }
        }

        $walPath = $databasePath . '-wal';
        $operations = [[
            'op' => 'write',
            'path' => $walPath,
            'offset' => strlen($wal->toBytes()),
            'bytes' => strlen($appendBytes),
            'durable' => false,
            'reason' => 'append_wal_transaction_frames',
        ]];
        if ($syncWal) {
            $operations[] = [
                'op' => 'sync',
                'path' => $walPath,
                'durable' => true,
                'reason' => 'sync_appended_wal_frames',
            ];
        }
        if ($syncDirectory) {
            $operations[] = [
                'op' => 'sync_directory',
                'path' => dirname($databasePath),
                'durable' => true,
                'reason' => 'persist_appended_wal_sidecar',
            ];
        }

        return [
            'status' => 'planned',
            'reason' => $committed > 0 ? 'wal_append_contains_commit_frame' : 'wal_append_uncommitted_tail',
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'start_offset' => strlen($wal->toBytes()),
            'append_bytes' => $appendBytes,
            'append_bytes_length' => strlen($appendBytes),
            'wal_bytes' => $walBytes,
            'wal_bytes_length' => strlen($walBytes),
            'start_frame' => $wal->frameCount() + 1,
            'end_frame' => $frameIndex,
            'appended_frame_count' => count($frames),
            'committed_transaction_count' => $committed,
            'uncommitted_transaction_count' => $uncommitted,
            'last_commit_frame' => $committed > 0 ? self::lastCommittedFrameIndex($frames) : $wal->lastCommitFrame()?->index,
            'last_database_page_count' => $lastDatabasePageCount,
            'frames' => $frames,
            'operations' => $operations,
            'dependencies' => ['sqlite-wal-append-transaction', 'sqlite-wal-frame-checksum-chain', 'vfs-file-write-coordination'],
        ];
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function checksumSeed(SQLiteWal $wal): array
    {
        if ($wal->frames === []) {
            return [$wal->header->checksum1, $wal->header->checksum2];
        }

        $last = $wal->frames[array_key_last($wal->frames)];

        return [$last->checksum1, $last->checksum2];
    }

    /**
     * @param list<array{frame_index:int,committed:bool}> $frames
     */
    private static function lastCommittedFrameIndex(array $frames): ?int
    {
        for ($index = count($frames) - 1; $index >= 0; $index--) {
            if ($frames[$index]['committed']) {
                return $frames[$index]['frame_index'];
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $checkpoint
     */
    private static function walAfterCheckpoint(SQLiteWal $wal, array $checkpoint): SQLiteWal
    {
        $walBytes = (string) $checkpoint['wal_bytes'];
        if ($walBytes !== '') {
            return SQLiteWal::parse($walBytes, $wal->header->pageSize, true);
        }

        /** @var array{0:int,1:int} $salt */
        $salt = $checkpoint['next_wal_header_salt'];
        $headerBytes = pack(
            'N*',
            $wal->header->magic,
            $wal->header->formatVersion,
            $wal->header->pageSize,
            ($wal->header->checkpointSequence + 1) & 0xffffffff,
            $salt[0],
            $salt[1],
        );
        $checksum = SQLiteWal::checksumPair($headerBytes, $wal->header->usesLittleEndianChecksums());

        return SQLiteWal::parse($headerBytes . pack('N*', $checksum[0], $checksum[1]), $wal->header->pageSize, true);
    }

    /**
     * @return array<string,mixed>
     */
    private static function safeReaderVisibility(SQLiteWal $wal, string $databaseBytes, int $pageNumber, ?int $snapshotEndFrame): array
    {
        try {
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $snapshotEndFrame);
        } catch (\OutOfBoundsException $e) {
            return [
                'page_number' => $pageNumber,
                'source' => 'missing',
                'frame_index' => null,
                'database_offset' => null,
                'image' => null,
                'snapshot_end_frame' => $snapshotEndFrame ?? $wal->frameCount(),
                'snapshot_commit_frame' => ($wal->readerSnapshot($databaseBytes, $snapshotEndFrame)['commit_frame'])?->index,
                'database_page_count' => $wal->readerSnapshot($databaseBytes, $snapshotEndFrame)['database_page_count'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function visibilityColumn(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function visibilityErrors(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            if (isset($row['error'])) {
                $errors[] = (string) $row['error'];
            }
        }

        return $errors;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string|null>
     */
    private static function visibilityImages(array $rows): array
    {
        return array_map(static fn (array $row): ?string => isset($row['image']) && is_string($row['image']) ? $row['image'] : null, $rows);
    }
}
