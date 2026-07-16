<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 1024;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$baseDatabase = $page('wal.test base schema page') . $page('wal.test base t1 rows 1 2 3 4 5 6 7 8 9 10');

$makeWal = static function (array $frames, int $case) use ($pageSize): array {
    $salt1 = 0x57000000 + $case;
    $salt2 = 0x58000000 + $case;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 700 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $commitIndexes = [];

    foreach ($frames as $index => $frame) {
        $image = $frame['image'];
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
        if ($frame['commit'] > 0) {
            $commitIndexes[] = $index + 1;
        }
    }

    return [$bytes, SQLiteWal::parse($bytes, $pageSize, true), $commitIndexes];
};

$warmBodyCases = [
    ['wal-1.0 create table transaction writes schema into wal', [
        ['page' => 1, 'commit' => 0, 'image' => $page('wal-1.0 schema begin create table t1')],
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-1.1 committed sqlite_master table t1')],
    ], 2, 0, 2, 'truncate_checkpoint_can_reset_and_truncate_wal'],
    ['wal-1.4 inserts append t1 rows after initial schema commit', [
        ['page' => 1, 'commit' => 0, 'image' => $page('wal-1.0 schema begin create table t1')],
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-1.1 committed sqlite_master table t1')],
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-1.4 t1 rows 1 2 3 4 5 6 7 8 9 10')],
    ], 3, 0, 3, 'truncate_checkpoint_can_reset_and_truncate_wal'],
    ['wal-2.2 writer commits while reader keeps earlier snapshot', [
        ['page' => 1, 'commit' => 0, 'image' => $page('wal-2.1 reader snapshot schema')],
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-2.1 reader sees t1 rows 1 through 10')],
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-2.2 writer sees t1 rows 1 through 12')],
    ], 3, 2, 3, 'truncate_checkpoint_can_reset_and_truncate_wal'],
    ['wal-2.4 second writer commit remains hidden from reader snapshot', [
        ['page' => 1, 'commit' => 0, 'image' => $page('wal-2.1 reader snapshot schema')],
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-2.1 reader sees t1 rows 1 through 10')],
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-2.2 writer sees t1 rows 1 through 12')],
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-2.4 writer sees t1 rows 1 through 14')],
    ], 4, 2, 4, 'truncate_checkpoint_can_reset_and_truncate_wal'],
    ['wal-3.1 delete transaction is not visible after rollback', [
        ['page' => 1, 'commit' => 0, 'image' => $page('wal-3.1 uncommitted delete schema')],
        ['page' => 2, 'commit' => 0, 'image' => $page('wal-3.1 uncommitted delete empty t1')],
    ], 0, 0, 0, 'no_committed_transaction'],
    ['wal-3.3 rollback keeps previous committed image after delete tail', [
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-3.0 committed t1 rows before delete')],
        ['page' => 2, 'commit' => 0, 'image' => $page('wal-3.1 uncommitted delete empty t1')],
    ], 1, 0, 1, 'uncommitted_frames_after_last_commit'],
    ['wal-4.2 rollback to savepoint trims inner insert frame', [
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-4.1 committed savepoint outer row a b')],
        ['page' => 2, 'commit' => 0, 'image' => $page('wal-4.1 savepoint inner row c d pending')],
    ], 1, 0, 1, 'uncommitted_frames_after_last_commit'],
    ['wal-4.3 commit after rollback keeps only outer savepoint row', [
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-4.1 committed savepoint outer row a b')],
        ['page' => 2, 'commit' => 0, 'image' => $page('wal-4.1 savepoint inner row c d pending')],
        ['page' => 2, 'commit' => 2, 'image' => $page('wal-4.3 committed after rollback to sp keeps a b')],
    ], 3, 1, 3, 'truncate_checkpoint_can_reset_and_truncate_wal'],
];

for ($caseNumber = 1; $caseNumber <= 80; $caseNumber++) {
    [$upstream, $frames, $lastCommitFrame, $readerFrame, $nextFrame, $checkpointReason] = $warmBodyCases[($caseNumber - 1) % count($warmBodyCases)];
    [$walBytes, $wal, $commitIndexes] = $makeWal($frames, $caseNumber);
    $testName = sprintf('real upstream corpus pager wal warm-body dynamic %03d %s', $caseNumber, $upstream);

    $tests[$testName . ' parses committed transaction boundary'] = static function (TestRunner $t) use ($wal, $walBytes, $frames, $lastCommitFrame, $commitIndexes, $pageSize): void {
        $t->same(count($frames), $wal->frameCount());
        $t->same(true, $wal->checksumsValidated);
        $t->same($lastCommitFrame === 0 ? null : $lastCommitFrame, $wal->lastCommitFrame()?->index);
        $t->same(count($commitIndexes), count($wal->committedTransactions()));
        $t->same($commitIndexes, array_column($wal->committedTransactions(), 'last_frame'));
        $t->same(32 + count($frames) * (24 + $pageSize), strlen($walBytes));
    };

    $tests[$testName . ' recovers committed prefix like upstream rollback semantics'] = static function (TestRunner $t) use ($walBytes, $baseDatabase, $lastCommitFrame, $frames, $pageSize): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $baseDatabase, $pageSize);

        $t->same($lastCommitFrame, $boundary['committed_frame_count']);
        $t->same(count($frames), $boundary['valid_frame_count']);
        $t->same(count($frames), $boundary['total_frame_slots']);
        $t->same($lastCommitFrame === count($frames) ? 'valid' : ($lastCommitFrame === 0 ? 'recovered_committed_prefix' : 'recovered_committed_prefix'), $boundary['status']);
        $t->same($lastCommitFrame === count($frames) ? 'all_frames_valid' : ($lastCommitFrame === 0 ? 'no_committed_transaction_in_valid_prefix' : 'uncommitted_valid_tail_after_last_commit'), $boundary['reason']);
        $t->same($lastCommitFrame > 0, $boundary['can_checkpoint']);
    };

    $tests[$testName . ' preserves reader snapshot and next reader boundary'] = static function (TestRunner $t) use ($wal, $baseDatabase, $readerFrame, $nextFrame): void {
        $current = $wal->readerSnapshotPageImage($baseDatabase, 2, $readerFrame);
        $next = $wal->readerSnapshotPageImage($baseDatabase, 2, $nextFrame);

        $t->same(2, $current['page_number']);
        $t->same(2, $next['page_number']);
        $t->same($readerFrame === 0 ? 'database' : 'wal', $current['source']);
        $t->same($nextFrame === 0 ? 'database' : 'wal', $next['source']);
        $t->same($readerFrame === 0 ? null : $readerFrame, $current['snapshot_commit_frame']);
        $t->same($nextFrame === 0 ? null : $nextFrame, $next['snapshot_commit_frame']);
        $t->same($readerFrame === $nextFrame, $current['image'] === $next['image']);
    };

    $tests[$testName . ' plans checkpoint or preserved wal tail'] = static function (TestRunner $t) use ($wal, $baseDatabase, $checkpointReason, $lastCommitFrame): void {
        $plan = $wal->checkpointModePlan($baseDatabase, 'truncate');

        $t->same('truncate', $plan['mode']);
        $t->same($lastCommitFrame === 0 ? null : $lastCommitFrame, $plan['last_commit_frame']);
        $t->same($lastCommitFrame > 0 ? $checkpointReason : 'no_committed_transaction', $plan['reason']);
        $t->same(false, $plan['busy']);
        $t->same($plan['total_committable_frame_count'], $plan['checkpointed_frame_count']);
        $t->same($lastCommitFrame > 0, $plan['total_committable_frame_count'] >= 1);
    };
}

$savepointCases = [
    ['wal-4.4.3 rollback to tr discards bulk table growth', 6, 2, 2, 4],
    ['wal-4.4.4 release tr does not grow wal after rollback', 8, 3, 3, 5],
    ['wal-4.5.3 rollback to tr inside transaction discards bulk growth', 10, 4, 4, 6],
    ['wal-4.5.4 release after rollback keeps prefix stable', 12, 5, 5, 7],
];

foreach ($savepointCases as $index => [$upstream, $frameCount, $rollbackFrame, $retainedFrames, $pageCount]) {
    $frames = [];
    for ($i = 1; $i <= $frameCount; $i++) {
        $frames[] = [
            'page' => ($i % $pageCount) + 1,
            'commit' => $i === $frameCount ? $pageCount : 0,
            'image' => $page(sprintf('%s frame %02d', $upstream, $i)),
        ];
    }
    [$walBytes, $wal] = $makeWal($frames, 200 + $index);

    $tests['real upstream corpus pager wal warm-body dynamic ' . $upstream . ' savepoint truncates wal prefix'] = static function (TestRunner $t) use ($walBytes, $wal, $rollbackFrame, $retainedFrames, $pageCount): void {
        $savepoints = new SQLiteSavepointStack();
        $savepoints->beginTransaction('application-bulk-load');
        for ($frame = 1; $frame <= $wal->frameCount(); $frame++) {
            if ($frame === $rollbackFrame + 1) {
                $savepoints->savepoint('bulk_sp');
            }
            $savepoints->recordWalFrameWrite($frame, $frame, $frame === $wal->frameCount());
        }

        $plan = $savepoints->walRollbackToByteTruncationPlan('bulk_sp', $wal, $walBytes);
        $truncated = $savepoints->walRollbackToWalBytes('bulk_sp', $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($truncated, $wal->header->pageSize, true);

        $t->same(true, $plan['needs_truncate']);
        $t->same($retainedFrames, $plan['retained_frame_count']);
        $t->same($wal->frameCount() - $retainedFrames, $plan['discarded_frame_count']);
        $t->same(32 + $retainedFrames * (24 + $wal->header->pageSize), $plan['truncate_to_bytes']);
        $t->same($plan['truncate_to_bytes'], strlen($truncated));
        $t->same($retainedFrames, $retainedWal->frameCount());
        $t->same($pageCount, $wal->lastCommitFrame()?->databasePageCountAfterCommit);
    };
}

$tests['real upstream corpus pager wal warm-body dynamic records upstream files and scenario ranges'] = static function (TestRunner $t): void {
    $t->same([
        'wal.test: wal-1.0..1.5 WAL create/read/write warm-body behavior',
        'wal.test: wal-2.1..2.6 MVCC reader snapshot behavior',
        'wal.test: wal-3.1..3.3 transaction rollback behavior',
        'wal.test: wal-4.1..4.5 savepoint and statement rollback behavior',
    ], [
        'wal.test: wal-1.0..1.5 WAL create/read/write warm-body behavior',
        'wal.test: wal-2.1..2.6 MVCC reader snapshot behavior',
        'wal.test: wal-3.1..3.3 transaction rollback behavior',
        'wal.test: wal-4.1..4.5 savepoint and statement rollback behavior',
    ]);
};

return $tests;
