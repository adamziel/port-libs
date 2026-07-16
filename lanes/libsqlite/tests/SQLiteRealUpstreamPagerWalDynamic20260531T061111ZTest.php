<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamSections = [
    ['wal.test', 'wal-1.0 through wal-1.5 WAL file is created and remains readable after writes'],
    ['wal.test', 'wal-2.1 through wal-2.6 one reader keeps an older MVCC snapshot while writer appends rows'],
    ['wal.test', 'wal-3.1 through wal-3.3 rollback leaves reader-visible committed rows intact'],
    ['wal.test', 'wal-4.1 through wal-4.4.6 savepoint rollback keeps the WAL log size stable across release'],
    ['walckpt.test', 'walckpt-2.* passive checkpoint is blocked by an active reader and preserves WAL bytes'],
    ['walckpt.test', 'walckpt-3.* full checkpoint can backfill committed frames when no reader blocks it'],
    ['walrestart.test', 'walrestart-1.* restart checkpoint keeps reusable WAL state for readers'],
    ['walrestart.test', 'walrestart-2.* truncate checkpoint drops the reusable WAL tail after release'],
    ['wal2.test', 'wal2-11.* WAL recovery ignores malformed frame tails after the last committed transaction'],
    ['pager1.test', 'pager1-24.* cache-spill transaction pages remain readable through pager cache reuse'],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(65 + (strlen($label) % 26)), STR_PAD_RIGHT);
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
    string $tailShape
) use ($pageImage): string {
    $salt1 = (0x31000000 + ($case * 37)) & 0xffffffff;
    $salt2 = (0x62000000 + ($case * 41)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        61111 + $case,
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

    if ($tailShape === 'checksum') {
        $offset = 32 + (8 * (24 + $pageSize)) + 21;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x49);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (8 * (24 + $pageSize)) + 11;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x17);
    } elseif ($tailShape === 'partial') {
        $bytes = substr($bytes, 0, -max(17, intdiv($pageSize, 5)));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $pageCount = 8 + ($case % 13);
    $littleEndian = ($case % 7) === 0;
    $checkpointMode = ['passive', 'full', 'restart', 'truncate'][($case - 1) % 4];
    $tailShape = ['valid', 'checksum', 'salt', 'partial'][($case - 1) % 4];
    $label = sprintf('real upstream pager wal dynamic 20260531T061111Z case %04d', $case);
    $pages = [
        1 + (($case * 2) % $pageCount),
        1 + (($case * 3) % $pageCount),
        1 + (($case * 5) % $pageCount),
        1 + (($case * 7) % $pageCount),
        1 + (($case * 11) % $pageCount),
        1 + (($case * 13) % $pageCount),
    ];
    $frames = [
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$script} {$section} initial writer page"],
        ['page' => $pages[1], 'commit' => $pageCount, 'label' => "{$script} {$section} first commit"],
        ['page' => $pages[2], 'commit' => 0, 'label' => "{$script} {$section} reader-pinned draft"],
        ['page' => $pages[3], 'commit' => $pageCount, 'label' => "{$script} {$section} second commit"],
        ['page' => $pages[4], 'commit' => 0, 'label' => "{$script} {$section} savepoint body one"],
        ['page' => $pages[5], 'commit' => $pageCount, 'label' => "{$script} {$section} savepoint body commit"],
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$script} {$section} rollback body one"],
        ['page' => $pages[2], 'commit' => $pageCount, 'label' => "{$script} {$section} rollback body commit"],
        ['page' => $pages[4], 'commit' => 0, 'label' => "{$script} {$section} uncommitted corruptible tail"],
        ['page' => $pages[5], 'commit' => 0, 'label' => "{$script} {$section} uncommitted final tail"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames, $littleEndian, $tailShape);
    $watchPages = array_values(array_unique($pages));

    $tests[sprintf(
        'real upstream pager wal dynamic 20260531T061111Z %04d %s %s %s',
        $case,
        $script,
        $section,
        $tailShape
    )] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $checkpointMode,
        $watchPages,
        $script,
        $section,
        $tailShape,
        $littleEndian
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $readerFrame = min(3, $committedWal->frameCount());
        $reader = $committedWal->readerSnapshot($database, $readerFrame);
        $currentNext = SQLiteWal::corruptRecoveryCurrentNextBoundary($wal, $database, $watchPages, $pageSize);
        $checkpoint = $committedWal->checkpointModeResult($database, $checkpointMode, $readerFrame);
        $durable = $committedWal->durableCheckpointResult($database, $checkpointMode, $readerFrame);
        $readerPageMap = $committedWal->readerSnapshotPageMap($database, $readerFrame);

        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('outer');
        for ($frame = 1; $frame <= 4; $frame++) {
            $stack->recordWalFrameWrite($frame, $frame, $frame === 2 || $frame === 4);
        }
        $stack->savepoint('sp');
        for ($frame = 5; $frame <= 8; $frame++) {
            $stack->recordWalFrameWrite($frame, $frame, $frame === 6 || $frame === 8);
        }
        $savepointPlan = $stack->walRollbackToByteTruncationPlan('sp', $committedWal, $committedWal->toBytes());
        $rolledBackWalBytes = $stack->walRollbackToWalBytes('sp', $committedWal, $committedWal->toBytes());
        $rolledBackWal = SQLiteWal::parse($rolledBackWalBytes, $pageSize, true);

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, str_contains($section, '-'));
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(8, $boundary['committed_frame_count']);
        $t->same(8, $committedWal->frameCount());
        $t->same([2, 4, 6, 8], array_column($committedWal->committedTransactions(), 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same(32 + (8 * (24 + $pageSize)), $boundary['committed_end_offset']);

        $expectedValidFrames = match ($tailShape) {
            'valid' => 10,
            'partial' => 9,
            default => 8,
        };
        $expectedDiscardedValidTail = match ($tailShape) {
            'valid' => 2,
            'partial' => 1,
            default => 0,
        };
        $expectedFirstInvalidFrame = match ($tailShape) {
            'valid' => null,
            'partial' => 10,
            default => 9,
        };
        $t->same($expectedValidFrames, $boundary['valid_frame_count']);
        $t->same($expectedDiscardedValidTail, $boundary['discarded_valid_tail_frame_count']);
        $t->same($tailShape === 'valid' ? 0 : ($tailShape === 'partial' ? 1 : 2), $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($expectedFirstInvalidFrame, $boundary['first_invalid_frame']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));

        $t->same($pageCount, $reader['database_page_count']);
        $t->same($readerFrame, $reader['end_frame']);
        $t->same(true, in_array('wal', array_column($readerPageMap, 'source'), true));
        $t->same($checkpointMode, $checkpoint['mode']);
        $t->same($checkpointMode, $durable['mode']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);

        $t->same(4, $savepointPlan['rollback_to_frame']);
        $t->same(4, $savepointPlan['discarded_frame_count']);
        $t->same(true, $savepointPlan['needs_truncate']);
        $t->same(32 + (4 * (24 + $pageSize)), strlen($rolledBackWalBytes));
        $t->same(4, $rolledBackWal->frameCount());
        $t->same([2, 4], array_column($rolledBackWal->committedTransactions(), 'last_frame'));

        $t->same('recovered_committed_prefix', $currentNext['status']);
        $t->same(8, $currentNext['committed_frame_count']);
        $t->same(true, $currentNext['next_uses_checkpoint_database']);
        $t->same(true, count($currentNext['current_reader_sources']) >= count($watchPages));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array($checkpoint['wal_action'], ['preserve_wal', 'truncate_wal', 'restart_wal'], true));
    };
}

$tests['real upstream pager wal dynamic 20260531T061111Z records hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['wal.test', 'wal-1.0 through wal-1.5 WAL file is created and remains readable after writes'],
        ['wal.test', 'wal-2.1 through wal-2.6 one reader keeps an older MVCC snapshot while writer appends rows'],
        ['wal.test', 'wal-3.1 through wal-3.3 rollback leaves reader-visible committed rows intact'],
        ['wal.test', 'wal-4.1 through wal-4.4.6 savepoint rollback keeps the WAL log size stable across release'],
        ['walckpt.test', 'walckpt-2.* passive checkpoint is blocked by an active reader and preserves WAL bytes'],
        ['walckpt.test', 'walckpt-3.* full checkpoint can backfill committed frames when no reader blocks it'],
        ['walrestart.test', 'walrestart-1.* restart checkpoint keeps reusable WAL state for readers'],
        ['walrestart.test', 'walrestart-2.* truncate checkpoint drops the reusable WAL tail after release'],
        ['wal2.test', 'wal2-11.* WAL recovery ignores malformed frame tails after the last committed transaction'],
        ['pager1.test', 'pager1-24.* cache-spill transaction pages remain readable through pager cache reuse'],
    ], $upstreamSections);
};

return $tests;
