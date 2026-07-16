<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = $page('wp-options-db-page-1') . $page('wp-options-db-page-2') . $page('wp-options-db-page-3');
$salt1 = 0x10203040;
$salt2 = 0x50607080;

$makeWal = static function (array $frames, int $checkpointSequence = 28) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 5) use ($pageSize): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack(
        'V*',
        3007000,
        $backfill,
        128,
        $pageSizeField,
        $mxFrame,
        3,
        0x11111111,
        0x22222222,
        0x33333333,
        0x44444444,
        0x55555555,
        0x66666666
    );
    $marks = array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, 0xffffffff);
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$wal = SQLiteWal::parse($makeWal([
    [2, 0, $page('frame-1-option-draft')],
    [3, 3, $page('frame-2-index-commit')],
    [2, 0, $page('frame-3-option-edit')],
    [1, 0, $page('frame-4-schema-edit')],
    [2, 3, $page('frame-5-option-final')],
]), null, true);

$pinnedShm = SQLiteShmIndex::parse($makeShm([0, 2, 5, null, 9], [false, true, true, false, true], 1, 4));
$releasedShm = SQLiteShmIndex::parse($makeShm([0, 5, null, null, null], [false, false, false, false, false], 5, 5));
$databaseOnlyShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [true, false, false, false, false], 5, 5));

$pinnedRestart = $wal->restartReadMarkTransition($database, $pinnedShm, 'restart');
$releasedRestart = $wal->restartReadMarkTransition($database, $releasedShm, 'restart');
$releasedTruncate = $wal->restartReadMarkTransition($database, $releasedShm, 'truncate');
$databaseOnlyRestart = $wal->restartReadMarkTransition($database, $databaseOnlyShm, 'restart');

$cases = [
    'pinned status' => static fn (): mixed => $pinnedRestart['status'],
    'pinned mode' => static fn (): mixed => $pinnedRestart['mode'],
    'pinned current reader end frame' => static fn (): mixed => $pinnedRestart['current_reader_end_frame'],
    'pinned checkpoint busy' => static fn (): mixed => $pinnedRestart['checkpoint']['busy'],
    'pinned checkpoint reason' => static fn (): mixed => $pinnedRestart['checkpoint']['reason'],
    'pinned wal action preserves wal' => static fn (): mixed => $pinnedRestart['checkpoint']['wal_action'],
    'pinned next wal frame count' => static fn (): mixed => $pinnedRestart['next_wal_frame_count'],
    'pinned current reader kept snapshot' => static fn (): mixed => $pinnedRestart['current_reader_kept_snapshot'],
    'pinned next read marks keep locked readers only' => static fn (): mixed => $pinnedRestart['next_read_marks'],
    'pinned next read mark plan pins same frame' => static fn (): mixed => $pinnedRestart['next_read_mark_plan']['checkpoint_pinned_frame'],
    'pinned next reusable slots include database and invalid marks' => static fn (): mixed => $pinnedRestart['next_read_mark_plan']['reusable_slots'],
    'pinned next reader slot chooses reusable zero' => static fn (): mixed => $pinnedRestart['next_reader_slot'],
    'pinned next reader frame advances to latest commit' => static fn (): mixed => $pinnedRestart['next_reader_frame'],
    'pinned next reader stays on wal' => static fn (): mixed => $pinnedRestart['next_reader_uses_database'],
    'pinned header remains original checkpoint sequence' => static fn (): mixed => $pinnedRestart['next_wal_header']['checkpoint_sequence'] ?? null,
    'pinned shm reusable slots from locks' => static fn (): mixed => $pinnedRestart['current_shm']['reusable_slots'],
    'pinned shm reset blocked' => static fn (): mixed => $pinnedRestart['current_shm']['reset_blocked'],
    'pinned shm checkpoint frame' => static fn (): mixed => $pinnedRestart['current_shm']['checkpoint_pinned_frame'],
    'pinned dependency includes current next boundary' => static fn (): mixed => in_array('wal-current-next-reader-boundary', $pinnedRestart['dependencies'], true),
    'released status' => static fn (): mixed => $releasedRestart['status'],
    'released checkpoint busy false' => static fn (): mixed => $releasedRestart['checkpoint']['busy'],
    'released checkpoint can reset' => static fn (): mixed => $releasedRestart['checkpoint']['can_reset'],
    'released checkpoint reason' => static fn (): mixed => $releasedRestart['checkpoint']['reason'],
    'released restart wal action' => static fn (): mixed => $releasedRestart['checkpoint']['wal_action'],
    'released restarted wal byte length' => static fn (): mixed => $releasedRestart['checkpoint']['wal_bytes_length'],
    'released restarted wal frame count' => static fn (): mixed => $releasedRestart['next_wal_frame_count'],
    'released restarted wal header sequence' => static fn (): mixed => $releasedRestart['next_wal_header']['checkpoint_sequence'],
    'released restarted wal header salt advanced' => static fn (): mixed => $releasedRestart['next_wal_header']['salt1'],
    'released next read marks reset to database reader' => static fn (): mixed => $releasedRestart['next_read_marks'],
    'released next read mark reusable slots' => static fn (): mixed => $releasedRestart['next_read_mark_plan']['reusable_slots'],
    'released next reader slot zero' => static fn (): mixed => $releasedRestart['next_reader_slot'],
    'released next reader frame zero' => static fn (): mixed => $releasedRestart['next_reader_frame'],
    'released next reader uses restarted wal header' => static fn (): mixed => $releasedRestart['next_reader_uses_restarted_wal'],
    'released current reader not kept' => static fn (): mixed => $releasedRestart['current_reader_kept_snapshot'],
    'released database page contains final option' => static fn (): mixed => str_contains($releasedRestart['checkpoint']['database_bytes'], 'frame-5-option-final'),
    'released database page contains schema edit' => static fn (): mixed => str_contains($releasedRestart['checkpoint']['database_bytes'], 'frame-4-schema-edit'),
    'released shm reset not blocked' => static fn (): mixed => $releasedRestart['current_shm']['reset_blocked'],
    'released shm checkpoint can finish' => static fn (): mixed => $releasedRestart['current_shm']['checkpoint_can_finish'],
    'truncate status' => static fn (): mixed => $releasedTruncate['status'],
    'truncate wal action' => static fn (): mixed => $releasedTruncate['checkpoint']['wal_action'],
    'truncate wal byte length' => static fn (): mixed => $releasedTruncate['checkpoint']['wal_bytes_length'],
    'truncate next wal frame count' => static fn (): mixed => $releasedTruncate['next_wal_frame_count'],
    'truncate next header null' => static fn (): mixed => $releasedTruncate['next_wal_header'],
    'truncate next reader uses database' => static fn (): mixed => $releasedTruncate['next_reader_uses_database'],
    'truncate next reader not restarted wal' => static fn (): mixed => $releasedTruncate['next_reader_uses_restarted_wal'],
    'truncate next read marks database only' => static fn (): mixed => $releasedTruncate['next_read_marks'],
    'database-only restart uses reset path' => static fn (): mixed => $databaseOnlyRestart['status'],
    'database-only restart current reader end frame null' => static fn (): mixed => $databaseOnlyRestart['current_reader_end_frame'],
    'database-only restart checkpoint reason' => static fn (): mixed => $databaseOnlyRestart['checkpoint']['reason'],
    'database-only restart next read marks' => static fn (): mixed => $databaseOnlyRestart['next_read_marks'],
    'invalid mode rejected' => static function () use ($wal, $database, $releasedShm): mixed {
        try {
            $wal->restartReadMarkTransition($database, $releasedShm, 'passive');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'pinned status' => 'current-reader-pinned',
    'pinned mode' => 'restart',
    'pinned current reader end frame' => 2,
    'pinned checkpoint busy' => true,
    'pinned checkpoint reason' => 'reader_blocks_checkpoint_completion',
    'pinned wal action preserves wal' => 'preserve_wal',
    'pinned next wal frame count' => 5,
    'pinned current reader kept snapshot' => true,
    'pinned next read marks keep locked readers only' => [null, 2, 5, null, null],
    'pinned next read mark plan pins same frame' => 2,
    'pinned next reusable slots include database and invalid marks' => [0, 1, 3, 4],
    'pinned next reader slot chooses reusable zero' => 0,
    'pinned next reader frame advances to latest commit' => 5,
    'pinned next reader stays on wal' => false,
    'pinned header remains original checkpoint sequence' => 28,
    'pinned shm reusable slots from locks' => [0, 3, 4],
    'pinned shm reset blocked' => true,
    'pinned shm checkpoint frame' => 2,
    'pinned dependency includes current next boundary' => true,
    'released status' => 'restart-ready',
    'released checkpoint busy false' => false,
    'released checkpoint can reset' => true,
    'released checkpoint reason' => 'restart_checkpoint_can_reset_wal',
    'released restart wal action' => 'restart_wal',
    'released restarted wal byte length' => 32,
    'released restarted wal frame count' => 0,
    'released restarted wal header sequence' => 29,
    'released restarted wal header salt advanced' => 0x10203041,
    'released next read marks reset to database reader' => [0, null, null, null, null],
    'released next read mark reusable slots' => [1, 2, 3, 4],
    'released next reader slot zero' => 1,
    'released next reader frame zero' => 0,
    'released next reader uses restarted wal header' => true,
    'released current reader not kept' => false,
    'released database page contains final option' => true,
    'released database page contains schema edit' => true,
    'released shm reset not blocked' => false,
    'released shm checkpoint can finish' => true,
    'truncate status' => 'restart-ready',
    'truncate wal action' => 'truncate_wal',
    'truncate wal byte length' => 0,
    'truncate next wal frame count' => 0,
    'truncate next header null' => null,
    'truncate next reader uses database' => true,
    'truncate next reader not restarted wal' => false,
    'truncate next read marks database only' => [0, null, null, null, null],
    'database-only restart uses reset path' => 'restart-ready',
    'database-only restart current reader end frame null' => null,
    'database-only restart checkpoint reason' => 'restart_checkpoint_can_reset_wal',
    'database-only restart next read marks' => [0, null, null, null, null],
    'invalid mode rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal readmark restart current next28 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
