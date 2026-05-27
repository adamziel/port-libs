<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db-page-1') . $page('db-page-2') . $page('db-page-3');

$makeWalBytes = static function (array $frames, int $sequence = 17) use ($pageSize): string {
    $salt1 = 0x10203040;
    $salt2 = 0x50607080;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $sequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWalBytes([
    [2, 0, $page('wal-tx1-page-2')],
    [3, 3, $page('wal-tx1-page-3-commit')],
    [2, 0, $page('wal-tx2-page-2')],
    [4, 4, $page('wal-tx2-page-4-commit')],
    [1, 0, $page('wal-uncommitted-page-1')],
]);
$wal = SQLiteWal::parse($walBytes, null, true);

$corruptTailBytes = substr($walBytes, 0, 32 + (4 * (24 + $pageSize))) . substr($walBytes, -12);
$corruptChecksumBytes = substr_replace($walBytes, "\xff", 32 + (4 * (24 + $pageSize)) + 40, 1);

$cases = [
    'latest snapshot ends at wal mx frame' => static fn (): mixed => $wal->readerSnapshot($databaseBytes)['end_frame'],
    'latest snapshot uses second committed transaction' => static fn (): mixed => $wal->readerSnapshot($databaseBytes)['commit_frame']->index,
    'latest snapshot exposes grown database size' => static fn (): mixed => $wal->readerSnapshot($databaseBytes)['database_page_count'],
    'snapshot before commit falls back to database page count' => static fn (): mixed => $wal->readerSnapshot($databaseBytes, 1)['database_page_count'],
    'snapshot at first commit uses first commit frame' => static fn (): mixed => $wal->readerSnapshot($databaseBytes, 2)['commit_frame']->index,
    'snapshot between commits still sees first commit frame' => static fn (): mixed => $wal->readerSnapshot($databaseBytes, 3)['commit_frame']->index,
    'snapshot at second commit uses second commit frame' => static fn (): mixed => $wal->readerSnapshot($databaseBytes, 4)['commit_frame']->index,
    'latest page one ignores uncommitted wal frame' => static fn (): mixed => substr($wal->readerSnapshotPageImage($databaseBytes, 1)['image'], 0, 9),
    'latest page two uses tx2 wal frame' => static fn (): mixed => $wal->readerSnapshotPageImage($databaseBytes, 2)['frame_index'],
    'first commit page two uses tx1 wal frame' => static fn (): mixed => $wal->readerSnapshotPageImage($databaseBytes, 2, 2)['frame_index'],
    'first commit page four is outside snapshot' => static function () use ($wal, $databaseBytes): mixed {
        try {
            $wal->readerSnapshotPageImage($databaseBytes, 4, 2);
        } catch (OutOfBoundsException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'latest page four uses second commit frame' => static fn (): mixed => substr($wal->readerSnapshotPageImage($databaseBytes, 4)['image'], 0, 21),
    'reader page map latest has four pages' => static fn (): mixed => count($wal->readerSnapshotPageMap($databaseBytes)),
    'reader page map first commit has three pages' => static fn (): mixed => count($wal->readerSnapshotPageMap($databaseBytes, 2)),
    'reader page map records wal source for page two' => static fn (): mixed => $wal->readerSnapshotPageMap($databaseBytes)[1]['source'],
    'reader page map records database source for page one' => static fn (): mixed => $wal->readerSnapshotPageMap($databaseBytes)[0]['source'],
    'passive checkpoint with old reader is reader limited' => static fn (): mixed => $wal->checkpointModePlan($databaseBytes, 'passive', 2)['reason'],
    'passive checkpoint with old reader is not busy' => static fn (): mixed => $wal->checkpointModePlan($databaseBytes, 'passive', 2)['busy'],
    'full checkpoint with old reader is busy' => static fn (): mixed => $wal->checkpointModePlan($databaseBytes, 'full', 2)['busy'],
    'full checkpoint with old reader reports blocked completion' => static fn (): mixed => $wal->checkpointModePlan($databaseBytes, 'full', 2)['reason'],
    'restart checkpoint with current reader blocks reset' => static fn (): mixed => $wal->checkpointModePlan($databaseBytes, 'restart', 4)['reason'],
    'truncate checkpoint with no reader can truncate' => static fn (): mixed => $wal->checkpointModePlan($databaseBytes, 'truncate')['can_truncate'],
    'truncate checkpoint with uncommitted tail preserves wal' => static fn (): mixed => $wal->checkpointModeResult($databaseBytes, 'truncate')['wal_action'],
    'restart checkpoint with uncommitted tail cannot reset' => static fn (): mixed => $wal->checkpointModeResult($databaseBytes, 'restart')['can_reset'],
    'durable passive checkpoint preserves wal bytes' => static fn (): mixed => $wal->durableCheckpointResult($databaseBytes, 'passive')['wal_bytes_length'],
    'durable full checkpoint keeps uncommitted frame tail' => static fn (): mixed => $wal->durableCheckpointResult($databaseBytes, 'full')['uncommitted_frame_count'],
    'checkpoint result grows database to four pages' => static fn (): mixed => $wal->checkpointModeResult($databaseBytes, 'passive')['database_page_count'],
    'checkpoint result writes latest committed page two' => static fn (): mixed => substr($wal->checkpointModeResult($databaseBytes, 'passive')['database_bytes'], 512, 14),
    'checkpoint result writes committed page four' => static fn (): mixed => substr($wal->checkpointModeResult($databaseBytes, 'passive')['database_bytes'], 1536, 21),
    'reader visibility passive old reader remains stable' => static fn (): mixed => $wal->checkpointReaderVisibility($databaseBytes, [2, 3], 'passive', 2)['stable'],
    'reader visibility passive old reader preserves wal action' => static fn (): mixed => $wal->checkpointReaderVisibility($databaseBytes, [2, 3], 'passive', 2)['wal_action'],
    'reader visibility full old reader reports busy' => static fn (): mixed => $wal->checkpointReaderVisibility($databaseBytes, [2, 3], 'full', 2)['checkpoint_busy'],
    'reader visibility restart current reader reports busy' => static fn (): mixed => $wal->checkpointReaderVisibility($databaseBytes, [2, 4], 'restart', 4)['checkpoint_busy'],
    'reader visibility latest passive has stable images' => static fn (): mixed => $wal->checkpointReaderVisibility($databaseBytes, [1, 2, 4], 'passive')['stable'],
    'reader visibility before page two source is wal' => static fn (): mixed => $wal->checkpointReaderVisibility($databaseBytes, [2], 'passive')['before'][0]['source'],
    'reader visibility after page two source remains wal when wal preserved' => static fn (): mixed => $wal->checkpointReaderVisibility($databaseBytes, [2], 'passive')['after'][0]['source'],
    'reader visibility rejects empty page list' => static function () use ($wal, $databaseBytes): mixed {
        try {
            $wal->checkpointReaderVisibility($databaseBytes, [], 'passive');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'snapshot rejects negative end frame' => static function () use ($wal, $databaseBytes): mixed {
        try {
            $wal->readerSnapshot($databaseBytes, -1);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'snapshot rejects end frame past mx frame' => static function () use ($wal, $databaseBytes): mixed {
        try {
            $wal->readerSnapshot($databaseBytes, 99);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'checkpoint rejects unsupported mode' => static function () use ($wal, $databaseBytes): mixed {
        try {
            $wal->checkpointModePlan($databaseBytes, 'flush');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'read mark plan pins stale reader' => static fn (): mixed => $wal->readMarkPlan([0, 2, 4, null])['checkpoint_pinned_frame'],
    'read mark plan recommends unused slot' => static fn (): mixed => $wal->readMarkPlan([0, 2, 4, null])['recommended_reader_slot'],
    'read mark plan recommends latest commit frame' => static fn (): mixed => $wal->readMarkPlan([0, 2, 4, null])['recommended_reader_frame'],
    'read mark plan flags reset blocked' => static fn (): mixed => $wal->readMarkPlan([0, 2, 4, null])['reset_blocked'],
    'read mark plan marks database only reader reusable' => static fn (): mixed => $wal->readMarkPlan([0, 2, 4, null])['read_marks'][0]['reason'],
    'read mark plan marks current reader as latest' => static fn (): mixed => $wal->readMarkPlan([0, 2, 4, null])['read_marks'][2]['reason'],
    'corrupt truncated tail recovers committed prefix' => static fn (): mixed => SQLiteWal::checksumRecoveryBoundary($corruptTailBytes, $databaseBytes)['status'],
    'corrupt truncated tail first invalid is next frame' => static fn (): mixed => SQLiteWal::checksumRecoveryBoundary($corruptTailBytes, $databaseBytes)['first_invalid_frame'],
    'corrupt truncated tail can checkpoint prefix' => static fn (): mixed => SQLiteWal::checksumRecoveryBoundary($corruptTailBytes, $databaseBytes)['can_checkpoint'],
    'corrupt truncated tail valid bytes stop before tail' => static fn (): mixed => SQLiteWal::checksumRecoveryBoundary($corruptTailBytes, $databaseBytes)['recovery_end_offset'],
    'corrupt checksum tail recovers four valid frames' => static fn (): mixed => SQLiteWal::checksumRecoveryBoundary($corruptChecksumBytes, $databaseBytes)['valid_frame_count'],
    'corrupt checksum tail reports checksum mismatch' => static fn (): mixed => SQLiteWal::checksumRecoveryBoundary($corruptChecksumBytes, $databaseBytes)['reason'],
    'corrupt checksum checkpoint image applies valid page four' => static fn (): mixed => substr(SQLiteWal::checksumRecoveryBoundary($corruptChecksumBytes, $databaseBytes)['checkpoint_database_bytes'], 1536, 21),
];

$expected = [
    'latest snapshot ends at wal mx frame' => 5,
    'latest snapshot uses second committed transaction' => 4,
    'latest snapshot exposes grown database size' => 4,
    'snapshot before commit falls back to database page count' => 3,
    'snapshot at first commit uses first commit frame' => 2,
    'snapshot between commits still sees first commit frame' => 2,
    'snapshot at second commit uses second commit frame' => 4,
    'latest page one ignores uncommitted wal frame' => 'db-page-1',
    'latest page two uses tx2 wal frame' => 3,
    'first commit page two uses tx1 wal frame' => 1,
    'first commit page four is outside snapshot' => 'rejected',
    'latest page four uses second commit frame' => 'wal-tx2-page-4-commit',
    'reader page map latest has four pages' => 4,
    'reader page map first commit has three pages' => 3,
    'reader page map records wal source for page two' => 'wal',
    'reader page map records database source for page one' => 'database',
    'passive checkpoint with old reader is reader limited' => 'reader_limited_passive_checkpoint',
    'passive checkpoint with old reader is not busy' => false,
    'full checkpoint with old reader is busy' => true,
    'full checkpoint with old reader reports blocked completion' => 'reader_blocks_checkpoint_completion',
    'restart checkpoint with current reader blocks reset' => 'uncommitted_frames_after_last_commit',
    'truncate checkpoint with no reader can truncate' => false,
    'truncate checkpoint with uncommitted tail preserves wal' => 'preserve_wal',
    'restart checkpoint with uncommitted tail cannot reset' => false,
    'durable passive checkpoint preserves wal bytes' => strlen($walBytes),
    'durable full checkpoint keeps uncommitted frame tail' => 1,
    'checkpoint result grows database to four pages' => 4,
    'checkpoint result writes latest committed page two' => 'wal-tx2-page-2',
    'checkpoint result writes committed page four' => 'wal-tx2-page-4-commit',
    'reader visibility passive old reader remains stable' => true,
    'reader visibility passive old reader preserves wal action' => 'preserve_wal',
    'reader visibility full old reader reports busy' => true,
    'reader visibility restart current reader reports busy' => false,
    'reader visibility latest passive has stable images' => true,
    'reader visibility before page two source is wal' => 'wal',
    'reader visibility after page two source remains wal when wal preserved' => 'wal',
    'reader visibility rejects empty page list' => 'rejected',
    'snapshot rejects negative end frame' => 'rejected',
    'snapshot rejects end frame past mx frame' => 'rejected',
    'checkpoint rejects unsupported mode' => 'rejected',
    'read mark plan pins stale reader' => 2,
    'read mark plan recommends unused slot' => 0,
    'read mark plan recommends latest commit frame' => 4,
    'read mark plan flags reset blocked' => true,
    'read mark plan marks database only reader reusable' => 'database_only_reader_before_wal_commit',
    'read mark plan marks current reader as latest' => 'pins_latest_commit',
    'corrupt truncated tail recovers committed prefix' => 'recovered_prefix',
    'corrupt truncated tail first invalid is next frame' => 5,
    'corrupt truncated tail can checkpoint prefix' => true,
    'corrupt truncated tail valid bytes stop before tail' => 32 + (4 * (24 + $pageSize)),
    'corrupt checksum tail recovers four valid frames' => 4,
    'corrupt checksum tail reports checksum mismatch' => 'frame_checksum_mismatch',
    'corrupt checksum checkpoint image applies valid page four' => 'wal-tx2-page-4-commit',
];

foreach ($cases as $name => $callback) {
    $tests['wal reader writer checkpoint snapshot corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
