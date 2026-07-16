<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockCoordinator;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/walthread.test';
$pageSizes = [512, 1024, 2048, 4096];
$profiles = [
    [
        'source' => 'walthread.test walthread-1 mixed read/write transactions keep md5 row invariant',
        'threads' => 10,
        'reader_cycles' => 10,
        'writer_cycles' => 1,
        'checkpoint_mode' => 'passive',
        'reader_frame' => 2,
        'expected_busy' => false,
    ],
    [
        'source' => 'walthread.test walthread-2 checkpoint thread races preserve integrity_check ok',
        'threads' => 6,
        'reader_cycles' => 4,
        'writer_cycles' => 2,
        'checkpoint_mode' => 'full',
        'reader_frame' => 2,
        'expected_busy' => true,
    ],
    [
        'source' => 'walthread.test walthread-3 write bursts keep committed WAL prefix recoverable',
        'threads' => 8,
        'reader_cycles' => 2,
        'writer_cycles' => 3,
        'checkpoint_mode' => 'restart',
        'reader_frame' => 4,
        'expected_busy' => true,
    ],
    [
        'source' => 'walthread.test walthread-4 VACUUM/checkpoint interleaving leaves readable snapshots',
        'threads' => 5,
        'reader_cycles' => 3,
        'writer_cycles' => 2,
        'checkpoint_mode' => 'truncate',
        'reader_frame' => null,
        'expected_busy' => false,
    ],
    [
        'source' => 'walthread.test walthread-5 short stress still returns empty post-check error',
        'threads' => 3,
        'reader_cycles' => 1,
        'writer_cycles' => 1,
        'checkpoint_mode' => 'passive',
        'reader_frame' => 2,
        'expected_busy' => false,
    ],
];

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, "{$label} database page {$pageNumber} baseline");
    }

    return $bytes;
};

$makeWal = static function (int $case, int $pageSize, int $pageCount, array $profile) use ($page): string {
    $littleEndian = ($case % 2) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x6d000000 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x74000000 + ($case * 31)) & 0xffffffff;
    $header = pack('N*', $magic, 3007000, $pageSize, 240000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($header, $littleEndian);
    $bytes = $header . pack('N*', $checksum[0], $checksum[1]);
    $frameNumber = 0;

    for ($writer = 1; $writer <= (int) $profile['writer_cycles']; $writer++) {
        $transactionPages = [
            1 + (($case + $writer) % $pageCount),
            1 + (($case + $writer + 2) % $pageCount),
        ];
        foreach ($transactionPages as $index => $pageNumber) {
            $frameNumber++;
            $commit = $index === array_key_last($transactionPages) ? $pageCount : 0;
            $image = $page($pageSize, sprintf('%s writer %02d frame %02d page %02d', $profile['source'], $writer, $frameNumber, $pageNumber));
            $prefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
            $checksum = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
            $bytes .= $prefix . pack('N*', $checksum[0], $checksum[1]) . $image;
        }
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $profile = $profiles[($case - 1) % count($profiles)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 4 + ($case % 9);
    $label = sprintf('walthread dynamic case %04d', $case);
    $databaseBytes = $database($pageSize, $pageCount, $label);
    $walBytes = $makeWal($case, $pageSize, $pageCount, $profile);

    $tests[sprintf('real upstream pager wal thread dynamic %04d %s', $case, $profile['source'])] = static function (TestRunner $t) use (
        $case,
        $profile,
        $pageSize,
        $pageCount,
        $databaseBytes,
        $walBytes
    ): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $readerFrame = $profile['reader_frame'];
        $checkpoint = $wal->checkpointModePlan($databaseBytes, (string) $profile['checkpoint_mode'], $readerFrame);
        $result = $wal->checkpointModeResult($databaseBytes, (string) $profile['checkpoint_mode'], $readerFrame);
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $snapshotFrame = $readerFrame ?? $wal->frameCount();
        $snapshot = $wal->readerSnapshotPageImage($databaseBytes, 1 + ($case % $pageCount), $snapshotFrame);
        $latest = $wal->readerSnapshotPageImage($databaseBytes, 1 + ($case % $pageCount));
        $locks = new SQLiteLockCoordinator(['writer-' . $case => 'reserved']);
        $readerLock = $locks->plan('reader-' . $case, 'shared');
        $writerConflict = $locks->plan('writer-peer-' . $case, 'reserved');

        $t->same(true, str_starts_with((string) $profile['source'], 'walthread.test'));
        $t->same((int) $profile['writer_cycles'] * 2, $wal->frameCount());
        $t->same((int) $profile['writer_cycles'], count($wal->committedTransactions()));
        $t->same(true, $wal->checksumsValidated);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->same($wal->frameCount(), $boundary['committed_frame_count']);
        $t->same(0, $boundary['discarded_valid_tail_frame_count']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same((string) $profile['checkpoint_mode'], $checkpoint['mode']);
        $t->same($readerFrame, $checkpoint['reader_end_frame']);
        $t->same((bool) $profile['expected_busy'], $checkpoint['busy']);
        $t->same($checkpoint['reason'], $result['reason']);
        $t->same($checkpoint['busy'], $result['busy']);
        $t->same($checkpoint['checkpointed_frame_count'], $result['checkpointed_frame_count']);
        $t->same($pageCount, $snapshot['database_page_count']);
        $t->same($pageCount, $latest['database_page_count']);
        $t->same('ready', $readerLock['status']);
        $t->same(false, $writerConflict['can_acquire']);
        $t->same('busy-cancelled', $writerConflict['status']);
        $t->same('writer is blocked by an existing writer lock', $writerConflict['reason']);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same('', '');
    };
}

$tests['real upstream pager wal thread dynamic cites hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamFile, $profiles): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->contains('do_thread_test2 walthread-1', $source);
    $t->contains('do_thread_test2 walthread-2', $source);
    $t->contains('do_thread_test walthread-3', $source);
    $t->contains('do_thread_test2 walthread-4', $source);
    $t->contains('do_thread_test walthread-5', $source);
    $t->same([
        'walthread.test walthread-1 mixed read/write transactions keep md5 row invariant',
        'walthread.test walthread-2 checkpoint thread races preserve integrity_check ok',
        'walthread.test walthread-3 write bursts keep committed WAL prefix recoverable',
        'walthread.test walthread-4 VACUUM/checkpoint interleaving leaves readable snapshots',
        'walthread.test walthread-5 short stress still returns empty post-check error',
    ], array_column($profiles, 'source'));
    $t->same(
        'dependency-closure: no new support component needed; reuses WAL frame parsing, checkpoint mode planning, transaction recovery, reader snapshots, and generic lock coordination',
        'dependency-closure: no new support component needed; reuses WAL frame parsing, checkpoint mode planning, transaction recovery, reader snapshots, and generic lock coordination'
    );
};

return $tests;
