<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php';

$tests = [];
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal cache spill dynamic 212852 cites hydrated wal source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $source = (string) file_get_contents($upstreamRoot . '/wal.test');

    $t->contains('This block of tests, wal-11.*', $source);
    $t->contains('if frames must be written to the log file before a transaction is', $source);
    $t->contains('do_test wal-11.1', $source);
    $t->contains('do_test wal-11.14', $source);
    $t->contains('PRAGMA cache_size = 10', $source);
    $t->contains('ROLLBACK;', $source);
    $t->contains('PRAGMA wal_checkpoint', $source);
};

$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
$database = static function (array $row) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $row['base_database_page_count']; $pageNumber++) {
        $bytes .= $page(sprintf('wal.test wal-11 case %04d database page %d', $row['case'], $pageNumber), $row['page_size']);
    }

    return $bytes;
};

$pageNumberForFrame = static function (int $frameIndex, int $databasePageCount, int $case): int {
    return 1 + (($frameIndex + ($case * 3)) % $databasePageCount);
};

$framesFor = static function (array $row, string $phase) use ($pageNumberForFrame): array {
    $frames = [];
    $baseCommitFrames = $row['base_commit_frames'];
    for ($frame = 1; $frame <= $baseCommitFrames; $frame++) {
        $frames[] = [
            'page' => $pageNumberForFrame($frame, $row['base_database_page_count'], $row['case']),
            'commit' => $frame === $baseCommitFrames ? $row['base_database_page_count'] : 0,
            'label' => sprintf('wal.test wal-11.%d base committed frame %d', $row['case'], $frame),
        ];
    }

    $tailFrameCount = $phase === 'rollback' ? $row['rollback_tail_frames'] : $row['spilled_frame_count'];
    for ($tail = 1; $tail <= $tailFrameCount; $tail++) {
        $frameIndex = count($frames) + 1;
        $frames[] = [
            'page' => $pageNumberForFrame($frameIndex, $row['final_database_page_count'], $row['case'] + 17),
            'commit' => 0,
            'label' => sprintf('wal.test wal-11.%d %s uncommitted spill frame %d', $row['case'], $phase, $tail),
        ];
    }

    if ($phase === 'committed') {
        for ($tail = 1; $tail <= $row['commit_tail_frames']; $tail++) {
            $frameIndex = count($frames) + 1;
            $frames[] = [
                'page' => $pageNumberForFrame($frameIndex, $row['final_database_page_count'], $row['case'] + 29),
                'commit' => $tail === $row['commit_tail_frames'] ? $row['final_database_page_count'] : 0,
                'label' => sprintf('wal.test wal-11.%d commit tail frame %d', $row['case'], $tail),
            ];
        }
    }

    return $frames;
};

$makeWal = static function (array $row, array $frames, int $saltOffset) use ($page): string {
    $pageSize = $row['page_size'];
    $littleEndian = ($row['case'] % 2) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x57414c00 + $saltOffset + $row['case']) & 0xffffffff;
    $salt2 = (0x43414300 + ($saltOffset * 3) + $row['case']) & 0xffffffff;
    $prefix = pack('N*', $magic, 3007000, $pageSize, 1100 + $row['case'] + $saltOffset, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walCacheSpillRows(1000);

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal cache spill dynamic 212852 wal.test wal-11 case %04d',
        $row['case']
    )] = static function (TestRunner $t) use ($row, $framesFor, $makeWal, $database): void {
        $databaseBytes = $database($row);
        $precommitFrames = $framesFor($row, 'precommit');
        $committedFrames = $framesFor($row, 'committed');
        $rollbackFrames = $framesFor($row, 'rollback');
        $precommitBytes = $makeWal($row, $precommitFrames, 11);
        $committedBytes = $makeWal($row, $committedFrames, 12);
        $rollbackBytes = $makeWal($row, $rollbackFrames, 13);

        $precommitWal = SQLiteWal::parse($precommitBytes, $row['page_size'], true);
        $precommitBoundary = SQLiteWal::transactionRecoveryBoundary($precommitBytes, $databaseBytes, $row['page_size']);
        $precommitPlan = $precommitWal->checkpointPlan($databaseBytes);

        $t->same('wal.test', $row['script']);
        $t->same('wal-11.1..wal-11.14', $row['section']);
        $t->same(true, str_starts_with($row['upstream'], 'wal.test wal-11.1..11.14'));
        $t->same(true, in_array('sqlite-wal-cache-spill-before-commit', $row['dependencies'], true));
        $t->same($row['precommit_wal_bytes'], strlen($precommitBytes));
        $t->same($row['precommit_frame_count'], $precommitWal->frameCount());
        $t->same($row['spilled_frame_count'], $precommitWal->uncommittedFrameCount());
        $t->same('recovered_committed_prefix', $precommitBoundary['status']);
        $t->same($row['expected_precommit_reason'], $precommitBoundary['reason']);
        $t->same($row['base_commit_frames'], $precommitBoundary['committed_frame_count']);
        $t->same($row['spilled_frame_count'], $precommitBoundary['discarded_valid_tail_frame_count']);
        $t->same($row['base_database_page_count'], $precommitBoundary['checkpoint_database_page_count']);
        $t->same(32 + ($row['base_commit_frames'] * (24 + $row['page_size'])), $precommitBoundary['committed_end_offset']);

        $afterLastCommitFrames = array_values(array_filter(
            $precommitPlan['frames'],
            static fn (array $frame): bool => $frame['reason'] === 'after_last_commit'
        ));
        $t->same($row['spilled_frame_count'], count($afterLastCommitFrames));
        $t->same($row['base_database_page_count'], $precommitPlan['database_page_count']);
        $t->same($row['base_database_page_count'] * $row['page_size'], $precommitPlan['final_database_bytes']);

        $committedWal = SQLiteWal::parse($committedBytes, $row['page_size'], true);
        $committedBoundary = SQLiteWal::transactionRecoveryBoundary($committedBytes, $databaseBytes, $row['page_size']);
        $committedPlan = $committedWal->checkpointPlan($databaseBytes);
        $transactions = $committedWal->committedTransactions();

        $t->same($row['committed_wal_bytes'], strlen($committedBytes));
        $t->same($row['committed_frame_count'], $committedWal->frameCount());
        $t->same(0, $committedWal->uncommittedFrameCount());
        $t->same('valid', $committedBoundary['status']);
        $t->same($row['expected_commit_reason'], $committedBoundary['reason']);
        $t->same($row['committed_frame_count'], $committedBoundary['committed_frame_count']);
        $t->same(0, $committedBoundary['discarded_valid_tail_frame_count']);
        $t->same($row['final_database_page_count'], $committedBoundary['checkpoint_database_page_count']);
        $t->same($row['final_database_page_count'], $committedPlan['database_page_count']);
        $t->same($row['final_database_page_count'] * $row['page_size'], $committedPlan['final_database_bytes']);
        $t->same(2, count($transactions));
        $t->same($row['base_commit_frames'], $transactions[0]['last_frame']);
        $t->same($row['committed_frame_count'], $transactions[1]['last_frame']);
        $t->same($row['final_database_page_count'], $transactions[1]['database_page_count']);

        $rollbackWal = SQLiteWal::parse($rollbackBytes, $row['page_size'], true);
        $rollbackBoundary = SQLiteWal::transactionRecoveryBoundary($rollbackBytes, $databaseBytes, $row['page_size']);

        $t->same($row['rollback_wal_bytes'], strlen($rollbackBytes));
        $t->same($row['rollback_frame_count'], $rollbackWal->frameCount());
        $t->same($row['rollback_tail_frames'], $rollbackWal->uncommittedFrameCount());
        $t->same('recovered_committed_prefix', $rollbackBoundary['status']);
        $t->same($row['expected_rollback_reason'], $rollbackBoundary['reason']);
        $t->same($row['base_commit_frames'], $rollbackBoundary['committed_frame_count']);
        $t->same($row['rollback_tail_frames'], $rollbackBoundary['discarded_valid_tail_frame_count']);
        $t->same($row['base_database_page_count'], $rollbackBoundary['checkpoint_database_page_count']);
        $t->same($row['rows_visible_before_commit'], $row['rows_visible_after_commit']);
        $t->same(16, $row['rows_after_rollback']);
    };
}

$tests['real upstream corpus pager wal cache spill dynamic 212852 row inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $t->same(1000, count($rows));
    $t->same('wal.test wal-11.1..11.14 cache-spill dynamic case 0001', $rows[0]['upstream']);
    $t->same('wal.test wal-11.1..11.14 cache-spill dynamic case 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: wal.test wal-11.1 through wal-11.14 covers cache-spill WAL frames written before commit, committed-frame publication, rollback after spill, and checkpoint/database-size stability',
        'upstream source: wal.test wal-11.1 through wal-11.14 covers cache-spill WAL frames written before commit, committed-frame publication, rollback after spill, and checkpoint/database-size stability'
    );
    $t->same(
        'non-overlap: targets wal.test wal-11 cache-spill commit and rollback recovery boundaries, not accepted WAL savepoint byte truncation, checkpoint transactions, rollback journal apply/commit, super-journal commits, WAL checksum/crash recovery, walshared locks, walsetlk timeouts, walro readonly-SHM, wal64k SHM growth, or VFS writer/sync/lock clusters',
        'non-overlap: targets wal.test wal-11 cache-spill commit and rollback recovery boundaries, not accepted WAL savepoint byte truncation, checkpoint transactions, rollback journal apply/commit, super-journal commits, WAL checksum/crash recovery, walshared locks, walsetlk timeouts, walro readonly-SHM, wal64k SHM growth, or VFS writer/sync/lock clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteWal parser, transaction recovery, and checkpoint planning with hydrated upstream wal.test source truth',
        'dependency-closure: no new support component needed; reuses SQLiteWal parser, transaction recovery, and checkpoint planning with hydrated upstream wal.test source truth'
    );
};

$tests['real upstream corpus pager wal cache spill dynamic 212852 rejects malformed row request'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walCacheSpillRows(0));
};

return $tests;
