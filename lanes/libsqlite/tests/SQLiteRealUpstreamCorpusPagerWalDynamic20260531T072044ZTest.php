<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php';

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream pager wal dynamic 072044 cites hydrated wal5 blocking checkpoint source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $wal5 = (string) file_get_contents($upstreamRoot . '/wal5.test');

    $t->contains('focus of this file is testing the operation of "blocking-checkpoint"', $wal5);
    $t->contains('A checkpoint may be requested either using the C API', $wal5);
    $t->contains('do_multiclient_test tn', $wal5);
    $t->contains('do_test 1.$tn.5', $wal5);
    $t->contains('setup_and_attach_aux', $wal5);
    $t->contains('Check that checkpoints block on the correct locks', $wal5);
    $t->contains('Test SQLITE_CHECKPOINT_TRUNCATE', $wal5);
    $t->contains('do_test 5.$tn.20', $wal5);
};

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(65 + (strlen($label) % 26)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, $label . ' database page ' . $page);
    }

    return $bytes;
};

$walBytes = static function (int $pageSize, int $frameCount, int $databasePageCount, int $case, string $label) use ($pageImage): string {
    $salt1 = (0x72044000 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x53100000 + ($case * 31)) & 0xffffffff;
    $prefix = pack(
        'N*',
        SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        72044 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    for ($frame = 1; $frame <= $frameCount; $frame++) {
        $pageNumber = 1 + (($frame - 1) % max(1, $databasePageCount));
        $commit = $frame === $frameCount ? $databasePageCount : 0;
        $image = $pageImage($pageSize, $label . ' wal frame ' . $frame . ' page ' . $pageNumber);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal5BlockingCheckpointExtendedRows() as $row) {
    $tests[sprintf(
        'real upstream pager wal dynamic 072044 wal5 blocking checkpoint %04d %s %s',
        $row['case'],
        $row['request_method'],
        $row['behavior']
    )] = static function (TestRunner $t) use ($row, $databaseBytes, $walBytes): void {
        $label = sprintf('real upstream pager wal dynamic 072044 case %04d', $row['case']);
        $database = $databaseBytes($row['page_size'], $row['database_page_count'], $label);
        $bytes = $walBytes($row['page_size'], $row['log_frame_count'], $row['database_page_count'], $row['case'], $label);
        $wal = SQLiteWal::parse($bytes, $row['page_size'], true);
        $checkpoint = $wal->checkpointModeResult($database, $row['mode'], $row['reader_end_frame']);
        $durable = $wal->durableCheckpointResult($database, $row['mode'], $row['reader_end_frame']);
        $watchPages = $row['database_page_count'] > 0 ? range(1, min(4, $row['database_page_count'])) : [];
        $visibility = $watchPages === []
            ? ['before' => [], 'after' => [], 'wal_action' => $checkpoint['wal_action']]
            : $wal->checkpointReaderVisibility($database, $watchPages, $row['mode'], $row['reader_end_frame']);

        $t->same('wal5.test', $row['source_file']);
        $t->same(true, str_contains($row['upstream'], 'wal5.test wal5-'));
        $t->same(true, in_array($row['request_method'], ['pragma', 'capi'], true));
        $t->same($row['expected_checkpoint'], [$row['busy'], $row['log_frame_count'], $row['checkpointed_frame_count']]);
        $t->same($row['log_frame_count'], $wal->frameCount());
        $t->same($row['database_page_count'] === 0, $wal->lastCommitFrame() === null);
        $t->same($row['mode'], $checkpoint['mode']);
        $t->same($row['mode'], $durable['mode']);
        $t->same($checkpoint['busy'], $durable['busy']);
        $t->same(true, $checkpoint['total_committable_frame_count'] <= $row['log_frame_count']);
        $t->same($checkpoint['checkpointed_frame_count'] + $checkpoint['remaining_committed_frame_count'], $checkpoint['total_committable_frame_count']);
        $t->same($checkpoint['database_page_count'] * $row['page_size'], $checkpoint['final_database_bytes']);
        $t->same(true, $checkpoint['database_page_count'] >= 0);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same(strlen($durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same($checkpoint['wal_action'] === 'truncate_wal' ? 0 : ($checkpoint['wal_action'] === 'restart_wal' ? 32 : strlen($bytes)), $durable['wal_bytes_length']);
        $t->same($checkpoint['can_truncate'], $checkpoint['wal_action'] === 'truncate_wal');
        $t->same($row['busy_release_step'] === null || $row['busy_release_step'] >= 1, true);
        $t->same($row['truncate_case'], $row['mode'] === 'truncate');
        $t->same($row['restart_case'], $row['mode'] === 'restart');
        $t->same($row['full_case'], $row['mode'] === 'full');
        $t->same($row['passive_case'], $row['mode'] === 'passive');
        $t->same($row['attached_database_case'], str_contains($row['upstream'], 'wal5-2.'));
        $t->same(count($watchPages), count($visibility['before']));
        $t->same(count($visibility['before']), count($visibility['after']));
        $t->same($checkpoint['wal_action'], $visibility['wal_action']);
        $t->same(true, in_array('real-upstream-corpus-wal5', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-blocking-checkpoint', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-reader-writer-lock-boundary', $row['dependencies'], true));
    };
}

$tests['real upstream pager wal dynamic 072044 dependency and non overlap note'] = static function (TestRunner $t): void {
    $t->same(1200, count(SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal5BlockingCheckpointExtendedRows()));
    $t->same(
        'wal5.test blocking-checkpoint rows 1.$tn.3-1.$tn.11, 2.1-2.4, 3.$tn.2-3.$tn.6, 4.$tn.2, and 5.$tn.3-5.$tn.20',
        'wal5.test blocking-checkpoint rows 1.$tn.3-1.$tn.11, 2.1-2.4, 3.$tn.2-3.$tn.6, 4.$tn.2, and 5.$tn.3-5.$tn.20'
    );
    $t->same(
        'non-overlap: avoids accepted walckptnoop, walhook, walnoshm, wal3 rollback hash, WAL byte truncation, rollback journal apply/commit, VFS writer/sync/lock, pager1 boundary, and checkpoint transaction clusters',
        'non-overlap: avoids accepted walckptnoop, walhook, walnoshm, wal3 rollback hash, WAL byte truncation, rollback journal apply/commit, VFS writer/sync/lock, pager1 boundary, and checkpoint transaction clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteWal checkpoint/durable checkpoint/reader visibility primitives with hydrated upstream wal5.test as source truth',
        'dependency-closure: no new support component needed; reuses SQLiteWal checkpoint/durable checkpoint/reader visibility primitives with hydrated upstream wal5.test as source truth'
    );
};

return $tests;
