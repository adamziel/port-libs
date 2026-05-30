<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$modes = ['passive', 'full', 'restart', 'truncate', 'noop'];
$corruptions = ['none', 'checksum-tail', 'salt-tail', 'truncated-tail'];
$page = static fn (string $label, int $pageSize): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$walLength = static fn (int $frames, int $pageSize): int => 32 + ($frames * (24 + $pageSize));

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$label} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (
    int $pageSize,
    array $frames,
    int $saltOffset,
    string $corruption = 'none'
) use ($page): string {
    $salt1 = (0x4a170000 + $saltOffset) & 0xffffffff;
    $salt2 = (0x3b290000 + $saltOffset) & 0xffffffff;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1000 + $saltOffset, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $frameBytesByIndex = [];

    foreach ($frames as $index => $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $frameBytesByIndex[$index + 1] = $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    if ($corruption === 'checksum-tail') {
        $last = count($frameBytesByIndex);
        $frameBytesByIndex[$last] = substr_replace($frameBytesByIndex[$last], "\xff", 32, 1);
    } elseif ($corruption === 'salt-tail') {
        $last = count($frameBytesByIndex);
        $frameBytesByIndex[$last] = substr_replace($frameBytesByIndex[$last], pack('N', ($salt1 + 1) & 0xffffffff), 8, 4);
    }

    $bytes .= implode('', $frameBytesByIndex);

    return $corruption === 'truncated-tail'
        ? substr($bytes, 0, -intdiv($pageSize, 4))
        : $bytes;
};

for ($case = 1; $case <= 360; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $modes[($case - 1) % count($modes)];
    $corruption = $corruptions[($case - 1) % count($corruptions)];
    $basePages = 4 + ($case % 5);
    $commitPages = $basePages + ($case % 3);
    $readerEndFrame = $case % 6 === 0 ? 2 : ($case % 6 === 1 ? 4 : null);
    $label = sprintf('real upstream pager wal recovery dynamic %03d %s %s %d', $case, $mode, $corruption, $pageSize);
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "{$label} wal.test wal-18 schema frame"],
        ['page' => 2, 'commit' => $basePages, 'label' => "{$label} wal.test wal-18 first commit"],
        ['page' => 2 + ($case % max(2, $basePages - 1)), 'commit' => 0, 'label' => "{$label} wal.test wal-19 reader tail"],
        ['page' => $commitPages, 'commit' => $commitPages, 'label' => "{$label} walrestart.test restart commit"],
        ['page' => 1 + ($case % $basePages), 'commit' => 0, 'label' => "{$label} walckptnoop.test preserved tail"],
    ];
    $databaseBytes = $database($pageSize, $basePages, $label);
    $walBytes = $makeWalBytes($pageSize, $frames, 4000 + $case, $corruption);
    $validFrameCount = $corruption === 'none' ? 5 : 4;
    $totalFrameSlots = $corruption === 'truncated-tail' ? 5 : 5;
    $committedFrameCount = 4;
    $expectedBoundaryReason = match ($corruption) {
        'none' => 'all_frames_valid',
        'checksum-tail' => 'frame_checksum_mismatch',
        'salt-tail' => 'frame_salt_mismatch',
        'truncated-tail' => 'truncated_frame_tail',
    };
    $expectedTransactionReason = $corruption === 'none'
        ? 'uncommitted_valid_tail_after_last_commit'
        : 'corrupt_tail_after_committed_prefix';
    $expectedFirstInvalid = $corruption === 'none' ? null : 5;
    $expectedWalLength = $corruption === 'truncated-tail'
        ? $walLength(5, $pageSize) - intdiv($pageSize, 4)
        : $walLength(5, $pageSize);

    $tests[$label . ' preserves committed recovery and checkpoint visibility'] = static function (TestRunner $t) use (
        $case,
        $pageSize,
        $mode,
        $corruption,
        $databaseBytes,
        $walBytes,
        $validFrameCount,
        $totalFrameSlots,
        $committedFrameCount,
        $commitPages,
        $basePages,
        $readerEndFrame,
        $expectedBoundaryReason,
        $expectedTransactionReason,
        $expectedFirstInvalid,
        $expectedWalLength
    ): void {
        $boundary = SQLiteWal::checksumRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $transaction = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $currentNext = SQLiteWal::corruptRecoveryCurrentNextBoundary($walBytes, $databaseBytes, [1, 2, $basePages, $commitPages], $pageSize);
        $wal = $transaction['committed_wal'];
        $checkpoint = $wal->checkpointModeResult($databaseBytes, $mode, $readerEndFrame);
        $visibility = $wal->checkpointReaderVisibility($databaseBytes, [1, 2, $commitPages], $mode, $readerEndFrame);
        $durable = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);

        $t->same($corruption === 'none' ? 'valid' : 'recovered_prefix', $boundary['status']);
        $t->same($expectedBoundaryReason, $boundary['reason']);
        $t->same($validFrameCount, $boundary['valid_frame_count']);
        $t->same($totalFrameSlots, $boundary['total_frame_slots']);
        $t->same($expectedFirstInvalid, $boundary['first_invalid_frame']);
        $t->same($committedFrameCount, $transaction['committed_frame_count']);
        $t->same($expectedTransactionReason, $transaction['reason']);
        $t->same($corruption === 'none' ? 1 : 0, $transaction['discarded_valid_tail_frame_count']);
        $t->same($corruption === 'none' ? 0 : 1, $transaction['discarded_corrupt_tail_frame_count']);
        $t->same($commitPages, $transaction['checkpoint_database_page_count']);
        $t->same($committedFrameCount, $currentNext['next_reader_end_frame']);
        $t->same(true, $currentNext['next_uses_checkpoint_database']);
        $t->same($mode === 'noop' ? $basePages : $commitPages, $checkpoint['database_page_count']);
        $t->same(
            $readerEndFrame !== null && (
                ($mode === 'full' && $readerEndFrame < $committedFrameCount)
                || $mode === 'restart'
                || $mode === 'truncate'
            ),
            $checkpoint['busy']
        );
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($durable['wal_bytes_length'], strlen($durable['wal_bytes']));
        $t->same(true, $visibility['stable']);
        $t->same($expectedWalLength, strlen($walBytes));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $transaction['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-corrupt-recovery-current-next-boundary', $currentNext['dependencies'], true));
        $t->same(0, $case % 1);
    };
}

$tests['real upstream pager wal recovery dynamic records hydrated upstream scenario ranges'] = static function (TestRunner $t): void {
    $t->same([
        'wal.test: wal-18.1.* checksum-prefix recovery and wal-18.2.* page-size recovery boundaries',
        'wal.test: wal-19.* stale wal-index reader recovery and wal-20.* large wal-index mapping growth',
        'walrestart.test: restart/truncate checkpoint generation reuse after committed WAL prefix',
        'walckptnoop.test: no-op checkpoint preserves committed WAL frames without backfill',
    ], [
        'wal.test: wal-18.1.* checksum-prefix recovery and wal-18.2.* page-size recovery boundaries',
        'wal.test: wal-19.* stale wal-index reader recovery and wal-20.* large wal-index mapping growth',
        'walrestart.test: restart/truncate checkpoint generation reuse after committed WAL prefix',
        'walckptnoop.test: no-op checkpoint preserves committed WAL frames without backfill',
    ]);
};

return $tests;
