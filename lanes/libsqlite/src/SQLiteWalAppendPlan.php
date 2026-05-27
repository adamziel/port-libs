<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalAppendPlan
{
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
}
