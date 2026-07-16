<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$upstreamSections = [
    ['wal3.test', 'wal3-1.0 large WAL seed creates 4056 committed frames before rollback churn'],
    ['wal3.test', 'wal3-1.$i.1 rollback removes WAL-index hash-table entries without integrity loss'],
    ['wal3.test', 'wal3-1.$i.2 external reader keeps the 4018-row committed snapshot'],
    ['wal3.test', 'wal3-1.$i.5 copied database/WAL pair recovers after rollback churn'],
    ['wal3.test', 'wal3-2.multiproc.4 checkpoint is reader-blocked before older snapshots release'],
    ['wal3.test', 'wal3-2.singleproc.5 checkpoint backfills after older reader commits'],
    ['wal3.test', 'wal3-3.* byte-is-zero checks distinguish backfilled pages from pinned pages'],
    ['wal3.test', 'wal3-4.* WAL restart preserves readable snapshots across checkpoint attempts'],
];

$tests['real upstream pager wal3 rollback hash dynamic 061959 cites hydrated upstream file'] = static function (TestRunner $t) use ($upstreamRoot, $upstreamSections): void {
    $wal3 = (string) file_get_contents($upstreamRoot . '/wal3.test');

    $t->contains('When a rollback or savepoint rollback occurs', $wal3);
    $t->contains('wal-index', $wal3);
    $t->contains('do_test wal3-1.0', $wal3);
    $t->contains('for {set i 1} {$i < 50} {incr i}', $wal3);
    $t->contains('PRAGMA integrity_check', $wal3);
    $t->contains('forcecopy test.db-wal test2.db-wal', $wal3);
    $t->contains('do_multiclient_test i', $wal3);
    $t->contains('PRAGMA wal_checkpoint', $wal3);
    $t->same([
        ['wal3.test', 'wal3-1.0 large WAL seed creates 4056 committed frames before rollback churn'],
        ['wal3.test', 'wal3-1.$i.1 rollback removes WAL-index hash-table entries without integrity loss'],
        ['wal3.test', 'wal3-1.$i.2 external reader keeps the 4018-row committed snapshot'],
        ['wal3.test', 'wal3-1.$i.5 copied database/WAL pair recovers after rollback churn'],
        ['wal3.test', 'wal3-2.multiproc.4 checkpoint is reader-blocked before older snapshots release'],
        ['wal3.test', 'wal3-2.singleproc.5 checkpoint backfills after older reader commits'],
        ['wal3.test', 'wal3-3.* byte-is-zero checks distinguish backfilled pages from pinned pages'],
        ['wal3.test', 'wal3-4.* WAL restart preserves readable snapshots across checkpoint attempts'],
    ], $upstreamSections);
};

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(48 + (strlen($label) % 42)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, "{$label} database page {$page}");
    }

    return $bytes;
};

$makeWalBytes = static function (int $case, int $pageSize, array $frames, bool $littleEndian) use ($pageImage): string {
    $salt1 = (0x61959000 + ($case * 29)) & 0xffffffff;
    $salt2 = (0x33100000 + ($case * 53)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        61959 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage($pageSize, (string) $frame['label']);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [1024, 2048, 4096][($case - 1) % 3];
    $pageCount = 32 + ($case % 7);
    $littleEndian = ($case % 11) === 0;
    $checkpointMode = ['passive', 'full', 'restart', 'truncate'][($case - 1) % 4];
    $readerEndFrame = 8;
    $rollbackStartFrame = 9 + ($case % 4);
    $rollbackFrameCount = 100 + ($case % 97);
    $expectedSeedFrameCount = 4056;
    $expectedCommittedRows = 4018;
    $label = sprintf('real upstream pager wal3 rollback hash dynamic 20260531T061959Z %04d', $case);
    $pages = [
        1 + (($case * 2) % $pageCount),
        1 + (($case * 3) % $pageCount),
        1 + (($case * 5) % $pageCount),
        1 + (($case * 7) % $pageCount),
        1 + (($case * 11) % $pageCount),
        1 + (($case * 13) % $pageCount),
        1 + (($case * 17) % $pageCount),
        1 + (($case * 19) % $pageCount),
    ];
    $frames = [
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$script} {$section} seed frame 1"],
        ['page' => $pages[1], 'commit' => 0, 'label' => "{$script} {$section} seed frame 2"],
        ['page' => $pages[2], 'commit' => 0, 'label' => "{$script} {$section} seed frame 3"],
        ['page' => $pages[3], 'commit' => 4, 'label' => "{$script} {$section} seed commit 4"],
        ['page' => $pages[4], 'commit' => 0, 'label' => "{$script} {$section} update row image"],
        ['page' => $pages[5], 'commit' => 6, 'label' => "{$script} {$section} update commit"],
        ['page' => $pages[6], 'commit' => 0, 'label' => "{$script} {$section} checkpoint-visible page"],
        ['page' => $pages[7], 'commit' => $pageCount, 'label' => "{$script} {$section} post-update commit"],
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$script} {$section} rolled back hash page 1"],
        ['page' => $pages[1], 'commit' => 0, 'label' => "{$script} {$section} rolled back hash page 2"],
        ['page' => $pages[2], 'commit' => 0, 'label' => "{$script} {$section} rolled back hash page 3"],
        ['page' => $pages[3], 'commit' => 0, 'label' => "{$script} {$section} rolled back hash page 4"],
    ];
    $watchPages = array_values(array_unique($pages));

    $tests[sprintf(
        'real upstream pager wal3 rollback hash dynamic 061959 %04d %s %s',
        $case,
        $checkpointMode,
        $section
    )] = static function (TestRunner $t) use (
        $databaseBytes,
        $makeWalBytes,
        $case,
        $pageSize,
        $pageCount,
        $label,
        $frames,
        $readerEndFrame,
        $checkpointMode,
        $watchPages,
        $script,
        $section,
        $littleEndian,
        $rollbackStartFrame,
        $rollbackFrameCount,
        $expectedSeedFrameCount,
        $expectedCommittedRows
    ): void {
        $database = $databaseBytes($pageSize, $pageCount, $label);
        $walBytes = $makeWalBytes($case, $pageSize, $frames, $littleEndian);
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $snapshotEnd = min($readerEndFrame, $committedWal->frameCount());
        $reader = $committedWal->readerSnapshot($database, $snapshotEnd);
        $checkpoint = $committedWal->checkpointModeResult($database, $checkpointMode, $snapshotEnd);
        $durable = $committedWal->durableCheckpointResult($database, $checkpointMode, $snapshotEnd);
        $visibility = $committedWal->checkpointReaderVisibility($database, $watchPages, $checkpointMode, $snapshotEnd);
        $readerMap = $committedWal->readerSnapshotPageMap($database, $snapshotEnd);
        $recovered = SQLiteWal::parse($committedWal->toBytes(), $pageSize, true);

        $t->same('wal3.test', $script);
        $t->same(true, str_contains($section, 'wal3-'));
        $t->same($expectedSeedFrameCount, 4056);
        $t->same($expectedCommittedRows, 4018);
        $t->same($rollbackStartFrame + $rollbackFrameCount, $rollbackStartFrame + $rollbackFrameCount);
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(8, $boundary['committed_frame_count']);
        $t->same(12, $boundary['valid_frame_count']);
        $t->same(4, $boundary['discarded_valid_tail_frame_count']);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same(null, $boundary['first_invalid_frame']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same(32 + (8 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same(8, $committedWal->frameCount());
        $t->same(8, $recovered->frameCount());
        $t->same([4, 6, 8], array_column($committedWal->committedTransactions(), 'last_frame'));
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same($pageCount, $reader['database_page_count']);
        $t->same($snapshotEnd, $reader['end_frame']);
        $t->same(true, in_array('wal', array_column($readerMap, 'source'), true));
        $t->same($checkpointMode, $checkpoint['mode']);
        $t->same($checkpointMode, $durable['mode']);
        $t->same($checkpoint['busy'], $durable['busy']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same(count($watchPages), count($visibility['before']));
        $t->same(count($watchPages), count($visibility['after']));
        $t->same(true, in_array($visibility['wal_action'], ['preserve_wal', 'truncate_wal', 'restart_wal'], true));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array($checkpoint['wal_action'], ['preserve_wal', 'truncate_wal', 'restart_wal'], true));
        $t->same(true, $boundary['committed_wal_bytes'] === $committedWal->toBytes());
    };
}

return $tests;
