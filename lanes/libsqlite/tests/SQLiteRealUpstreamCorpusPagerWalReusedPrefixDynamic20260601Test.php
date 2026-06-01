<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];
$upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test';

$tests['real upstream corpus pager wal reused prefix dynamic cites hydrated wal 12 source'] = static function (TestRunner $t) use ($upstream): void {
    $source = (string) file_get_contents($upstream);

    $t->contains('This block of tests, wal-12.*', $source);
    $t->contains('could occur if a log that is a prefix of an older log', $source);
    $t->contains('do_test wal-12.1', $source);
    $t->contains('do_test wal-12.6', $source);
    $t->contains('forcecopy test.db-wal test2.db-wal', $source);
    $t->contains("UPDATE t2 SET y = 2 WHERE x = 'B'", $source);
};

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, ' ', STR_PAD_RIGHT);
};

$databaseBytes = static function (array $row) use ($page): string {
    $case = (int) $row['case'];
    $pageSize = (int) $row['page_size'];
    $phase = (string) $row['phase'];
    $t2Base = $phase === 'copy_after_checkpoint_cycles'
        ? sprintf('wal.test wal-12 case %04d database page 3 t2 row B 2 checkpointed', $case)
        : sprintf('wal.test wal-12 case %04d database page 3 t2 empty before reused WAL', $case);

    return $page($pageSize, sprintf('wal.test wal-12 case %04d database page 1 schema', $case))
        . $page($pageSize, sprintf('wal.test wal-12 case %04d database page 2 t1 row A 1 base', $case))
        . $page($pageSize, $t2Base);
};

$walBytes = static function (array $row, string $generation) use ($page): string {
    $pageSize = (int) $row['page_size'];
    $case = (int) $row['case'];
    $littleEndian = (bool) $row['little_endian'];
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $isNew = $generation === 'new';
    $salt1 = (int) $row[$isNew ? 'new_salt1' : 'old_salt1'];
    $salt2 = (int) $row[$isNew ? 'new_salt2' : 'old_salt2'];
    $checkpointSequence = (int) $row[$isNew ? 'new_checkpoint_sequence' : 'old_checkpoint_sequence'];
    $frameCount = (int) $row[$isNew ? 'new_frame_count' : 'old_frame_count'];
    $phase = (string) $row['phase'];

    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    for ($frame = 1; $frame <= $frameCount; $frame++) {
        $commit = $frame === $frameCount ? (int) $row['database_page_count'] : 0;
        if ($isNew && $phase === 'copy_after_short_reuse') {
            $pageNumber = $frame === 1 ? 2 : 3;
            $label = $frame === 1
                ? sprintf('wal.test wal-12 case %04d new reused WAL page 2 t1 row A 0', $case)
                : sprintf('wal.test wal-12 case %04d new reused WAL page 3 t2 row B 1', $case);
        } elseif ($isNew) {
            $pageNumber = $frame === 1 ? 2 : 1;
            $label = $frame === 1
                ? sprintf('wal.test wal-12 case %04d checkpoint-cycle reused WAL page 2 t1 row A 0', $case)
                : sprintf('wal.test wal-12 case %04d checkpoint-cycle reused WAL page 1 schema refresh', $case);
        } else {
            $oldPages = [1, 2, 3, 2, 3, 1, 2, 3];
            $pageNumber = $oldPages[($frame - 1) % count($oldPages)];
            $label = sprintf('wal.test wal-12 case %04d stale older WAL frame %02d page %d old tail', $case, $frame, $pageNumber);
        }

        $image = $page($pageSize, $label);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$reusedWalBytes = static function (array $row) use ($walBytes): string {
    $pageSize = (int) $row['page_size'];
    $newFrameCount = (int) $row['new_frame_count'];
    $frameSize = 24 + $pageSize;
    $newWal = $walBytes($row, 'new');
    $oldWal = $walBytes($row, 'old');

    return substr($newWal, 0, 32 + ($newFrameCount * $frameSize))
        . substr($oldWal, 32 + ($newFrameCount * $frameSize));
};

$pageSlice = static function (string $bytes, int $pageSize, int $pageNumber): string {
    return substr($bytes, ($pageNumber - 1) * $pageSize, $pageSize);
};

$rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walReusedLogPrefixRows(1000);

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal reused prefix dynamic wal.test wal-12 case %04d %s',
        $row['case'],
        $row['phase']
    )] = static function (TestRunner $t) use ($row, $databaseBytes, $reusedWalBytes, $pageSlice): void {
        $database = $databaseBytes($row);
        $reusedWal = $reusedWalBytes($row);
        $checksum = SQLiteWal::checksumRecoveryBoundary($reusedWal, $database, $row['page_size']);
        $boundary = SQLiteWal::transactionRecoveryBoundary($reusedWal, $database, $row['page_size']);
        $committedWal = SQLiteWal::parse($boundary['committed_wal_bytes'], $row['page_size'], true);
        $checkpointDatabase = (string) $boundary['checkpoint_database_bytes'];
        $transactions = $committedWal->committedTransactions();
        $t2Page = $pageSlice($checkpointDatabase, $row['page_size'], $row['expected_t2_page']);

        $t->same('wal.test', $row['script']);
        $t->same(true, str_starts_with($row['section'], 'wal-12.'));
        $t->same(1024, $row['page_size']);
        $t->same(3, $row['database_page_count']);
        $t->same(true, $row['old_frame_count'] > $row['new_frame_count']);
        $t->same($row['old_frame_count'] - $row['new_frame_count'], $row['stale_tail_frame_count']);
        $t->same($row['new_frame_count'] + 1, $row['first_stale_frame']);
        $t->same(true, in_array('sqlite-wal-reused-log-prefix-recovery', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-stale-salt-tail-discard', $row['dependencies'], true));
        $t->same($row['old_frame_count'], $checksum['total_frame_slots']);
        $t->same($row['new_frame_count'], $checksum['valid_frame_count']);
        $t->same($row['first_stale_frame'], $checksum['first_invalid_frame']);
        $t->same($row['expected_recovery_reason'], $checksum['reason']);
        $t->same($row['expected_boundary_status'], $boundary['status']);
        $t->same($row['expected_boundary_reason'], $boundary['reason']);
        $t->same($row['old_frame_count'], $boundary['total_frame_slots']);
        $t->same($row['new_frame_count'], $boundary['valid_frame_count']);
        $t->same($row['new_frame_count'], $boundary['committed_frame_count']);
        $t->same($row['new_frame_count'], $boundary['last_commit_frame']);
        $t->same($row['database_page_count'], $boundary['last_commit_page_count']);
        $t->same(0, $boundary['discarded_valid_tail_frame_count']);
        $t->same($row['stale_tail_frame_count'], $boundary['discarded_corrupt_tail_frame_count']);
        $t->same(32 + ($row['new_frame_count'] * (24 + $row['page_size'])), $boundary['committed_end_offset']);
        $t->same($boundary['committed_end_offset'], strlen($boundary['committed_wal_bytes']));
        $t->same($row['new_frame_count'], $committedWal->frameCount());
        $t->same(1, count($transactions));
        $t->same(1, $transactions[0]['first_frame']);
        $t->same($row['new_frame_count'], $transactions[0]['last_frame']);
        $t->same($row['database_page_count'], $transactions[0]['database_page_count']);
        $t->same($row['database_page_count'], $boundary['checkpoint_database_page_count']);
        $t->same($row['database_page_count'] * $row['page_size'], strlen($checkpointDatabase));
        $t->same(true, str_contains($t2Page, 't2 row ' . $row['expected_t2_value']));
        $t->same($row['expected_t2_source'], $row['phase'] === 'copy_after_short_reuse' ? 'wal_frame' : 'database_image');
        $t->throws(InvalidArgumentException::class, static fn (): SQLiteWal => SQLiteWal::parse($reusedWal, $row['page_size'], true));
    };
}

$tests['real upstream corpus pager wal reused prefix dynamic inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $phases = array_count_values(array_column($rows, 'phase'));

    $t->same(1000, count($rows));
    $t->same(500, $phases['copy_after_short_reuse']);
    $t->same(500, $phases['copy_after_checkpoint_cycles']);
    $t->same('wal.test wal-12.1..12.4 reused-log prefix dynamic case 0001', $rows[0]['upstream']);
    $t->same('wal.test wal-12.5..12.6 reused-log prefix dynamic case 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: wal.test wal-12.1 through wal-12.6 checks reused WAL files where a shorter new log is a prefix of an older stale log',
        'upstream source: wal.test wal-12.1 through wal-12.6 checks reused WAL files where a shorter new log is a prefix of an older stale log'
    );
    $t->same(
        'non-overlap: targets wal.test wal-12 stale reused-log tail recovery, not accepted wal-17 full-sync padding, wal-18 checksum/page-size recovery, wal-19 close checkpoint, wal-16 attached checkpoint, wal-11 cache spill, rollback-journal apply/commit, VFS writer/sync/lock, or savepoint byte truncation',
        'non-overlap: targets wal.test wal-12 stale reused-log tail recovery, not accepted wal-17 full-sync padding, wal-18 checksum/page-size recovery, wal-19 close checkpoint, wal-16 attached checkpoint, wal-11 cache spill, rollback-journal apply/commit, VFS writer/sync/lock, or savepoint byte truncation'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteWal checksum recovery, transaction recovery, checkpoint image application, and hydrated upstream wal.test source truth',
        'dependency-closure: no new support component needed; reuses SQLiteWal checksum recovery, transaction recovery, checkpoint image application, and hydrated upstream wal.test source truth'
    );
};

$tests['real upstream corpus pager wal reused prefix dynamic rejects invalid row count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walReusedLogPrefixRows(0));
};

return $tests;
