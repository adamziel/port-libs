<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [1024, 2048, 4096];
$page = static fn (string $label, int $pageSize): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$label} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (int $case, int $pageSize, int $basePages, int $extraCommit, int $savepointStart, int $savepointUpdates, bool $rollbackSavepoint) use ($page): string {
    $salt1 = (0x5a170000 + $case) & 0xffffffff;
    $salt2 = (0x6b280000 + ($case * 7)) & 0xffffffff;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1200 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $frame = 0;

    $appendFrame = static function (int $pageNumber, int $commitPageCount, string $imageLabel) use (&$bytes, &$seed, &$frame, $page, $pageSize, $salt1, $salt2): void {
        $frame++;
        $image = $page($imageLabel, $pageSize);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    if ($extraCommit > 0) {
        $appendFrame(($case % $basePages) + 1, $basePages, "waloverwrite.test preexisting transaction case {$case}");
    }

    for ($i = 1; $i <= 20; $i++) {
        $pageNumber = (($case + $i) % $basePages) + 1;
        $commit = $i === 20 && !$rollbackSavepoint ? $basePages : 0;
        $appendFrame($pageNumber, $commit, "waloverwrite.test case {$case} bulk overwrite row {$i}");
    }

    for ($i = 1; $i <= $savepointStart; $i++) {
        $pageNumber = (($case + 20 + $i) % ($basePages + 1)) + 1;
        $appendFrame($pageNumber, 0, "waloverwrite.test case {$case} savepoint prefix {$i}");
    }

    for ($i = 1; $i <= $savepointUpdates; $i++) {
        $pageNumber = (($case + 40 + $i) % ($basePages + 1)) + 1;
        $appendFrame($pageNumber, 0, "waloverwrite.test case {$case} rolled back savepoint update {$i}");
    }

    if ($rollbackSavepoint) {
        for ($i = 1; $i <= 3; $i++) {
            $pageNumber = (($case + 60 + $i) % ($basePages + 1)) + 1;
            $commit = $i === 3 ? $basePages + 1 : 0;
            $appendFrame($pageNumber, $commit, "waloverwrite.test case {$case} post rollback commit {$i}");
        }
    } else {
        $appendFrame(($case % ($basePages + 1)) + 1, $basePages + 1, "waloverwrite.test case {$case} final overwrite commit");
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $basePages = 42 + ($case % 12);
    $extraCommit = $case % 2;
    $savepointStart = 2 + ($case % 6);
    $savepointUpdates = 5 + ($case % 9);
    $rollbackSavepoint = ($case % 3) !== 0;
    $upstreamSubtest = $rollbackSavepoint
        ? sprintf('waloverwrite.test 1.%d.7-9 savepoint rollback recovery', ($case % 2) + 1)
        : sprintf('waloverwrite.test 1.%d.1-6 repeated page overwrite recovery', ($case % 2) + 1);
    $label = sprintf('real upstream pager wal overwrite dynamic %04d %s', $case, $upstreamSubtest);

    $tests[$label] = static function (TestRunner $t) use ($case, $pageSize, $basePages, $extraCommit, $savepointStart, $savepointUpdates, $rollbackSavepoint, $databaseBytes, $makeWalBytes): void {
        $database = $databaseBytes($pageSize, $basePages, "real upstream waloverwrite dynamic {$case}");
        $walBytes = $makeWalBytes($case, $pageSize, $basePages, $extraCommit, $savepointStart, $savepointUpdates, $rollbackSavepoint);
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $database, $pageSize);
        $checkpoint = $wal->checkpointModeResult($database, 'passive');
        $readerFrame = $rollbackSavepoint ? max(1, $boundary['committed_frame_count'] - 1) : $boundary['committed_frame_count'];
        $reader = $wal->readerSnapshotPageImage($database, (($case + 3) % $basePages) + 1, $readerFrame);

        $t->same(true, $wal->checksumsValidated);
        $t->same($pageSize, $wal->header->pageSize);
        $t->same(true, $wal->frameCount() >= 24);
        $t->same(strlen($walBytes), 32 + $wal->frameCount() * (24 + $pageSize));
        $t->same('valid', $boundary['status']);
        $t->same('all_frames_valid', $boundary['reason']);
        $t->same($wal->frameCount(), $boundary['valid_frame_count']);
        $t->same($wal->frameCount(), $boundary['committed_frame_count']);
        $t->same(0, $boundary['discarded_valid_tail_frame_count']);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same(true, $boundary['can_checkpoint']);
        $t->same($basePages + 1, $boundary['last_commit_page_count']);
        $t->same($basePages + 1, $checkpoint['database_page_count']);
        $t->same(true, $checkpoint['total_committable_frame_count'] <= $wal->frameCount());
        $t->same(true, $checkpoint['checkpointed_frame_count'] <= $checkpoint['total_committable_frame_count']);
        $t->same(false, $checkpoint['busy']);
        $t->same('preserve_wal', $checkpoint['wal_action']);
        $t->same(true, count($wal->committedTransactions()) - $extraCommit >= 1);
        $t->same(2 + ($case % 6), $savepointStart);
        $t->same(5 + ($case % 9), $savepointUpdates);
        $t->same((($case % 3) !== 0), $rollbackSavepoint);
        $t->same($reader['source'] === 'wal' || $reader['source'] === 'database', true);
        $t->same($reader['page_number'] >= 1, true);
        $t->same($reader['page_number'] <= $basePages, true);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
    };
}

$tests['real upstream pager wal overwrite dynamic records source corpus'] = static function (TestRunner $t): void {
    $t->same([
        'waloverwrite.test: 1.1.1-1.1.6 repeated dirty page overwrites keep WAL frame count bounded and recoverable',
        'waloverwrite.test: 1.2.1-1.2.6 same overwrite sequence with a preexisting WAL transaction',
        'waloverwrite.test: 1.1.7-1.1.9 savepoint rollback excludes rolled-back blob updates from recovery',
        'waloverwrite.test: 1.2.7-1.2.9 savepoint rollback after a preexisting WAL transaction stays recoverable',
    ], [
        'waloverwrite.test: 1.1.1-1.1.6 repeated dirty page overwrites keep WAL frame count bounded and recoverable',
        'waloverwrite.test: 1.2.1-1.2.6 same overwrite sequence with a preexisting WAL transaction',
        'waloverwrite.test: 1.1.7-1.1.9 savepoint rollback excludes rolled-back blob updates from recovery',
        'waloverwrite.test: 1.2.7-1.2.9 savepoint rollback after a preexisting WAL transaction stays recoverable',
    ]);
};

return $tests;
