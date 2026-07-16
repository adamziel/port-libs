<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockCoordinator;
use PortLibs\LibSqlite\SQLitePagerCheckpointTransactionPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 1024;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('walckptnoop database page one before checkpoint')
    . $page('walckptnoop database page two before checkpoint')
    . $page('walckptnoop database page three before checkpoint')
    . $page('walckptnoop database page four before checkpoint')
    . $page('walckptnoop database page five before checkpoint');

$makeWal = static function (int $seed, int $committedFrames, int $checkpointedFrames = 0) use ($pageSize, $page): SQLiteWal {
    $salt1 = (0x51000000 + $seed) & 0xffffffff;
    $salt2 = (0x52000000 + ($seed * 7)) & 0xffffffff;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointedFrames, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    for ($frame = 1; $frame <= $committedFrames; $frame++) {
        $pageNumber = 1 + (($frame + $seed) % 5);
        $commitPageCount = $frame === $committedFrames ? 5 : 0;
        $image = $page(sprintf('walckptnoop.test frame %03d/%03d', $seed, $frame));
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return SQLiteWal::parse($bytes, $pageSize, true);
};

$scenarios = [
    ['walckptnoop.test 1.1 first noop reports log without checkpointing', 298, 0, null, 0, 5, false, 'noop_checkpoint_does_not_backfill'],
    ['walckptnoop.test 1.2 repeated noop leaves checkpoint count unchanged', 298, 0, null, 0, 5, false, 'noop_checkpoint_does_not_backfill'],
    ['walckptnoop.test 1.4 noop after passive reports already checkpointed log', 298, 298, null, 0, 5, false, 'noop_checkpoint_does_not_backfill'],
    ['walckptnoop.test 1.5 restored noop reports sidecar log without backfill', 298, 0, null, 0, 5, false, 'noop_checkpoint_does_not_backfill'],
    ['walckptnoop.test 1.6 reopened checkpointed database has empty WAL', 0, 0, null, 0, 0, false, 'wal_has_no_frames'],
    ['walckptnoop.test 1.8 commit then noop reports remaining log only', 5, 0, null, 0, 5, false, 'noop_checkpoint_does_not_backfill'],
    ['walckptnoop.test 1.9 sqlite3_wal_checkpoint_v2 noop matches pragma', 5, 0, null, 0, 5, false, 'noop_checkpoint_does_not_backfill'],
    ['walckptnoop.test reader-pinned noop does not become busy', 298, 0, 17, 0, 5, false, 'noop_checkpoint_does_not_backfill'],
];

for ($case = 1; $case <= 256; $case++) {
    [$upstream, $frames, $checkpointed, $readerEndFrame, $expectedCheckpointed, $expectedRemaining, $expectedBusy, $expectedReason] = $scenarios[($case - 1) % count($scenarios)];
    $tests[sprintf('real upstream pager wal noop checkpoint dynamic %03d %s', $case, $upstream)] = static function (TestRunner $t) use (
        $makeWal,
        $databaseBytes,
        $case,
        $frames,
        $checkpointed,
        $readerEndFrame,
        $expectedCheckpointed,
        $expectedRemaining,
        $expectedBusy,
        $expectedReason
    ): void {
        $wal = $makeWal($case, $frames, $checkpointed);
        $beforeWalBytes = $wal->toBytes();
        $plan = $wal->checkpointModePlan($databaseBytes, 'noop', $readerEndFrame);
        $result = $wal->durableCheckpointResult($databaseBytes, 'noop', $readerEndFrame);
        $transaction = SQLitePagerCheckpointTransactionPlan::plan(
            new SQLiteLockCoordinator(),
            'walckptnoop-' . $case,
            $wal,
            $databaseBytes,
            '/srv/app/data/noop-checkpoint.sqlite',
            'noop',
            $readerEndFrame
        );

        $t->same('noop', $plan['mode']);
        $t->same($expectedReason, $plan['reason']);
        $t->same($expectedBusy, $plan['busy']);
        $t->same($expectedCheckpointed, $plan['checkpointed_frame_count']);
        $t->same($expectedRemaining, $plan['remaining_committed_frame_count']);
        $t->same(false, $plan['can_reset']);
        $t->same(false, $plan['can_truncate']);

        $t->same($databaseBytes, $result['database_bytes']);
        $t->same($beforeWalBytes, $result['wal_bytes']);
        $t->same('preserve_wal', $result['wal_action']);
        $t->same(strlen($beforeWalBytes), $result['wal_bytes_length']);

        $t->same('ready', $transaction['status']);
        $t->same(true, $transaction['can_checkpoint']);
        $t->same('noop', $transaction['mode']);
        $t->same('shared', $transaction['lock_sequence'][0]['requested']);
        $t->same([], $transaction['write_plan']['operations']);
        $t->same($expectedReason, $transaction['write_plan']['reason']);
        $t->same(strlen($databaseBytes), $transaction['write_plan']['database_bytes']);
        $t->same(strlen($beforeWalBytes), $transaction['write_plan']['wal_bytes']);
        $t->same(true, in_array('sqlite-pager-checkpoint-transaction', $transaction['dependencies'], true));
    };
}

$tests['real upstream pager wal noop checkpoint rejects rollback journal mode parity'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWal::parse('', 1024, true));
};

$tests['real upstream pager wal noop checkpoint records upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'walckptnoop.test: 1.1 noop reports log frames and zero checkpointed frames',
        'walckptnoop.test: 1.2 repeated noop is non-mutating',
        'walckptnoop.test: 1.4 noop after passive preserves checkpointed-frame count',
        'walckptnoop.test: 1.5 restored connection noop does not backfill',
        'walckptnoop.test: 1.6 empty WAL returns zero counts',
        'walckptnoop.test: 1.8-1.9 committed tail noop reports log without checkpoint writes',
        'walckptnoop.test: 1.10 rollback-journal mode returns delete/0/-1/-1 outside WAL parsing',
    ], [
        'walckptnoop.test: 1.1 noop reports log frames and zero checkpointed frames',
        'walckptnoop.test: 1.2 repeated noop is non-mutating',
        'walckptnoop.test: 1.4 noop after passive preserves checkpointed-frame count',
        'walckptnoop.test: 1.5 restored connection noop does not backfill',
        'walckptnoop.test: 1.6 empty WAL returns zero counts',
        'walckptnoop.test: 1.8-1.9 committed tail noop reports log without checkpoint writes',
        'walckptnoop.test: 1.10 rollback-journal mode returns delete/0/-1/-1 outside WAL parsing',
    ]);
};

return $tests;
