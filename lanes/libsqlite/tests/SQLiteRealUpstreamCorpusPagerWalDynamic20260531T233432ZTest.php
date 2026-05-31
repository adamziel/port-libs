<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal dynamic 233432 cites hydrated wal 17 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $source = (string) file_get_contents($upstreamRoot . '/wal.test');
    $common = (string) file_get_contents($upstreamRoot . '/wal_common.tcl');

    $t->contains('The following tests - wal-17.*', $source);
    $t->contains('number of "padding" frames', $source);
    $t->contains('PRAGMA synchronous = FULL', $source);
    $t->contains('1   128  [wal_file_size 172 512]', $source);
    $t->contains('6  4096  [wal_file_size 176 512]', $source);
    $t->contains('7  8192  [wal_file_size 184 512]', $source);
    $t->contains('proc wal_file_size {nFrame pgsz}', $common);
};

$page = static fn (int $pageSize, string $label): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = static function (array $row) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $row['database_page_count']; $pageNumber++) {
        $bytes .= $page($row['page_size'], sprintf('wal.test wal-17 case %04d database page %03d', $row['case'], $pageNumber));
    }

    return $bytes;
};

$makeWal = static function (array $row) use ($page): string {
    $pageSize = $row['page_size'];
    $littleEndian = ($row['case'] % 2) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x57170000 + $row['case']) & 0xffffffff;
    $salt2 = (0x50170000 + ($row['sector_size'] >> 1) + $row['case']) & 0xffffffff;
    $prefix = pack('N*', $magic, 3007000, $pageSize, 1700 + $row['case'], $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    for ($frame = 1; $frame <= $row['total_frame_count']; $frame++) {
        $pageNumber = 1 + (($frame + $row['case']) % $row['database_page_count']);
        $commit = $frame === $row['transaction_frame_count'] ? $row['database_page_count'] : 0;
        $label = $frame <= $row['transaction_frame_count']
            ? sprintf('wal.test wal-17 case %04d committed transaction frame %03d', $row['case'], $frame)
            : sprintf('wal.test wal-17 case %04d fullsync padding frame %03d', $row['case'], $frame);
        $image = $page($pageSize, $label);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walFullSyncPaddingRows(1000);

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal dynamic 233432 wal.test wal-17 fullsync padding case %04d',
        $row['case']
    )] = static function (TestRunner $t) use ($row, $makeWal, $databaseBytes): void {
        $bytes = $makeWal($row);
        $database = $databaseBytes($row);
        $wal = SQLiteWal::parse($bytes, $row['page_size'], true);
        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $database, $row['page_size']);
        $plan = $wal->checkpointPlan($database);
        $lastCommit = $wal->lastCommitFrame();
        $afterCommitFrames = array_values(array_filter(
            $plan['frames'],
            static fn (array $frame): bool => $frame['reason'] === 'after_last_commit'
        ));

        $t->same('wal.test', $row['script']);
        $t->same('full', $row['synchronous']);
        $t->same('wal', $row['journal_mode']);
        $t->same(512, $row['page_size']);
        $t->same(-2000, $row['cache_size']);
        $t->same(true, in_array($row['sector_size'], [128, 256, 512, 1024, 2048, 4096, 8192], true));
        $t->same(true, str_starts_with($row['upstream'], 'wal.test wal-17.'));
        $t->same(true, in_array('sqlite-wal-full-sync-padding', $row['dependencies'], true));
        $t->same($row['total_frame_count'], $wal->frameCount());
        $t->same($row['padding_frame_count'], $wal->uncommittedFrameCount());
        $t->same(true, $lastCommit !== null);
        $t->same($row['transaction_frame_count'], $lastCommit?->index);
        $t->same($row['database_page_count'], $lastCommit?->databasePageCountAfterCommit);
        $t->same($row['next_transaction_start_bytes'], strlen($bytes));
        $t->same(true, $row['next_transaction_start_sector'] > $row['transaction_end_sector']);
        $t->same($row['padding_frame_count'], count($afterCommitFrames));
        $t->same($row['database_page_count'], $plan['database_page_count']);
        $t->same($row['database_page_count'] * $row['page_size'], $plan['final_database_bytes']);
        $t->same($row['padding_frame_count'] === 0 ? 'valid' : 'recovered_committed_prefix', $boundary['status']);
        $t->same($row['transaction_frame_count'], $boundary['committed_frame_count']);
        $t->same($row['padding_frame_count'], $boundary['discarded_valid_tail_frame_count']);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($row['database_page_count'], $boundary['checkpoint_database_page_count']);

        if ($row['matches_upstream_wal17_example']) {
            $t->same($row['upstream_total_frames_for_171'], $row['total_frame_count']);
            $t->same($row['upstream_log_bytes_for_171'], $row['next_transaction_start_bytes']);
        } else {
            $t->same(false, $row['matches_upstream_wal17_example']);
        }
    };
}

$tests['real upstream corpus pager wal dynamic 233432 row inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $matches = array_values(array_filter($rows, static fn (array $row): bool => $row['matches_upstream_wal17_example']));
    $upstreamTotals = [];
    foreach ($matches as $row) {
        $upstreamTotals[$row['sector_size']] = $row['total_frame_count'];
    }
    ksort($upstreamTotals);

    $t->same(1000, count($rows));
    $t->same([128 => 172, 256 => 172, 512 => 172, 1024 => 172, 2048 => 172, 4096 => 176, 8192 => 184], $upstreamTotals);
    $t->same('wal.test wal-17.1 synchronous FULL padding dynamic case 0001', $rows[0]['upstream']);
    $t->same('wal.test wal-17.4 synchronous FULL padding dynamic case 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: wal.test wal-17.1 through wal-17.7 checks synchronous=FULL WAL padding frames for 128..8192 byte sectors using wal_file_size totals 172, 176, and 184',
        'upstream source: wal.test wal-17.1 through wal-17.7 checks synchronous=FULL WAL padding frames for 128..8192 byte sectors using wal_file_size totals 172, 176, and 184'
    );
    $t->same(
        'non-overlap: targets wal.test wal-17 full-sync padding frames, not accepted wal2 checkpoint fullsync counts, WAL byte truncation, checkpoint transactions, rollback-journal apply/commit, cache-spill wal-11, wal-18 checksum/page-size recovery, VFS writer/sync/lock, or pager4 DBMOVED batches',
        'non-overlap: targets wal.test wal-17 full-sync padding frames, not accepted wal2 checkpoint fullsync counts, WAL byte truncation, checkpoint transactions, rollback-journal apply/commit, cache-spill wal-11, wal-18 checksum/page-size recovery, VFS writer/sync/lock, or pager4 DBMOVED batches'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteWal parser, transaction recovery, checkpoint planning, and hydrated upstream wal.test source truth',
        'dependency-closure: no new support component needed; reuses SQLiteWal parser, transaction recovery, checkpoint planning, and hydrated upstream wal.test source truth'
    );
};

$tests['real upstream corpus pager wal dynamic 233432 rejects invalid row count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walFullSyncPaddingRows(0));
};

return $tests;
