<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x37373737;
$salt2 = 0x57575757;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = $page('db page 1 schema baseline')
    . $page('db page 2 option baseline')
    . $page('db page 3 autoload baseline')
    . $page('db page 4 plugin baseline');

$makeWal = static function (array $frames, int $checkpointSequence = 37) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 6) use ($pageSize): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack(
        'V*',
        3007000,
        $backfill,
        141,
        $pageSizeField,
        $mxFrame,
        4,
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
    [2, 0, $page('frame 1 option draft before reader')],
    [3, 3, $page('frame 2 autoload commit before reader')],
    [2, 0, $page('frame 3 option edit after reader')],
    [4, 0, $page('frame 4 plugin settings draft')],
    [2, 0, $page('frame 5 option final before commit')],
    [4, 4, $page('frame 6 plugin settings commit')],
]), null, true);

$pinnedShm = SQLiteShmIndex::parse($makeShm([0, 2, 6, null, 9], [false, true, true, false, true], 1, 5));
$releasedShm = SQLiteShmIndex::parse($makeShm([0, 6, null, null, null], [false, false, false, false, false], 6, 6));
$databaseOnlyShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [true, false, false, false, false], 6, 6));

$pinned = static fn (): array => $wal->restartReadMarkReaderMapTransition($database, $pinnedShm, [1, 2, 3, 4], 'restart');
$releasedRestart = static fn (): array => $wal->restartReadMarkReaderMapTransition($database, $releasedShm, [1, 2, 3, 4], 'restart');
$releasedTruncate = static fn (): array => $wal->restartReadMarkReaderMapTransition($database, $releasedShm, [1, 2, 3, 4], 'truncate');
$databaseOnly = static fn (): array => $wal->restartReadMarkReaderMapTransition($database, $databaseOnlyShm, [1, 2], 'restart');

$cases = [
    'pinned status' => [static fn (): mixed => $pinned()['status'], 'current-reader-pinned'],
    'pinned mode' => [static fn (): mixed => $pinned()['mode'], 'restart'],
    'pinned current reader end frame' => [static fn (): mixed => $pinned()['current_reader_end_frame'], 2],
    'pinned next reader end frame' => [static fn (): mixed => $pinned()['next_reader_end_frame'], 6],
    'pinned current reader kept snapshot' => [static fn (): mixed => $pinned()['current_reader_kept_snapshot'], true],
    'pinned next reader uses database false' => [static fn (): mixed => $pinned()['next_reader_uses_database'], false],
    'pinned next reader not restarted wal' => [static fn (): mixed => $pinned()['next_reader_uses_restarted_wal'], false],
    'pinned checkpoint busy' => [static fn (): mixed => $pinned()['transition']['checkpoint']['busy'], true],
    'pinned checkpoint reason' => [static fn (): mixed => $pinned()['transition']['checkpoint']['reason'], 'reader_blocks_checkpoint_completion'],
    'pinned checkpoint wal preserved' => [static fn (): mixed => $pinned()['transition']['checkpoint']['wal_action'], 'preserve_wal'],
    'pinned current sources' => [static fn (): mixed => $pinned()['current_reader_sources'], ['database', 'wal', 'wal', 'missing']],
    'pinned next sources' => [static fn (): mixed => $pinned()['next_reader_sources'], ['database', 'wal', 'wal', 'wal']],
    'pinned current frames' => [static fn (): mixed => $pinned()['current_reader_frame_indexes'], [null, 1, 2, null]],
    'pinned next frames' => [static fn (): mixed => $pinned()['next_reader_frame_indexes'], [null, 5, 2, 6]],
    'pinned current option page old draft' => [static fn (): mixed => str_contains($pinned()['current_reader'][1]['image'], 'before reader'), true],
    'pinned next option page final' => [static fn (): mixed => str_contains($pinned()['next_reader'][1]['image'], 'option final'), true],
    'pinned current plugin page beyond old commit' => [static fn (): mixed => $pinned()['current_reader'][3]['error'], 'SQLite WAL reader page 4 is beyond the committed database size'],
    'pinned next plugin page committed' => [static fn (): mixed => str_contains($pinned()['next_reader'][3]['image'], 'plugin settings commit'), true],
    'pinned current autoload committed' => [static fn (): mixed => str_contains($pinned()['current_reader'][2]['image'], 'autoload commit'), true],
    'pinned next autoload committed' => [static fn (): mixed => str_contains($pinned()['next_reader'][2]['image'], 'autoload commit'), true],
    'pinned current errors report old snapshot page count' => [static fn (): mixed => $pinned()['current_reader_errors'], ['SQLite WAL reader page 4 is beyond the committed database size']],
    'pinned no next errors' => [static fn (): mixed => $pinned()['next_reader_errors'], []],
    'pinned read marks keep old reader' => [static fn (): mixed => $pinned()['transition']['next_read_marks'], [null, 2, 6, null, null]],
    'pinned next read mark pins frame two' => [static fn (): mixed => $pinned()['transition']['next_read_mark_plan']['checkpoint_pinned_frame'], 2],
    'pinned next reader frame latest commit' => [static fn (): mixed => $pinned()['transition']['next_reader_frame'], 6],
    'pinned dependency includes reader map' => [static fn (): mixed => in_array('wal-checkpoint-restart-reader-map-current-next37', $pinned()['dependencies'], true), true],
    'pinned dependency includes restart' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-restart', $pinned()['dependencies'], true), true],
    'pinned dependency includes read marks' => [static fn (): mixed => in_array('wal-index-read-marks', $pinned()['dependencies'], true), true],
    'released restart status' => [static fn (): mixed => $releasedRestart()['status'], 'restart-ready'],
    'released restart current reader end frame null' => [static fn (): mixed => $releasedRestart()['current_reader_end_frame'], null],
    'released restart next reader end frame zero' => [static fn (): mixed => $releasedRestart()['next_reader_end_frame'], 0],
    'released restart checkpoint action' => [static fn (): mixed => $releasedRestart()['transition']['checkpoint']['wal_action'], 'restart_wal'],
    'released restart checkpoint not busy' => [static fn (): mixed => $releasedRestart()['transition']['checkpoint']['busy'], false],
    'released restart next uses database' => [static fn (): mixed => $releasedRestart()['next_reader_uses_database'], true],
    'released restart next uses restarted wal' => [static fn (): mixed => $releasedRestart()['next_reader_uses_restarted_wal'], true],
    'released restart current sources database' => [static fn (): mixed => $releasedRestart()['current_reader_sources'], ['database', 'database', 'database', 'database']],
    'released restart next sources database' => [static fn (): mixed => $releasedRestart()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'released restart current frames null' => [static fn (): mixed => $releasedRestart()['current_reader_frame_indexes'], [null, null, null, null]],
    'released restart next frames null' => [static fn (): mixed => $releasedRestart()['next_reader_frame_indexes'], [null, null, null, null]],
    'released restart database option final checkpointed' => [static fn (): mixed => str_contains($releasedRestart()['next_reader'][1]['image'], 'option final'), true],
    'released restart database plugin commit checkpointed' => [static fn (): mixed => str_contains($releasedRestart()['next_reader'][3]['image'], 'plugin settings commit'), true],
    'released restart header advanced' => [static fn (): mixed => $releasedRestart()['transition']['next_wal_header']['checkpoint_sequence'], 38],
    'released restart read marks database reader' => [static fn (): mixed => $releasedRestart()['transition']['next_read_marks'], [0, null, null, null, null]],
    'released restart no current errors' => [static fn (): mixed => $releasedRestart()['current_reader_errors'], []],
    'released restart no next errors' => [static fn (): mixed => $releasedRestart()['next_reader_errors'], []],
    'truncate status' => [static fn (): mixed => $releasedTruncate()['status'], 'restart-ready'],
    'truncate checkpoint action' => [static fn (): mixed => $releasedTruncate()['transition']['checkpoint']['wal_action'], 'truncate_wal'],
    'truncate next reader uses database' => [static fn (): mixed => $releasedTruncate()['next_reader_uses_database'], true],
    'truncate not restarted wal' => [static fn (): mixed => $releasedTruncate()['next_reader_uses_restarted_wal'], false],
    'truncate next reader end frame zero' => [static fn (): mixed => $releasedTruncate()['next_reader_end_frame'], 0],
    'truncate next header null' => [static fn (): mixed => $releasedTruncate()['transition']['next_wal_header'], null],
    'truncate next sources database' => [static fn (): mixed => $releasedTruncate()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'truncate plugin page checkpointed' => [static fn (): mixed => str_contains($releasedTruncate()['next_reader'][3]['image'], 'plugin settings commit'), true],
    'database only status' => [static fn (): mixed => $databaseOnly()['status'], 'restart-ready'],
    'database only current sources' => [static fn (): mixed => $databaseOnly()['current_reader_sources'], ['database', 'database']],
    'database only next sources' => [static fn (): mixed => $databaseOnly()['next_reader_sources'], ['database', 'database']],
    'database only current reader kept false' => [static fn (): mixed => $databaseOnly()['current_reader_kept_snapshot'], false],
    'database only next read marks' => [static fn (): mixed => $databaseOnly()['transition']['next_read_marks'], [0, null, null, null, null]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint restart reader map current next37 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint restart reader map current next37 rejects empty page list'] = static function (TestRunner $t) use ($wal, $database, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->restartReadMarkReaderMapTransition($database, $releasedShm, []));
};

$tests['wal checkpoint restart reader map current next37 rejects non integer page'] = static function (TestRunner $t) use ($wal, $database, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->restartReadMarkReaderMapTransition($database, $releasedShm, ['2']));
};

$tests['wal checkpoint restart reader map current next37 rejects unsupported mode'] = static function (TestRunner $t) use ($wal, $database, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->restartReadMarkReaderMapTransition($database, $releasedShm, [2], 'passive'));
};

return $tests;
