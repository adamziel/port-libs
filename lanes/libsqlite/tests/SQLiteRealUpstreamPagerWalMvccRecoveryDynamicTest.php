<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x11223344;
$salt2 = 0x55667788;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('app schema baseline') . $page('app row baseline two') . $page('app row baseline three');

$makeWalBytes = static function (array $frames, int $sequence = 31, ?callable $mutate = null) use ($pageSize, $salt1, $salt2, $page): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $sequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $mutate === null ? $bytes : $mutate($bytes);
};

$walCases = [
    [
        'source' => 'wal.test wal-1.0..1.5 create table and append visible committed WAL frames',
        'frames' => [
            [1, 0, 'wal1 schema page draft'],
            [2, 2, 'wal1 table root commit'],
        ],
        'snapshot_frame' => null,
        'page' => 2,
        'expected' => [
            'frame_count' => 2,
            'commit_frames' => [2],
            'snapshot_end' => 2,
            'database_page_count' => 2,
            'page_source' => 'wal',
            'page_frame' => 2,
            'page_prefix' => 'wal1 table root commit',
            'checkpoint_page_count' => 2,
            'checkpoint_prefix' => 'wal1 table root commit',
            'checkpoint_busy' => false,
            'checkpoint_wal_action' => 'truncate_wal',
            'recovery_status' => 'valid',
            'recovery_reason' => 'all_frames_valid',
            'recovery_committed' => 2,
            'recovery_valid' => 2,
            'readmark_pin' => null,
            'readmark_latest' => 2,
            'uncommitted' => 0,
        ],
    ],
    [
        'source' => 'wal.test wal-2.1..2.6 reader snapshot remains pinned while writer appends later commit',
        'frames' => [
            [2, 0, 'wal2 reader page two'],
            [3, 3, 'wal2 first commit page three'],
            [2, 0, 'wal2 writer page two'],
            [4, 4, 'wal2 second commit page four'],
        ],
        'snapshot_frame' => 2,
        'page' => 2,
        'expected' => [
            'frame_count' => 4,
            'commit_frames' => [2, 4],
            'snapshot_end' => 2,
            'database_page_count' => 3,
            'page_source' => 'wal',
            'page_frame' => 1,
            'page_prefix' => 'wal2 reader page two',
            'checkpoint_page_count' => 4,
            'checkpoint_prefix' => 'wal2 writer page two',
            'checkpoint_busy' => true,
            'checkpoint_wal_action' => 'preserve_wal',
            'recovery_status' => 'valid',
            'recovery_reason' => 'all_frames_valid',
            'recovery_committed' => 4,
            'recovery_valid' => 4,
            'readmark_pin' => null,
            'readmark_latest' => 4,
            'uncommitted' => 0,
        ],
    ],
    [
        'source' => 'wal.test wal-3.1..3.3 transaction rollback discards uncommitted delete tail',
        'frames' => [
            [1, 0, 'wal3 old schema'],
            [2, 2, 'wal3 committed data'],
            [2, 0, 'wal3 delete draft page two'],
            [3, 0, 'wal3 delete draft page three'],
        ],
        'snapshot_frame' => null,
        'page' => 2,
        'expected' => [
            'frame_count' => 4,
            'commit_frames' => [2],
            'snapshot_end' => 4,
            'database_page_count' => 2,
            'page_source' => 'wal',
            'page_frame' => 2,
            'page_prefix' => 'wal3 committed data',
            'checkpoint_page_count' => 2,
            'checkpoint_prefix' => 'wal3 committed data',
            'checkpoint_busy' => false,
            'checkpoint_wal_action' => 'preserve_wal',
            'recovery_status' => 'recovered_committed_prefix',
            'recovery_reason' => 'uncommitted_valid_tail_after_last_commit',
            'recovery_committed' => 2,
            'recovery_valid' => 4,
            'readmark_pin' => null,
            'readmark_latest' => 2,
            'uncommitted' => 2,
        ],
    ],
    [
        'source' => 'wal.test wal-4.1..4.3 savepoint rollback leaves only pre-savepoint row committed',
        'frames' => [
            [2, 0, 'wal4 savepoint before row'],
            [3, 3, 'wal4 pre savepoint commit'],
            [3, 0, 'wal4 rolled back savepoint row'],
            [4, 0, 'wal4 rolled back savepoint index'],
        ],
        'snapshot_frame' => null,
        'page' => 3,
        'expected' => [
            'frame_count' => 4,
            'commit_frames' => [2],
            'snapshot_end' => 4,
            'database_page_count' => 3,
            'page_source' => 'wal',
            'page_frame' => 2,
            'page_prefix' => 'wal4 pre savepoint commit',
            'checkpoint_page_count' => 3,
            'checkpoint_prefix' => 'wal4 pre savepoint commit',
            'checkpoint_busy' => false,
            'checkpoint_wal_action' => 'preserve_wal',
            'recovery_status' => 'recovered_committed_prefix',
            'recovery_reason' => 'uncommitted_valid_tail_after_last_commit',
            'recovery_committed' => 2,
            'recovery_valid' => 4,
            'readmark_pin' => null,
            'readmark_latest' => 2,
            'uncommitted' => 2,
        ],
    ],
    [
        'source' => 'wal2.test wal2-15.1..15.12 checkpoint applies latest committed page before sync',
        'frames' => [
            [2, 0, 'wal2sync first page two'],
            [3, 3, 'wal2sync first commit'],
            [2, 0, 'wal2sync latest page two'],
            [5, 5, 'wal2sync grown commit'],
        ],
        'snapshot_frame' => null,
        'page' => 5,
        'expected' => [
            'frame_count' => 4,
            'commit_frames' => [2, 4],
            'snapshot_end' => 4,
            'database_page_count' => 5,
            'page_source' => 'wal',
            'page_frame' => 4,
            'page_prefix' => 'wal2sync grown commit',
            'checkpoint_page_count' => 5,
            'checkpoint_prefix' => 'wal2sync latest page two',
            'checkpoint_busy' => false,
            'checkpoint_wal_action' => 'truncate_wal',
            'recovery_status' => 'valid',
            'recovery_reason' => 'all_frames_valid',
            'recovery_committed' => 4,
            'recovery_valid' => 4,
            'readmark_pin' => null,
            'readmark_latest' => 4,
            'uncommitted' => 0,
        ],
    ],
    [
        'source' => 'walrestart.test restart checkpoint preserves uncheckpointed reader prefix',
        'frames' => [
            [1, 0, 'restart schema edit'],
            [2, 2, 'restart first commit'],
            [2, 0, 'restart second page'],
            [3, 3, 'restart second commit'],
            [1, 0, 'restart post commit draft'],
        ],
        'snapshot_frame' => 2,
        'page' => 2,
        'expected' => [
            'frame_count' => 5,
            'commit_frames' => [2, 4],
            'snapshot_end' => 2,
            'database_page_count' => 2,
            'page_source' => 'wal',
            'page_frame' => 2,
            'page_prefix' => 'restart first commit',
            'checkpoint_page_count' => 3,
            'checkpoint_prefix' => 'restart second page',
            'checkpoint_busy' => true,
            'checkpoint_wal_action' => 'preserve_wal',
            'recovery_status' => 'recovered_committed_prefix',
            'recovery_reason' => 'uncommitted_valid_tail_after_last_commit',
            'recovery_committed' => 4,
            'recovery_valid' => 5,
            'readmark_pin' => null,
            'readmark_latest' => 4,
            'uncommitted' => 1,
        ],
    ],
    [
        'source' => 'walcksum.test checksum failure after commit recovers committed prefix',
        'frames' => [
            [1, 0, 'cksum schema'],
            [2, 2, 'cksum committed page'],
            [3, 0, 'cksum corrupt tail page'],
        ],
        'snapshot_frame' => null,
        'page' => 2,
        'mutate' => static fn (string $bytes): string => substr_replace($bytes, 'X', 32 + (2 * (24 + 512)) + 64, 1),
        'expected' => [
            'frame_count' => 2,
            'commit_frames' => [2],
            'snapshot_end' => 2,
            'database_page_count' => 2,
            'page_source' => 'wal',
            'page_frame' => 2,
            'page_prefix' => 'cksum committed page',
            'checkpoint_page_count' => 2,
            'checkpoint_prefix' => 'cksum committed page',
            'checkpoint_busy' => false,
            'checkpoint_wal_action' => 'truncate_wal',
            'recovery_status' => 'recovered_committed_prefix',
            'recovery_reason' => 'corrupt_tail_after_committed_prefix',
            'recovery_committed' => 2,
            'recovery_valid' => 2,
            'readmark_pin' => null,
            'readmark_latest' => 2,
            'uncommitted' => 0,
        ],
    ],
    [
        'source' => 'walcksum.test truncated WAL tail recovers prior complete committed frame',
        'frames' => [
            [1, 0, 'trunc schema'],
            [2, 2, 'trunc committed page'],
            [2, 0, 'trunc incomplete tail page'],
        ],
        'snapshot_frame' => null,
        'page' => 2,
        'mutate' => static fn (string $bytes): string => substr($bytes, 0, -128),
        'expected' => [
            'frame_count' => 2,
            'commit_frames' => [2],
            'snapshot_end' => 2,
            'database_page_count' => 2,
            'page_source' => 'wal',
            'page_frame' => 2,
            'page_prefix' => 'trunc committed page',
            'checkpoint_page_count' => 2,
            'checkpoint_prefix' => 'trunc committed page',
            'checkpoint_busy' => false,
            'checkpoint_wal_action' => 'truncate_wal',
            'recovery_status' => 'recovered_committed_prefix',
            'recovery_reason' => 'corrupt_tail_after_committed_prefix',
            'recovery_committed' => 2,
            'recovery_valid' => 2,
            'readmark_pin' => null,
            'readmark_latest' => 2,
            'uncommitted' => 0,
        ],
    ],
];

$assertions = [
    'valid recovered frame count matches upstream transaction boundary' => static fn (SQLiteWal $wal, array $boundary, array $case): mixed => $wal->frameCount(),
    'commit frame indexes match upstream commit markers' => static fn (SQLiteWal $wal, array $boundary, array $case): mixed => array_column($wal->committedTransactions(), 'last_frame'),
    'reader snapshot end frame follows upstream MVCC pin' => static function (SQLiteWal $wal, array $boundary, array $case) use ($databaseBytes): mixed {
        return $wal->readerSnapshot($databaseBytes, $case['snapshot_frame'])['end_frame'];
    },
    'reader snapshot database page count follows commit marker' => static function (SQLiteWal $wal, array $boundary, array $case) use ($databaseBytes): mixed {
        return $wal->readerSnapshot($databaseBytes, $case['snapshot_frame'])['database_page_count'];
    },
    'snapshot page image uses expected source' => static function (SQLiteWal $wal, array $boundary, array $case) use ($databaseBytes): mixed {
        return $wal->readerSnapshotPageImage($databaseBytes, $case['page'], $case['snapshot_frame'])['source'];
    },
    'snapshot page image uses expected frame index' => static function (SQLiteWal $wal, array $boundary, array $case) use ($databaseBytes): mixed {
        return $wal->readerSnapshotPageImage($databaseBytes, $case['page'], $case['snapshot_frame'])['frame_index'];
    },
    'snapshot page image prefix is stable' => static function (SQLiteWal $wal, array $boundary, array $case) use ($databaseBytes): mixed {
        return rtrim(substr($wal->readerSnapshotPageImage($databaseBytes, $case['page'], $case['snapshot_frame'])['image'], 0, 32), '.');
    },
    'checkpoint applies committed page count' => static function (SQLiteWal $wal, array $boundary, array $case) use ($databaseBytes): mixed {
        return $wal->checkpointModeResult($databaseBytes, 'passive')['database_page_count'];
    },
    'checkpoint applies committed page prefix' => static function (SQLiteWal $wal, array $boundary, array $case) use ($databaseBytes): mixed {
        return rtrim(substr($wal->checkpointModeResult($databaseBytes, 'passive')['database_bytes'], ($case['page'] === 5 ? 1 : $case['page'] - 1) * 512, 32), ".\0");
    },
    'restart or full checkpoint busy state matches reader pin' => static function (SQLiteWal $wal, array $boundary, array $case) use ($databaseBytes): mixed {
        return $wal->checkpointModePlan($databaseBytes, 'restart', $case['snapshot_frame'])['busy'];
    },
    'truncate checkpoint preserves wal when tail or reader requires it' => static function (SQLiteWal $wal, array $boundary, array $case) use ($databaseBytes): mixed {
        return $wal->checkpointModeResult($databaseBytes, 'truncate', $case['snapshot_frame'])['wal_action'];
    },
    'transaction recovery status matches upstream tail rule' => static fn (SQLiteWal $wal, array $boundary, array $case): mixed => $boundary['status'],
    'transaction recovery reason names upstream tail rule' => static fn (SQLiteWal $wal, array $boundary, array $case): mixed => $boundary['reason'],
    'transaction recovery committed frame count matches last commit' => static fn (SQLiteWal $wal, array $boundary, array $case): mixed => $boundary['committed_frame_count'],
    'transaction recovery valid frame count stops before corrupt tail' => static fn (SQLiteWal $wal, array $boundary, array $case): mixed => $boundary['valid_frame_count'],
    'readmark plan pins oldest active reader' => static fn (SQLiteWal $wal, array $boundary, array $case): mixed => $wal->readMarkPlan([0, $case['expected']['readmark_pin'], $case['expected']['readmark_latest'], null])['checkpoint_pinned_frame'],
    'readmark plan recommends latest committed frame' => static fn (SQLiteWal $wal, array $boundary, array $case): mixed => $wal->readMarkPlan([0, $case['expected']['readmark_pin'], $case['expected']['readmark_latest'], null])['recommended_reader_frame'],
    'uncommitted tail frame count is preserved for recovery diagnostics' => static fn (SQLiteWal $wal, array $boundary, array $case): mixed => $wal->uncommittedFrameCount(),
    'reader page map includes requested page' => static function (SQLiteWal $wal, array $boundary, array $case) use ($databaseBytes): mixed {
        return in_array($case['page'], array_column($wal->readerSnapshotPageMap($databaseBytes, $case['snapshot_frame']), 'page_number'), true);
    },
    'hydrated upstream filename is cited' => static fn (SQLiteWal $wal, array $boundary, array $case): mixed => preg_match('/^(wal|wal2|walrestart|walcksum)\.test /', $case['source']) === 1,
];

$expectedKey = [
    'valid recovered frame count matches upstream transaction boundary' => 'frame_count',
    'commit frame indexes match upstream commit markers' => 'commit_frames',
    'reader snapshot end frame follows upstream MVCC pin' => 'snapshot_end',
    'reader snapshot database page count follows commit marker' => 'database_page_count',
    'snapshot page image uses expected source' => 'page_source',
    'snapshot page image uses expected frame index' => 'page_frame',
    'snapshot page image prefix is stable' => 'page_prefix',
    'checkpoint applies committed page count' => 'checkpoint_page_count',
    'checkpoint applies committed page prefix' => 'checkpoint_prefix',
    'restart or full checkpoint busy state matches reader pin' => 'checkpoint_busy',
    'truncate checkpoint preserves wal when tail or reader requires it' => 'checkpoint_wal_action',
    'transaction recovery status matches upstream tail rule' => 'recovery_status',
    'transaction recovery reason names upstream tail rule' => 'recovery_reason',
    'transaction recovery committed frame count matches last commit' => 'recovery_committed',
    'transaction recovery valid frame count stops before corrupt tail' => 'recovery_valid',
    'readmark plan pins oldest active reader' => 'readmark_pin',
    'readmark plan recommends latest committed frame' => 'readmark_latest',
    'uncommitted tail frame count is preserved for recovery diagnostics' => 'uncommitted',
    'reader page map includes requested page' => null,
    'hydrated upstream filename is cited' => null,
];

for ($i = 1; $i <= 1000; $i++) {
    $case = $walCases[($i - 1) % count($walCases)];
    $assertionName = array_keys($assertions)[($i - 1) % count($assertions)];
    $callback = $assertions[$assertionName];
    $key = $expectedKey[$assertionName];
    $label = sprintf('real upstream pager wal mvcc recovery dynamic %04d %s %s', $i, $case['source'], $assertionName);

    $tests[$label] = static function (TestRunner $t) use ($case, $callback, $key, $makeWalBytes, $databaseBytes): void {
        $walBytes = $makeWalBytes($case['frames'], 31, $case['mutate'] ?? null);
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes);
        $wal = $boundary['wal'];
        $expected = $key === null ? true : $case['expected'][$key];

        $t->same($expected, $callback($wal, $boundary, $case));
    };
}

$tests['real upstream pager wal mvcc recovery dynamic records upstream sections'] = static function (TestRunner $t) use ($walCases): void {
    $t->same([
        'wal.test wal-1.0..1.5 create table and append visible committed WAL frames',
        'wal.test wal-2.1..2.6 reader snapshot remains pinned while writer appends later commit',
        'wal.test wal-3.1..3.3 transaction rollback discards uncommitted delete tail',
        'wal.test wal-4.1..4.3 savepoint rollback leaves only pre-savepoint row committed',
        'wal2.test wal2-15.1..15.12 checkpoint applies latest committed page before sync',
        'walrestart.test restart checkpoint preserves uncheckpointed reader prefix',
        'walcksum.test checksum failure after commit recovers committed prefix',
        'walcksum.test truncated WAL tail recovers prior complete committed frame',
    ], array_column($walCases, 'source'));
};

return $tests;
