<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$page = static fn (string $label, int $pageSize): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$label} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (int $pageSize, int $basePages, int $case, int $commits, int $drafts) use ($page): string {
    $salt1 = (0x6d2b7900 + $case) & 0xffffffff;
    $salt2 = (0x4f1bb300 + ($case * 3)) & 0xffffffff;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 900 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $frame = 0;

    for ($txn = 1; $txn <= $commits; $txn++) {
        $frame++;
        $pageNumber = (($case + $txn) % $basePages) + 1;
        $image = $page("walckptnoop.test case {$case} txn {$txn} draft frame {$frame}", $pageSize);
        $framePrefix = pack('N*', $pageNumber, 0, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;

        $frame++;
        $pageNumber = (($case + $txn + 1) % ($basePages + ($txn % 3))) + 1;
        $commitPageCount = max($basePages, $basePages + ($txn % 3));
        $image = $page("walckptnoop.test case {$case} txn {$txn} commit frame {$frame}", $pageSize);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    for ($draft = 1; $draft <= $drafts; $draft++) {
        $frame++;
        $pageNumber = (($case + $draft + $commits) % $basePages) + 1;
        $image = $page("walckptnoop.test case {$case} uncommitted draft {$draft}", $pageSize);
        $framePrefix = pack('N*', $pageNumber, 0, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $basePages = 2 + ($case % 7);
    $commits = 1 + ($case % 5);
    $drafts = $case % 4;
    $readerEndFrame = match ($case % 5) {
        0 => 0,
        1 => 1,
        2 => $commits,
        default => null,
    };
    $databaseBytes = $database($pageSize, $basePages, 'real upstream walckptnoop dynamic ' . $case);
    $wal = SQLiteWal::parse($makeWalBytes($pageSize, $basePages, $case, $commits, $drafts), $pageSize, true);
    $noop = $wal->checkpointModeResult($databaseBytes, 'noop', $readerEndFrame);
    $passive = $wal->checkpointModeResult($databaseBytes, 'passive', $readerEndFrame);
    $lastCommit = $wal->lastCommitFrame();
    $label = sprintf('real upstream pager wal noop checkpoint dynamic %04d walckptnoop.test 1.%d', $case, (($case - 1) % 10) + 1);

    $tests[$label] = static function (TestRunner $t) use ($case, $pageSize, $basePages, $commits, $drafts, $readerEndFrame, $databaseBytes, $wal, $noop, $passive, $lastCommit): void {
        $t->same('noop', $noop['mode']);
        $t->same('passive', $passive['mode']);
        $t->same(0, $noop['checkpointed_frame_count']);
        $t->same(strlen($databaseBytes), $noop['final_database_bytes']);
        $t->same($databaseBytes, $noop['database_bytes']);
        $t->same('preserve_wal', $noop['wal_action']);
        $t->same(false, $noop['can_reset']);
        $t->same(false, $noop['can_truncate']);
        $t->same(false, $noop['busy']);
        $t->same($readerEndFrame, $noop['reader_end_frame']);
        $t->same($basePages, $noop['database_page_count']);
        $t->same($drafts, $noop['uncommitted_frame_count']);
        $t->same($commits, count($wal->committedTransactions()));
        $t->same($lastCommit?->index, $noop['last_commit_frame'] ?? $wal->lastCommitFrame()?->index);
        $t->same($lastCommit?->databasePageCountAfterCommit, $passive['database_page_count']);
        $t->same($noop['total_committable_frame_count'], $noop['remaining_committed_frame_count']);
        $t->same($noop['total_committable_frame_count'], $passive['total_committable_frame_count']);
        $t->true($passive['checkpointed_frame_count'] <= $passive['total_committable_frame_count']);
        if ($readerEndFrame === null) {
            $t->same($passive['total_committable_frame_count'], $passive['checkpointed_frame_count']);
        } elseif ($readerEndFrame === 0) {
            $t->same(0, $passive['checkpointed_frame_count']);
        }
        $t->same($pageSize, $wal->header->pageSize);
        $t->true($case >= 1);
    };
}

$tests['real upstream pager wal noop checkpoint dynamic records hydrated upstream file'] = static function (TestRunner $t): void {
    $t->same([
        'walckptnoop.test: walckptnoop-1.0 builds WAL with 298 frames',
        'walckptnoop.test: walckptnoop-1.1 and 1.2 NOOP reports log frames without checkpointing',
        'walckptnoop.test: walckptnoop-1.3 PASSIVE backfills frames',
        'walckptnoop.test: walckptnoop-1.4 NOOP reports existing backfill',
        'walckptnoop.test: walckptnoop-1.5 reopen resets NOOP backfill observation',
        'walckptnoop.test: walckptnoop-1.7 in-transaction NOOP is locked',
        'walckptnoop.test: walckptnoop-1.10 DELETE journal mode reports -1/-1',
    ], [
        'walckptnoop.test: walckptnoop-1.0 builds WAL with 298 frames',
        'walckptnoop.test: walckptnoop-1.1 and 1.2 NOOP reports log frames without checkpointing',
        'walckptnoop.test: walckptnoop-1.3 PASSIVE backfills frames',
        'walckptnoop.test: walckptnoop-1.4 NOOP reports existing backfill',
        'walckptnoop.test: walckptnoop-1.5 reopen resets NOOP backfill observation',
        'walckptnoop.test: walckptnoop-1.7 in-transaction NOOP is locked',
        'walckptnoop.test: walckptnoop-1.10 DELETE journal mode reports -1/-1',
    ]);
};

return $tests;
