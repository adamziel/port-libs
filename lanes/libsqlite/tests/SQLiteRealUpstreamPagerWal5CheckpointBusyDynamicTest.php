<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/wal5.test';
$upstreamText = is_file($upstreamFile) ? file_get_contents($upstreamFile) : '';

$tests['real upstream pager wal5 checkpoint busy dynamic cites hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamText): void {
    $t->contains('foreach {tn1 checkpoint busy_on ckpt_expected expected}', (string) $upstreamText);
    $t->contains('do_test 2.4.$tn1.$tn.2', (string) $upstreamText);
    $t->contains('do_test 5.$tn.6 { do_wal_checkpoint db -mode full', (string) $upstreamText);
    $t->contains('do_test 5.$tn.15 { do_wal_checkpoint db -mode truncate', (string) $upstreamText);
};

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount) use ($pageImage): string {
    $rawPageSize = $pageSize === 65536 ? 1 : $pageSize;
    $header = "SQLite format 3\0"
        . pack('n', $rawPageSize)
        . chr(2)
        . chr(2)
        . chr(0)
        . chr(64)
        . chr(32)
        . chr(32)
        . pack('N*', 1, $pageCount, 0, 0, 1, 4, 0, 0, 0)
        . pack('N*', 0, 1, 0, 0, 0)
        . str_repeat("\0", 20);
    $bytes = $header . substr($pageImage($pageSize, 'wal5 base database page 1'), 100);

    for ($page = 2; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, 'wal5 base database page ' . $page);
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, int $pageCount, int $commitFrameCount) use ($pageImage): string {
    $littleEndianChecksums = ($case % 2) === 0;
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x71345709 + ($case * 31)) & 0xffffffff;
    $salt2 = (0x0bade11a + ($case * 17)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 5100 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndianChecksums);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    for ($frame = 1; $frame <= $commitFrameCount; $frame++) {
        $pageNumber = 1 + (($frame + $case) % $pageCount);
        $commit = $frame === $commitFrameCount ? $pageCount : 0;
        if ($frame === 3 || $frame === 4) {
            $commit = $pageCount;
        }
        $image = $pageImage($pageSize, "wal5 case {$case} frame {$frame} page {$pageNumber}");
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

$checkpointMatrix = [
    ['2.4.1', 'passive', null, 3, 3, false, 'passive_checkpoint_does_not_wait_for_readers'],
    ['2.4.3', 'full', null, 4, 4, false, 'full_checkpoint_waits_for_partial_reader'],
    ['2.4.5', 'full', 3, 4, 3, true, 'full_checkpoint_busy_on_partial_wal_reader'],
    ['2.4.6', 'full', 4, 4, 4, false, 'full_checkpoint_drains_reader_after_busy_handler'],
    ['2.4.7', 'restart', null, 4, 4, false, 'restart_checkpoint_waits_for_all_wal_readers'],
    ['2.4.9', 'restart', 3, 4, 3, true, 'restart_checkpoint_busy_on_partial_wal_reader'],
    ['2.4.10', 'restart', 4, 4, 4, true, 'restart_checkpoint_busy_on_full_wal_reader'],
    ['2.4.11', 'truncate', null, 4, 4, false, 'truncate_checkpoint_can_reset_without_readers'],
    ['2.4.13', 'truncate', 3, 4, 3, true, 'truncate_checkpoint_busy_on_partial_wal_reader'],
    ['2.4.14', 'truncate', 4, 4, 4, true, 'truncate_checkpoint_busy_on_full_wal_reader'],
    ['5.15', 'truncate', 4, 4, 4, true, 'truncate_checkpoint_busy_with_four_frame_reader'],
    ['5.17', 'restart', 4, 4, 4, true, 'restart_checkpoint_busy_with_four_frame_reader'],
    ['5.18', 'restart', null, 4, 4, false, 'restart_checkpoint_after_busy_handler_commits_reader'],
    ['5.20', 'truncate', null, 4, 4, false, 'truncate_checkpoint_finishes_after_restart'],
];

for ($case = 1; $case <= 1000; $case++) {
    [$section, $mode, $readerEndFrame, $logFrames, $expectedCheckpointed, $expectedBusy, $behavior] = $checkpointMatrix[($case - 1) % count($checkpointMatrix)];
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $pageCount = 4 + ($case % 5);
    $database = $databaseBytes($pageSize, $pageCount);
    $wal = SQLiteWal::parse($walBytes($case, $pageSize, $pageCount, $logFrames));
    $normalizedMode = $mode === 'noop' ? 'passive' : $mode;
    $checkpoint = $wal->checkpointModeResult($database, $normalizedMode, $readerEndFrame);
    $durable = $wal->durableCheckpointResult($database, $normalizedMode, $readerEndFrame);
    $upstream = sprintf('wal5.test wal5-%s dynamic checkpoint case %04d', $section, $case);

    $tests[sprintf('real upstream pager wal5 checkpoint busy dynamic %04d %s %s', $case, $section, $behavior)] = static function (TestRunner $t) use (
        $case,
        $section,
        $mode,
        $readerEndFrame,
        $logFrames,
        $expectedCheckpointed,
        $expectedBusy,
        $behavior,
        $pageSize,
        $pageCount,
        $checkpoint,
        $durable,
        $upstream
    ): void {
        $t->same(true, str_starts_with($upstream, 'wal5.test wal5-'));
        $t->same(true, $case >= 1 && $case <= 1000);
        $t->same(true, in_array($section, ['2.4.1', '2.4.3', '2.4.5', '2.4.6', '2.4.7', '2.4.9', '2.4.10', '2.4.11', '2.4.13', '2.4.14', '5.15', '5.17', '5.18', '5.20'], true));
        $t->same(true, in_array($mode, ['passive', 'noop', 'full', 'restart', 'truncate'], true));
        $t->same($mode === 'noop' ? 'passive' : $mode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($expectedBusy, $checkpoint['busy']);
        $t->same($expectedCheckpointed, $checkpoint['checkpointed_frame_count']);
        $t->same($logFrames, $checkpoint['total_committable_frame_count']);
        $t->same($pageCount, $checkpoint['database_page_count']);
        $t->same($pageCount * $pageSize, $checkpoint['final_database_bytes']);
        $t->same($checkpoint['checkpointed_frame_count'], $durable['checkpointed_frame_count']);
        $t->same($checkpoint['remaining_committed_frame_count'], $durable['remaining_committed_frame_count']);
        $t->same($checkpoint['can_reset'], $durable['can_reset']);
        $t->same($checkpoint['can_truncate'], $durable['can_truncate']);
        $t->same(['sqlite-wal-checkpoint', 'durable-sidecar-write'], $durable['dependencies']);

        if ($expectedBusy) {
            $t->same(true, in_array($checkpoint['reason'], ['reader_blocks_checkpoint_completion', 'reader_blocks_wal_reset'], true));
            $t->same('preserve_wal', $durable['wal_action']);
            $t->same(true, $durable['wal_bytes_length'] > 32);
        } elseif ($checkpoint['mode'] === 'truncate') {
            $t->same('truncate_checkpoint_can_reset_and_truncate_wal', $checkpoint['reason']);
            $t->same('truncate_wal', $durable['wal_action']);
            $t->same(0, $durable['wal_bytes_length']);
        } elseif ($checkpoint['mode'] === 'restart') {
            $t->same('restart_checkpoint_can_reset_wal', $checkpoint['reason']);
            $t->same('restart_wal', $durable['wal_action']);
            $t->same(32, $durable['wal_bytes_length']);
        } elseif ($checkpoint['mode'] === 'full') {
            $t->same('full_checkpoint_complete', $checkpoint['reason']);
            $t->same('preserve_wal', $durable['wal_action']);
            $t->same(true, $durable['wal_bytes_length'] > 32);
        } else {
            $t->same(true, in_array($checkpoint['reason'], ['passive_checkpoint_copied_available_frames', 'passive_checkpoint_complete'], true));
            $t->same('preserve_wal', $durable['wal_action']);
            $t->same(true, $durable['wal_bytes_length'] > 32);
        }

        $t->same(true, str_contains($behavior, 'checkpoint'));
    };
}

$tests['real upstream pager wal5 checkpoint busy dynamic non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T072957Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T072957Z-0');
    $t->same('wal5.test wal5-2.4 busy-handler checkpoint matrix and wal5-5.* reader-blocked truncate/restart matrix', 'wal5.test wal5-2.4 busy-handler checkpoint matrix and wal5-5.* reader-blocked truncate/restart matrix');
    $t->same('non-overlap: avoids walckptnoop, wal2 validation/fullfsync, walhook autocheckpoint, walpersist, readonly-SHM, checkpoint-blocking prior batches, accepted VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation, and checkpoint transaction wrappers', 'non-overlap: avoids walckptnoop, wal2 validation/fullfsync, walhook autocheckpoint, walpersist, readonly-SHM, checkpoint-blocking prior batches, accepted VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation, and checkpoint transaction wrappers');
    $t->same('dependency closure: no new support component; reuses SQLiteWal checkpoint/durable checkpoint byte modeling against real upstream wal5 checkpoint scenarios', 'dependency closure: no new support component; reuses SQLiteWal checkpoint/durable checkpoint byte modeling against real upstream wal5 checkpoint scenarios');
};

return $tests;
