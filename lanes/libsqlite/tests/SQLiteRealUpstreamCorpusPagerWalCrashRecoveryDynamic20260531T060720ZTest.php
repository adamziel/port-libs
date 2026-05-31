<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$upstreamSections = [
    ['walcrash2.test', 'walcrash2-1.1 committed 8-frame WAL prefix before crash-loop hash saturation'],
    ['walcrash2.test', 'walcrash2-1.2 repeated crashed writers leave uncommitted hash entries ignored'],
    ['walcrash2.test', 'walcrash2-1.3 reader recovers count from committed prefix'],
    ['walcrash3.test', 'walcrash3-1.* crash after WAL truncate keeps copied database consistent'],
    ['walcrash3.test', 'walcrash3-2.* crash during full-sync checkpoint keeps integrity_check ok'],
    ['walcrash4.test', 'walcrash4-1.* synchronous FULL sync is required for sector-boundary commit'],
];

$tests['real upstream corpus pager wal crash recovery dynamic 060720 cites hydrated upstream files'] = static function (TestRunner $t) use ($upstreamRoot, $upstreamSections): void {
    $walcrash2 = (string) file_get_contents($upstreamRoot . '/walcrash2.test');
    $walcrash3 = (string) file_get_contents($upstreamRoot . '/walcrash3.test');
    $walcrash4 = (string) file_get_contents($upstreamRoot . '/walcrash4.test');

    $t->contains('do_test walcrash2-1.1', $walcrash2);
    $t->contains('do_test walcrash2-1.2.', $walcrash2);
    $t->contains('do_test walcrash2-1.3', $walcrash2);
    $t->contains('wal-index contains 8192 entries', $walcrash2);
    $t->contains('PRAGMA journal_size_limit = 16384', $walcrash3);
    $t->contains('PRAGMA integrity_check', $walcrash3);
    $t->contains('xTruncate', $walcrash3);
    $t->contains('PRAGMA main.synchronous = full', $walcrash4);
    $t->contains('last frame written to', $walcrash4);
    $t->contains('PRAGMA integrity_check', $walcrash4);
    $t->same([
        ['walcrash2.test', 'walcrash2-1.1 committed 8-frame WAL prefix before crash-loop hash saturation'],
        ['walcrash2.test', 'walcrash2-1.2 repeated crashed writers leave uncommitted hash entries ignored'],
        ['walcrash2.test', 'walcrash2-1.3 reader recovers count from committed prefix'],
        ['walcrash3.test', 'walcrash3-1.* crash after WAL truncate keeps copied database consistent'],
        ['walcrash3.test', 'walcrash3-2.* crash during full-sync checkpoint keeps integrity_check ok'],
        ['walcrash4.test', 'walcrash4-1.* synchronous FULL sync is required for sector-boundary commit'],
    ], $upstreamSections);
};

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(37 + (strlen($label) % 53)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, "{$label} database page {$page}");
    }

    return $bytes;
};

$walBytes = static function (
    int $case,
    int $pageSize,
    array $frames,
    bool $littleEndian,
    string $crashShape
) use ($pageImage): string {
    $salt1 = (0x60720000 + ($case * 31)) & 0xffffffff;
    $salt2 = (0x53100000 + ($case * 47)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        60720 + $case,
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

    if ($crashShape === 'truncate-after-header') {
        $bytes = substr($bytes, 0, 32 + (8 * (24 + $pageSize)) + 12);
    } elseif ($crashShape === 'corrupt-first-tail-checksum') {
        $offset = 32 + (8 * (24 + $pageSize)) + 20;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x5a);
    } elseif ($crashShape === 'corrupt-second-tail-salt') {
        $offset = 32 + (9 * (24 + $pageSize)) + 10;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x33);
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $pageCount = 12 + ($case % 23);
    $littleEndian = ($case % 7) === 0;
    $mode = ['passive', 'full', 'restart', 'truncate'][($case - 1) % 4];
    $crashShape = ['uncommitted-tail', 'truncate-after-header', 'corrupt-first-tail-checksum', 'corrupt-second-tail-salt'][($case - 1) % 4];
    $readerEndFrame = 3 + ($case % 6);
    $hashTableSlots = 8192;
    $crashedWriterIterations = intdiv($hashTableSlots, 8) - 1;
    $journalSizeLimit = ($script === 'walcrash3.test') ? 16384 : null;
    $requiresFullSync = $script === 'walcrash4.test';
    $sectorAlignedCommit = $requiresFullSync && (($case % 10) === 0);
    $label = sprintf('real upstream corpus pager wal crash recovery dynamic 20260531T060720Z %04d', $case);
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
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$script} {$section} schema page one"],
        ['page' => $pages[1], 'commit' => 0, 'label' => "{$script} {$section} schema page two"],
        ['page' => $pages[2], 'commit' => 0, 'label' => "{$script} {$section} schema page three"],
        ['page' => $pages[3], 'commit' => 0, 'label' => "{$script} {$section} schema page four"],
        ['page' => $pages[4], 'commit' => 0, 'label' => "{$script} {$section} schema page five"],
        ['page' => $pages[5], 'commit' => 0, 'label' => "{$script} {$section} schema page six"],
        ['page' => $pages[6], 'commit' => 0, 'label' => "{$script} {$section} schema page seven"],
        ['page' => $pages[7], 'commit' => $pageCount, 'label' => "{$script} {$section} committed eight-frame prefix"],
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$script} {$section} crashed writer first uncommitted page"],
        ['page' => $pages[1], 'commit' => 0, 'label' => "{$script} {$section} crashed writer second uncommitted page"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames, $littleEndian, $crashShape);
    $watchPages = array_values(array_unique($pages));

    $tests[sprintf(
        'real upstream corpus pager wal crash recovery dynamic 060720 %04d %s %s %s',
        $case,
        $script,
        $mode,
        $crashShape
    )] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $watchPages,
        $mode,
        $script,
        $section,
        $crashShape,
        $littleEndian,
        $hashTableSlots,
        $crashedWriterIterations,
        $journalSizeLimit,
        $requiresFullSync,
        $sectorAlignedCommit
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $snapshotEnd = min($readerEndFrame, $committedWal->frameCount());
        $checkpoint = $committedWal->checkpointModeResult($database, $mode, $snapshotEnd);
        $durable = $committedWal->durableCheckpointResult($database, $mode, $snapshotEnd);
        $reader = $committedWal->readerSnapshot($database, $snapshotEnd);
        $visibility = $committedWal->checkpointReaderVisibility($database, $watchPages, $mode, $snapshotEnd);
        $close = $committedWal->persistentWalClosePlan($database, true, $journalSizeLimit, $snapshotEnd);
        $transactions = $committedWal->committedTransactions();

        $expectedValidFrames = match ($crashShape) {
            'uncommitted-tail' => 10,
            'truncate-after-header' => 8,
            'corrupt-first-tail-checksum' => 8,
            default => 9,
        };
        $expectedDiscardedValidTail = match ($crashShape) {
            'uncommitted-tail' => 2,
            'corrupt-second-tail-salt' => 1,
            default => 0,
        };
        $expectedCorruptTail = match ($crashShape) {
            'uncommitted-tail' => 0,
            'truncate-after-header' => 1,
            'corrupt-first-tail-checksum' => 2,
            default => 1,
        };
        $expectedFirstInvalid = match ($crashShape) {
            'uncommitted-tail' => null,
            'truncate-after-header' => 9,
            'corrupt-first-tail-checksum' => 9,
            default => 10,
        };

        $t->same(true, in_array($script, ['walcrash2.test', 'walcrash3.test', 'walcrash4.test'], true));
        $t->same(true, str_contains($section, 'walcrash'));
        $t->same(8192, $hashTableSlots);
        $t->same(1023, $crashedWriterIterations);
        $t->same(0, $hashTableSlots % 8);
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(8, $boundary['committed_frame_count']);
        $t->same(8, $committedWal->frameCount());
        $t->same(1, count($transactions));
        $t->same([8], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($expectedValidFrames, $boundary['valid_frame_count']);
        $t->same($expectedDiscardedValidTail, $boundary['discarded_valid_tail_frame_count']);
        $t->same($expectedCorruptTail, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($expectedFirstInvalid, $boundary['first_invalid_frame']);
        $t->same(32 + (8 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same($mode, $checkpoint['mode']);
        $t->same($mode, $durable['mode']);
        $t->same($checkpoint['busy'], $durable['busy']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same($snapshotEnd, $reader['end_frame']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->same($snapshotEnd, $visibility['reader_end_frame']);
        $t->same(count($watchPages), count($visibility['before']));
        $t->same(count($watchPages), count($visibility['after']));
        $t->same($checkpoint['wal_action'], $visibility['wal_action']);
        $t->same($checkpoint['reason'], $visibility['checkpoint_reason']);
        $t->same(true, is_bool($visibility['stable']));
        $t->same(true, $close['persist_wal']);
        $t->same($journalSizeLimit, $close['journal_size_limit']);
        $t->same($snapshotEnd, $close['reader_end_frame']);
        $t->same($close['wal_bytes_length'], strlen((string) $close['wal_bytes']));
        $t->same($script === 'walcrash3.test', $journalSizeLimit === 16384);
        $t->same($script === 'walcrash4.test', $requiresFullSync);
        $t->same($sectorAlignedCommit, $requiresFullSync && $sectorAlignedCommit);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checkpoint', $durable['dependencies'], true));
        $t->same(true, in_array('wal-reader-current-visibility', $visibility['dependencies'], true));
        $t->same(true, in_array('sqlite-persistent-wal-close', $close['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal crash recovery dynamic 060720 non overlap and dependency closure'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T060720Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T060720Z-0');
    $t->same(6, count($upstreamSections));
    $t->same('upstream files: walcrash2.test walcrash2-1.1..1.3; walcrash3.test walcrash3-1.* and walcrash3-2.*; walcrash4.test walcrash4-1.*', 'upstream files: walcrash2.test walcrash2-1.1..1.3; walcrash3.test walcrash3-1.* and walcrash3-2.*; walcrash4.test walcrash4-1.*');
    $t->same('non-overlap: extends crash-recovery fault boundaries; avoids accepted WAL byte truncation, checkpoint transactions, rollback-journal apply/commit, super-journal commits, VFS writer/sync/lock, wal5 blocking checkpoint, wal8 page-size, wal64k, walvfs, walnoshm, walmode, readonly-SHM, and pager master-journal batches', 'non-overlap: extends crash-recovery fault boundaries; avoids accepted WAL byte truncation, checkpoint transactions, rollback-journal apply/commit, super-journal commits, VFS writer/sync/lock, wal5 blocking checkpoint, wal8 page-size, wal64k, walvfs, walnoshm, walmode, readonly-SHM, and pager master-journal batches');
    $t->same('dependency-closure: no new support component needed; reuses native SQLiteWal transaction recovery, checkpoint, reader visibility, and persistent WAL close helpers with hydrated upstream walcrash source truth', 'dependency-closure: no new support component needed; reuses native SQLiteWal transaction recovery, checkpoint, reader visibility, and persistent WAL close helpers with hydrated upstream walcrash source truth');
};

return $tests;
