<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalMultiTransactionClusterPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,transaction_count:int,frame_count:int,uncommitted_tail_frame_count:int,database_page_count_before:int,database_page_count_after:int,clusters:list<array<string,mixed>>,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_images:list<string>,next_reader_images:list<string>,images_match:bool,dependencies:list<string>}
     */
    public static function currentNext(SQLiteWal $wal, string $databaseBytes, array $pageNumbers, ?int $currentReaderEndFrame = null): array
    {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL multi-transaction cluster requires at least one page number');
        }

        $pageSize = self::pageSize($wal, $databaseBytes);
        $databasePageCountBefore = intdiv(strlen($databaseBytes), $pageSize);
        $transactions = $wal->committedTransactions();
        $lastCommitFrame = $wal->lastCommitFrame();
        $currentReaderEndFrame ??= $lastCommitFrame?->index ?? $wal->frameCount();

        if ($currentReaderEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL multi-transaction current reader frame must be non-negative');
        }

        $clusters = [];
        $currentDatabaseBytes = $databaseBytes;
        $previousCommitFrame = 0;
        foreach ($transactions as $ordinal => $transaction) {
            $nextDatabaseBytes = self::checkpointThroughFrame($wal, $currentDatabaseBytes, (int) $transaction['last_frame']);
            $clusters[] = self::clusterSummary(
                $wal,
                $currentDatabaseBytes,
                $nextDatabaseBytes,
                $pageNumbers,
                $ordinal + 1,
                $previousCommitFrame,
                $transaction,
            );
            $currentDatabaseBytes = $nextDatabaseBytes;
            $previousCommitFrame = (int) $transaction['last_frame'];
        }

        $checkpointDatabaseBytes = $lastCommitFrame === null
            ? $databaseBytes
            : $wal->checkpointDatabaseImage($databaseBytes);
        $databasePageCountAfter = intdiv(strlen($checkpointDatabaseBytes), $pageSize);

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL multi-transaction pages must be integers');
            }
            $current[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $next[] = self::databasePageVisibility($checkpointDatabaseBytes, $pageSize, $pageNumber);
        }

        $currentImages = self::column($current, 'image');
        $nextImages = self::column($next, 'image');

        return [
            'status' => $transactions === [] ? 'no_committed_transactions' : 'ready',
            'transaction_count' => count($transactions),
            'frame_count' => $wal->frameCount(),
            'uncommitted_tail_frame_count' => $wal->uncommittedFrameCount(),
            'database_page_count_before' => $databasePageCountBefore,
            'database_page_count_after' => $databasePageCountAfter,
            'clusters' => $clusters,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::column($current, 'source'),
            'next_reader_sources' => self::column($next, 'source'),
            'current_reader_frame_indexes' => self::column($current, 'frame_index'),
            'next_reader_frame_indexes' => self::column($next, 'frame_index'),
            'current_reader_images' => $currentImages,
            'next_reader_images' => $nextImages,
            'images_match' => $currentImages === $nextImages,
            'dependencies' => ['sqlite-wal-multi-transaction-cluster-current-next'],
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @param array{first_frame:int,last_frame:int,database_page_count:int,page_numbers:list<int>} $transaction
     * @return array<string,mixed>
     */
    private static function clusterSummary(
        SQLiteWal $wal,
        string $beforeDatabaseBytes,
        string $afterDatabaseBytes,
        array $pageNumbers,
        int $ordinal,
        int $previousCommitFrame,
        array $transaction
    ): array {
        $frames = [];
        $allPages = [];
        $lastFrameByPage = [];
        foreach ($wal->frames as $frame) {
            if ($frame->index < $transaction['first_frame'] || $frame->index > $transaction['last_frame']) {
                continue;
            }
            $frames[] = $frame;
            $allPages[$frame->pageNumber] = true;
            $lastFrameByPage[$frame->pageNumber] = $frame->index;
        }

        $appliedPages = [];
        $supersededFrames = [];
        foreach ($frames as $frame) {
            if ($lastFrameByPage[$frame->pageNumber] === $frame->index) {
                $appliedPages[$frame->pageNumber] = true;
            } else {
                $supersededFrames[] = $frame->index;
            }
        }

        $before = [];
        $after = [];
        $pageSize = self::pageSize($wal, $beforeDatabaseBytes);
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL multi-transaction pages must be integers');
            }
            $before[] = self::readerVisibilityOrFuture($wal, $beforeDatabaseBytes, $pageSize, $pageNumber, $previousCommitFrame);
            $after[] = self::databasePageVisibilityOrFuture($afterDatabaseBytes, $pageSize, $pageNumber);
        }

        $allPageNumbers = array_keys($allPages);
        $appliedPageNumbers = array_keys($appliedPages);
        sort($allPageNumbers, SORT_NUMERIC);
        sort($appliedPageNumbers, SORT_NUMERIC);

        return [
            'ordinal' => $ordinal,
            'first_frame' => $transaction['first_frame'],
            'last_frame' => $transaction['last_frame'],
            'frame_count' => count($frames),
            'database_page_count' => $transaction['database_page_count'],
            'page_numbers' => $allPageNumbers,
            'applied_page_numbers' => $appliedPageNumbers,
            'superseded_frame_indexes' => $supersededFrames,
            'before_sources' => self::column($before, 'source'),
            'after_sources' => self::column($after, 'source'),
            'before_frame_indexes' => self::column($before, 'frame_index'),
            'after_frame_indexes' => self::column($after, 'frame_index'),
            'before_images' => self::column($before, 'image'),
            'after_images' => self::column($after, 'image'),
        ];
    }

    private static function checkpointThroughFrame(SQLiteWal $wal, string $databaseBytes, int $commitFrame): string
    {
        $pageSize = self::pageSize($wal, $databaseBytes);
        $databasePageCount = $wal->frames[$commitFrame - 1]->databasePageCountAfterCommit;
        $checkpointBytes = substr(
            $databaseBytes . str_repeat("\0", max(0, ($databasePageCount * $pageSize) - strlen($databaseBytes))),
            0,
            $databasePageCount * $pageSize
        );
        $lastByPage = [];
        foreach ($wal->frames as $frame) {
            if ($frame->index > $commitFrame) {
                break;
            }
            $lastByPage[$frame->pageNumber] = $frame->index;
        }
        foreach ($wal->frames as $frame) {
            if ($frame->index > $commitFrame) {
                break;
            }
            if ($lastByPage[$frame->pageNumber] !== $frame->index || $frame->pageNumber > $databasePageCount) {
                continue;
            }
            $checkpointBytes = substr_replace($checkpointBytes, $frame->pageImage, ($frame->pageNumber - 1) * $pageSize, $pageSize);
        }

        return $checkpointBytes;
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}
     */
    private static function readerVisibility(SQLiteWal $wal, string $databaseBytes, int $pageSize, int $pageNumber, int $snapshotEndFrame): array
    {
        if ($snapshotEndFrame > 0) {
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $snapshotEndFrame);
        }

        return self::databasePageVisibility($databaseBytes, $pageSize, $pageNumber);
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}
     */
    private static function readerVisibilityOrFuture(SQLiteWal $wal, string $databaseBytes, int $pageSize, int $pageNumber, int $snapshotEndFrame): array
    {
        try {
            return self::readerVisibility($wal, $databaseBytes, $pageSize, $pageNumber, $snapshotEndFrame);
        } catch (\OutOfBoundsException) {
            return self::futurePageVisibility($databaseBytes, $pageSize, $pageNumber);
        }
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}
     */
    private static function databasePageVisibilityOrFuture(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        try {
            return self::databasePageVisibility($databaseBytes, $pageSize, $pageNumber);
        } catch (\OutOfBoundsException) {
            return self::futurePageVisibility($databaseBytes, $pageSize, $pageNumber);
        }
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}
     */
    private static function databasePageVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL multi-transaction page numbers are one-based');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL multi-transaction database image must be page aligned');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL multi-transaction page {$pageNumber} is beyond the committed database size");
        }

        $offset = ($pageNumber - 1) * $pageSize;

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => $offset,
            'image' => substr($databaseBytes, $offset, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $databasePageCount,
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}
     */
    private static function futurePageVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL multi-transaction page numbers are one-based');
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'beyond_database',
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => '',
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => intdiv(strlen($databaseBytes), $pageSize),
        ];
    }

    private static function pageSize(SQLiteWal $wal, string $databaseBytes): int
    {
        $pageSize = $wal->header->pageSize;
        if ($pageSize === 0) {
            $pageSize = SQLiteHeader::parse($databaseBytes)->pageSize;
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL multi-transaction cluster requires a database image aligned to the page size');
        }

        return $pageSize;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function column(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column], $rows);
    }
}
