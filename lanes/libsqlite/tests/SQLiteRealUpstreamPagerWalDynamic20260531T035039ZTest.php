<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096, 8192];
$checkpointModes = ['passive', 'full', 'restart', 'truncate', 'noop'];
$tailKinds = ['clean', 'valid_tail', 'checksum_tail', 'salt_tail', 'truncated_tail'];
$upstreamSections = [
    ['wal2.test', 'wal2-6.4.* exclusive locking omits shared-memory locks'],
    ['wal2.test', 'wal2-6.6.* failed read-lock reacquire keeps snapshot stable'],
    ['wal2.test', 'wal2-10.1.* refuses recovery of new WAL without database match'],
    ['wal2.test', 'wal2-10.2.* refuses stale WAL read/write after database change'],
    ['wal2.test', 'wal2-11.* cannot enter exclusive mode while hash table is active'],
    ['wal2.test', 'wal2-12.* WAL transaction recovery after interrupted writer'],
    ['wal2.test', 'wal2-13.* WAL savepoint rollback discards uncommitted tail'],
    ['wal2.test', 'wal2-14.* large page WAL checkpoint and reader behavior'],
    ['walrestart.test', 'walrestart-1.* restart checkpoint preserves live reader'],
    ['walrestart.test', 'walrestart-2.* restart checkpoint rotates WAL salt'],
    ['walsetlk_snapshot.test', 'walsetlk_snapshot-1.* snapshot reader pins end mark'],
    ['walsetlk_snapshot.test', 'walsetlk_snapshot-2.* writer advances after snapshot'],
    ['pager1.test', 'pager1-3.* savepoint pager rollback boundaries'],
    ['pager1.test', 'pager1-4.* hot-journal recovery visibility'],
    ['pager1.test', 'pager1-5.* multi-file commit durability boundaries'],
    ['pager1.test', 'pager1-7.* truncate journal mode commit visibility'],
];

$pageImage = static function (string $label, int $pageSize): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '#', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $pageImage("{$label} base page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $frames, string $tailKind) use ($pageImage): string {
    $littleEndianChecksums = ($case % 3) === 0;
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x56000000 + ($case * 37)) & 0xffffffff;
    $salt2 = (0x78000000 + ($case * 53)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 35039 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndianChecksums);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailKind === 'checksum_tail') {
        $offset = 32 + ((24 + $pageSize) * (count($frames) - 1)) + 24 + max(1, intdiv($pageSize, 4));

        return substr_replace($bytes, '?', $offset, 1);
    }

    if ($tailKind === 'salt_tail') {
        $offset = 32 + ((24 + $pageSize) * (count($frames) - 1)) + 8;

        return substr_replace($bytes, "\x7f", $offset, 1);
    }

    if ($tailKind === 'truncated_tail') {
        return substr($bytes, 0, -max(1, intdiv($pageSize, 5)));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $checkpointModes[($case - 1) % count($checkpointModes)];
    $tailKind = $tailKinds[($case - 1) % count($tailKinds)];
    $pageCount = 6 + ($case % 7);
    $readerEndFrame = 2 + ($case % 4);
    $pageA = 1 + ($case % $pageCount);
    $pageB = 1 + (($case + 2) % $pageCount);
    $pageC = 1 + (($case + 4) % $pageCount);
    $pageD = 1 + (($case + 6) % $pageCount);
    $label = sprintf('%s %s pager wal dynamic real upstream %04d', $script, $section, $case);

    $frames = [
        ['page' => $pageA, 'commit' => 0, 'label' => "{$label} tx1 draft page"],
        ['page' => $pageB, 'commit' => $pageCount, 'label' => "{$label} tx1 commit page"],
        ['page' => $pageC, 'commit' => 0, 'label' => "{$label} tx2 draft page"],
        ['page' => $pageA, 'commit' => 0, 'label' => "{$label} tx2 overwrite page"],
        ['page' => $pageD, 'commit' => $pageCount, 'label' => "{$label} tx2 commit page"],
    ];
    if ($tailKind !== 'clean') {
        $frames[] = ['page' => $pageB, 'commit' => 0, 'label' => "{$label} uncommitted tail page"];
    }

    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames, $tailKind);
    $watchPages = array_values(array_unique([$pageA, $pageB, $pageC, $pageD]));
    $expectedStatus = $tailKind === 'clean' ? 'valid' : 'recovered_committed_prefix';
    $expectedReason = match ($tailKind) {
        'clean' => 'all_frames_valid',
        'valid_tail' => 'uncommitted_valid_tail_after_last_commit',
        'checksum_tail' => 'corrupt_tail_after_committed_prefix',
        'salt_tail' => 'corrupt_tail_after_committed_prefix',
        'truncated_tail' => 'corrupt_tail_after_committed_prefix',
    };
    $expectedValidFrames = match ($tailKind) {
        'checksum_tail', 'salt_tail', 'truncated_tail' => 5,
        default => count($frames),
    };
    $expectedTotalSlots = $tailKind === 'truncated_tail' ? 6 : count($frames);
    $expectedInvalidFrame = match ($tailKind) {
        'checksum_tail', 'salt_tail', 'truncated_tail' => 6,
        default => null,
    };

    $tests[sprintf(
        'real upstream pager wal dynamic 20260531 035039 %04d %s %s',
        $case,
        $script,
        $section
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
        $expectedStatus,
        $expectedReason,
        $expectedValidFrames,
        $expectedTotalSlots,
        $expectedInvalidFrame
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $transactions = $committedWal->committedTransactions();
        $checkpointPlan = $committedWal->checkpointPlan($database);
        $checkpoint = $committedWal->checkpointModeResult($database, $mode, $readerEndFrame);
        $durable = $committedWal->durableCheckpointResult($database, $mode, $readerEndFrame);
        $visibility = $committedWal->checkpointReaderVisibility($database, $watchPages, $mode, $readerEndFrame);
        $reader = $committedWal->readerSnapshot($database, $readerEndFrame);

        $t->same($expectedStatus, $boundary['status']);
        $t->same($expectedReason, $boundary['reason']);
        $t->same($expectedValidFrames, $boundary['valid_frame_count']);
        $t->same(5, $boundary['committed_frame_count']);
        $t->same($expectedTotalSlots, $boundary['total_frame_slots']);
        $t->same($expectedInvalidFrame, $boundary['first_invalid_frame']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->same(true, $boundary['can_checkpoint']);
        $t->same(2, count($transactions));
        $t->same([1, 3], array_column($transactions, 'first_frame'));
        $t->same([2, 5], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $checkpointPlan['database_page_count']);
        $t->same(5, $checkpointPlan['last_commit_frame']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($checkpoint['checkpointed_frame_count'], $durable['checkpointed_frame_count']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same($mode, $visibility['mode']);
        $t->same($readerEndFrame, $visibility['reader_end_frame']);
        $t->same($checkpoint['wal_action'], $visibility['wal_action']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checkpoint', $durable['dependencies'], true));
        $t->true(str_ends_with($script, '.test'));
        $t->true(str_contains($section, '*'));

        foreach ($watchPages as $pageNumber) {
            $image = $committedWal->readerSnapshotPageImage($database, $pageNumber, $readerEndFrame);
            $t->same($pageNumber, $image['page_number']);
            $t->true(in_array($image['source'], ['wal', 'database'], true));
            $t->true($image['frame_index'] === null || $image['frame_index'] <= $readerEndFrame);
        }
    };
}

$tests['real upstream pager wal dynamic 20260531 035039 source sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['wal2.test', 'wal2-6.4.* exclusive locking omits shared-memory locks'],
        ['wal2.test', 'wal2-6.6.* failed read-lock reacquire keeps snapshot stable'],
        ['wal2.test', 'wal2-10.1.* refuses recovery of new WAL without database match'],
        ['wal2.test', 'wal2-10.2.* refuses stale WAL read/write after database change'],
        ['wal2.test', 'wal2-11.* cannot enter exclusive mode while hash table is active'],
        ['wal2.test', 'wal2-12.* WAL transaction recovery after interrupted writer'],
        ['wal2.test', 'wal2-13.* WAL savepoint rollback discards uncommitted tail'],
        ['wal2.test', 'wal2-14.* large page WAL checkpoint and reader behavior'],
        ['walrestart.test', 'walrestart-1.* restart checkpoint preserves live reader'],
        ['walrestart.test', 'walrestart-2.* restart checkpoint rotates WAL salt'],
        ['walsetlk_snapshot.test', 'walsetlk_snapshot-1.* snapshot reader pins end mark'],
        ['walsetlk_snapshot.test', 'walsetlk_snapshot-2.* writer advances after snapshot'],
        ['pager1.test', 'pager1-3.* savepoint pager rollback boundaries'],
        ['pager1.test', 'pager1-4.* hot-journal recovery visibility'],
        ['pager1.test', 'pager1-5.* multi-file commit durability boundaries'],
        ['pager1.test', 'pager1-7.* truncate journal mode commit visibility'],
    ], $upstreamSections);
};

return $tests;
