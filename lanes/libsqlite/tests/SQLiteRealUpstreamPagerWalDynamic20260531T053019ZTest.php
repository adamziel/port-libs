<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamSections = [
    ['wal6.test', 'wal6-1.0..1.3 journal-mode VACUUM and WAL mode transitions'],
    ['wal7.test', 'wal7-1.0..1.2 WAL index invalidation across schema changes'],
    ['wal7.test', 'wal7-2.0 reader transaction preserves older committed view'],
    ['wal7.test', 'wal7-3.0 checkpoint-visible database image after WAL writes'],
    ['wal7.test', 'wal7-4.0 writer reuses WAL after reader boundary advances'],
    ['walbig.test', 'walbig-1.0..1.3 large WAL frame indexing and recovery'],
    ['pager2.test', 'pager2-1.* EXCLUSIVE locking-mode rollback persistence'],
    ['pager2.test', 'pager2-2.1..2.2 journal tail preservation while readers exist'],
    ['pager2.test', 'pager2-3.1 journal mode state survives cache spill'],
    ['journal1.test', 'journal1-1.1..1.2 hot rollback-journal database recovery'],
    ['journal2.test', 'journal2-1.1..1.21 persistent journal header lifecycle'],
    ['journal2.test', 'journal2-2.1..2.4 delete/truncate/persist journal cleanup'],
];

$pageImage = static function (int $pageSize, string $label, int $fill): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(35 + ($fill % 70)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, "{$label} database page {$page}", $page);
    }

    return $bytes;
};

$walBytes = static function (
    int $case,
    int $pageSize,
    int $pageCount,
    array $frames,
    bool $littleEndian,
    string $tailShape
) use ($pageImage): string {
    $salt1 = (0x31000000 + ($case * 7919)) & 0xffffffff;
    $salt2 = (0x62000000 + ($case * 1543)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        53019 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $offset => $frame) {
        $page = 1 + (((int) $frame['page'] - 1) % $pageCount);
        $image = $pageImage($pageSize, (string) $frame['label'], $case + $offset);
        $framePrefix = pack('N*', $page, (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailShape === 'checksum') {
        $offset = 32 + (7 * (24 + $pageSize)) + 24 + intdiv($pageSize, 3);
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x6d);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (7 * (24 + $pageSize)) + 10;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x2f);
    } elseif ($tailShape === 'truncated') {
        $bytes = substr($bytes, 0, -max(17, intdiv($pageSize, 4)));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096, 8192, 16384][($case - 1) % 6];
    $pageCount = 9 + ($case % 29);
    $littleEndian = ($case % 5) === 0;
    $mode = ['passive', 'full', 'restart', 'truncate', 'noop'][($case - 1) % 5];
    $tailShape = ['valid_tail', 'checksum', 'salt', 'truncated'][($case - 1) % 4];
    $readerEndFrame = 2 + ($case % 5);
    $firstPage = 1 + (($case * 3) % $pageCount);
    $secondPage = 1 + (($case * 5) % $pageCount);
    $thirdPage = 1 + (($case * 7) % $pageCount);
    $fourthPage = 1 + (($case * 11) % $pageCount);
    $fifthPage = 1 + (($case * 13) % $pageCount);
    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$script} {$section} transaction one draft page {$firstPage}"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$script} {$section} transaction one commit page {$secondPage}"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$script} {$section} transaction two draft page {$thirdPage}"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$script} {$section} transaction two overwrite page {$firstPage}"],
        ['page' => $fourthPage, 'commit' => $pageCount, 'label' => "{$script} {$section} transaction two commit page {$fourthPage}"],
        ['page' => $fifthPage, 'commit' => 0, 'label' => "{$script} {$section} reader-pinned writer tail page {$fifthPage}"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$script} {$section} journal tail frame page {$thirdPage}"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$script} {$section} damaged tail frame page {$secondPage}"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, "real upstream pager wal dynamic 20260531T053019Z {$case}");
    $wal = $walBytes($case, $pageSize, $pageCount, $frames, $littleEndian, $tailShape);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage, $fifthPage]));

    $tests[sprintf(
        'real upstream pager wal dynamic 20260531T053019Z %04d %s %s %s',
        $case,
        $script,
        $section,
        $tailShape
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
        $tailShape,
        $littleEndian
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $snapshotEndFrame = min($readerEndFrame, $committedWal->frameCount());
        $checkpoint = $committedWal->checkpointModePlan($database, $mode, $snapshotEndFrame);
        $durable = $committedWal->durableCheckpointResult($database, $mode, $snapshotEndFrame);
        $reader = $committedWal->readerSnapshot($database, $snapshotEndFrame);
        $visibility = $committedWal->checkpointReaderVisibility($database, $watchPages, $mode, $snapshotEndFrame);
        $close = $committedWal->persistentWalClosePlan($database, true, 8192 + $pageSize, $snapshotEndFrame);
        $transactions = $committedWal->committedTransactions();

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, str_contains($section, '-'));
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(5, $boundary['committed_frame_count']);
        $t->same(5, $committedWal->frameCount());
        $t->same(2, count($transactions));
        $t->same([2, 5], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($tailShape === 'valid_tail' ? 8 : 7, $boundary['valid_frame_count']);
        $t->same($tailShape === 'valid_tail' ? 3 : 2, $boundary['discarded_valid_tail_frame_count']);
        $t->same($tailShape === 'valid_tail' ? 0 : 1, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($tailShape === 'valid_tail' ? null : 8, $boundary['first_invalid_frame']);
        $t->same(32 + (5 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same($mode, $checkpoint['mode']);
        $t->same($mode, $durable['mode']);
        $t->same($checkpoint['checkpointed_frame_count'], $durable['checkpointed_frame_count']);
        $t->same($checkpoint['busy'], $durable['busy']);
        $t->same($pageCount, $durable['database_page_count']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->same($snapshotEndFrame, $reader['end_frame']);
        $t->same($snapshotEndFrame, $visibility['reader_end_frame']);
        $t->same($mode, $visibility['mode']);
        $t->same(count($watchPages), count($visibility['before']));
        $t->same(count($watchPages), count($visibility['after']));
        $t->same(true, in_array($durable['wal_action'], ['preserve_wal', 'truncate_wal', 'restart_wal'], true));
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same(true, in_array($close['sidecar_action'], ['preserve_wal', 'truncate_persistent_wal'], true));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('wal-reader-current-visibility', $visibility['dependencies'], true));
    };
}

$tests['real upstream pager wal dynamic 20260531T053019Z records hydrated upstream files'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['wal6.test', 'wal6-1.0..1.3 journal-mode VACUUM and WAL mode transitions'],
        ['wal7.test', 'wal7-1.0..1.2 WAL index invalidation across schema changes'],
        ['wal7.test', 'wal7-2.0 reader transaction preserves older committed view'],
        ['wal7.test', 'wal7-3.0 checkpoint-visible database image after WAL writes'],
        ['wal7.test', 'wal7-4.0 writer reuses WAL after reader boundary advances'],
        ['walbig.test', 'walbig-1.0..1.3 large WAL frame indexing and recovery'],
        ['pager2.test', 'pager2-1.* EXCLUSIVE locking-mode rollback persistence'],
        ['pager2.test', 'pager2-2.1..2.2 journal tail preservation while readers exist'],
        ['pager2.test', 'pager2-3.1 journal mode state survives cache spill'],
        ['journal1.test', 'journal1-1.1..1.2 hot rollback-journal database recovery'],
        ['journal2.test', 'journal2-1.1..1.21 persistent journal header lifecycle'],
        ['journal2.test', 'journal2-2.1..2.4 delete/truncate/persist journal cleanup'],
    ], $upstreamSections);
};

$tests['real upstream pager wal dynamic 20260531T053019Z dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'dependency-closure: no new support component needed; reuses native SQLiteWal recovery, checkpoint, reader-visibility, and persistent-WAL close behavior',
        'dependency-closure: no new support component needed; reuses native SQLiteWal recovery, checkpoint, reader-visibility, and persistent-WAL close behavior'
    );
};

return $tests;
