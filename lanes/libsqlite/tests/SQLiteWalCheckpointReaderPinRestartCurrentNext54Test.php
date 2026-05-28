<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x54545454;
$salt2 = 0x94949494;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = $page('db page 1 wp_options schema')
    . $page('db page 2 siteurl baseline')
    . $page('db page 3 autoload index baseline')
    . $page('db page 4 plugin baseline');

$makeWal = static function (array $frames, int $checkpointSequence = 54) use ($pageSize, $salt1, $salt2): string {
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

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 7) use ($pageSize): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack(
        'V*',
        3007000,
        $backfill,
        154,
        $pageSizeField,
        $mxFrame,
        4,
        0x10101010,
        0x20202020,
        0x30303030,
        0x40404040,
        0x50505050,
        0x60606060
    );
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$wal = SQLiteWal::parse($makeWal([
    [2, 0, $page('frame 1 siteurl draft before pinned reader')],
    [3, 3, $page('frame 2 autoload commit before pinned reader')],
    [2, 0, $page('frame 3 siteurl edit after pinned reader')],
    [4, 0, $page('frame 4 plugin draft')],
    [2, 0, $page('frame 5 siteurl final before commit')],
    [4, 4, $page('frame 6 plugin commit after reader')],
    [3, 4, $page('frame 7 autoload index final commit')],
]), null, true);

$pinnedShm = SQLiteShmIndex::parse($makeShm([0, 2, 7, null, null], [false, true, true, false, false], 1, 6));
$releasedShm = SQLiteShmIndex::parse($makeShm([0, 7, null, null, null], [false, false, false, false, false], 7, 7));

$restart = static fn (): array => $wal->checkpointReaderPinRestartRetryCurrentNext($database, $pinnedShm, $releasedShm, [1, 2, 3, 4], 'restart');
$truncate = static fn (): array => $wal->checkpointReaderPinRestartRetryCurrentNext($database, $pinnedShm, $releasedShm, [2, 3, 4], 'truncate');
$alreadyReleased = static fn (): array => $wal->checkpointReaderPinRestartRetryCurrentNext($database, $releasedShm, $releasedShm, [2, 3], 'restart');

$cases = [
    'restart status' => [static fn (): mixed => $restart()['status'], 'reader-pin-restart-current-next'],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart first status pinned' => [static fn (): mixed => $restart()['first']['status'], 'current-reader-pinned'],
    'restart retry status ready' => [static fn (): mixed => $restart()['retry']['status'], 'restart-ready'],
    'restart current reader frame two' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart retry reader frame zero' => [static fn (): mixed => $restart()['retry_reader_end_frame'], 0],
    'restart first checkpoint busy' => [static fn (): mixed => $restart()['first']['checkpoint']['busy'], true],
    'restart first reason' => [static fn (): mixed => $restart()['first']['checkpoint']['reason'], 'reader_blocks_checkpoint_completion'],
    'restart first preserves wal' => [static fn (): mixed => $restart()['first']['checkpoint']['wal_action'], 'preserve_wal'],
    'restart retry not busy' => [static fn (): mixed => $restart()['retry']['checkpoint']['busy'], false],
    'restart retry action' => [static fn (): mixed => $restart()['retry']['checkpoint']['wal_action'], 'restart_wal'],
    'restart retry reset ready' => [static fn (): mixed => $restart()['retry_reset_ready'], true],
    'restart current kept snapshot' => [static fn (): mixed => $restart()['current_reader_kept_snapshot'], true],
    'restart next uses database' => [static fn (): mixed => $restart()['next_reader_uses_database'], true],
    'restart next uses restarted wal' => [static fn (): mixed => $restart()['next_reader_uses_restarted_wal'], true],
    'restart images differ' => [static fn (): mixed => $restart()['images_match'], false],
    'restart current sources' => [static fn (): mixed => $restart()['current_reader_sources'], ['database', 'wal', 'wal', 'missing']],
    'restart next sources' => [static fn (): mixed => $restart()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'restart current frame indexes' => [static fn (): mixed => $restart()['current_reader_frame_indexes'], [null, 1, 2, null]],
    'restart next frame indexes' => [static fn (): mixed => $restart()['next_reader_frame_indexes'], [null, null, null, null]],
    'restart current page two before reader' => [static fn (): mixed => str_contains($restart()['current_reader'][1]['image'], 'before pinned reader'), true],
    'restart next page two final' => [static fn (): mixed => str_contains($restart()['next_reader'][1]['image'], 'siteurl final'), true],
    'restart current page three old commit' => [static fn (): mixed => str_contains($restart()['current_reader'][2]['image'], 'before pinned reader'), true],
    'restart next page three final commit' => [static fn (): mixed => str_contains($restart()['next_reader'][2]['image'], 'autoload index final'), true],
    'restart current page four error' => [static fn (): mixed => $restart()['current_reader'][3]['error'], 'SQLite WAL reader page 4 is beyond the committed database size'],
    'restart next page four committed' => [static fn (): mixed => str_contains($restart()['next_reader'][3]['image'], 'plugin commit'), true],
    'restart current errors' => [static fn (): mixed => count($restart()['current_reader_errors']), 1],
    'restart next errors empty' => [static fn (): mixed => $restart()['next_reader_errors'], []],
    'restart first read marks retained pinned slot' => [static fn (): mixed => $restart()['first']['next_read_marks'], [null, 2, 7, null, null]],
    'restart retry read marks database reader' => [static fn (): mixed => $restart()['retry']['next_read_marks'], [0, null, null, null, null]],
    'restart retry header sequence' => [static fn (): mixed => $restart()['retry']['next_wal_header']['checkpoint_sequence'], 55],
    'restart retry database page count' => [static fn (): mixed => $restart()['retry']['checkpoint']['database_page_count'], 4],
    'restart dependency marker' => [static fn (): mixed => in_array('wal-checkpoint-reader-pin-restart-retry-current-next54', $restart()['dependencies'], true), true],
    'restart dependency shm' => [static fn (): mixed => in_array('sqlite-shm-index', $restart()['dependencies'], true), true],
    'restart dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-restart', $restart()['dependencies'], true), true],
    'truncate status' => [static fn (): mixed => $truncate()['status'], 'reader-pin-restart-current-next'],
    'truncate retry action' => [static fn (): mixed => $truncate()['retry']['checkpoint']['wal_action'], 'truncate_wal'],
    'truncate next database true' => [static fn (): mixed => $truncate()['next_reader_uses_database'], true],
    'truncate next restarted wal false' => [static fn (): mixed => $truncate()['next_reader_uses_restarted_wal'], false],
    'truncate retry frame zero' => [static fn (): mixed => $truncate()['retry_reader_end_frame'], 0],
    'truncate next sources' => [static fn (): mixed => $truncate()['next_reader_sources'], ['database', 'database', 'database']],
    'truncate page two final' => [static fn (): mixed => str_contains($truncate()['next_reader'][0]['image'], 'siteurl final'), true],
    'truncate page three final' => [static fn (): mixed => str_contains($truncate()['next_reader'][1]['image'], 'autoload index final'), true],
    'truncate page four committed' => [static fn (): mixed => str_contains($truncate()['next_reader'][2]['image'], 'plugin commit'), true],
    'truncate current sources' => [static fn (): mixed => $truncate()['current_reader_sources'], ['wal', 'wal', 'missing']],
    'truncate current errors one' => [static fn (): mixed => $truncate()['current_reader_errors'], ['SQLite WAL reader page 4 is beyond the committed database size']],
    'already released status' => [static fn (): mixed => $alreadyReleased()['status'], 'restart-retry-restart-ready'],
    'already released current frame null' => [static fn (): mixed => $alreadyReleased()['current_reader_end_frame'], null],
    'already released kept snapshot false' => [static fn (): mixed => $alreadyReleased()['current_reader_kept_snapshot'], false],
    'already released current sources database' => [static fn (): mixed => $alreadyReleased()['current_reader_sources'], ['database', 'database']],
    'already released next sources database' => [static fn (): mixed => $alreadyReleased()['next_reader_sources'], ['database', 'database']],
    'already released images match false after checkpoint' => [static fn (): mixed => $alreadyReleased()['images_match'], false],
    'already released retry ready' => [static fn (): mixed => $alreadyReleased()['retry_reset_ready'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint reader pin restart current next54 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint reader pin restart current next54 rejects empty pages'] = static function (TestRunner $t) use ($wal, $database, $pinnedShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinRestartRetryCurrentNext($database, $pinnedShm, $releasedShm, []));
};

$tests['wal checkpoint reader pin restart current next54 rejects non integer page'] = static function (TestRunner $t) use ($wal, $database, $pinnedShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinRestartRetryCurrentNext($database, $pinnedShm, $releasedShm, ['2']));
};

$tests['wal checkpoint reader pin restart current next54 rejects passive mode'] = static function (TestRunner $t) use ($wal, $database, $pinnedShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinRestartRetryCurrentNext($database, $pinnedShm, $releasedShm, [2], 'passive'));
};

return $tests;
