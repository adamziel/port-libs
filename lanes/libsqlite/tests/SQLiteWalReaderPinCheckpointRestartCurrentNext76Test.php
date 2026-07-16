<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x76112233;
$salt2 = 0x76445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db76 page1 wp schema')
    . $page('db76 page2 siteurl base')
    . $page('db76 page3 autoload base')
    . $page('db76 page4 plugin base')
    . $page('db76 page5 transient base');

$makeWal = static function (array $frames, int $checkpointSequence = 76) use ($pageSize, $salt1, $salt2): string {
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
        176,
        $pageSizeField,
        $mxFrame,
        5,
        0x11111111,
        0x22222222,
        0x33333333,
        0x44444444,
        0x55555555,
        0x66666666
    );
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$wal = SQLiteWal::parse($makeWal([
    [2, 0, $page('wal76 frame1 siteurl before old reader')],
    [3, 3, $page('wal76 frame2 autoload commit before old reader')],
    [2, 0, $page('wal76 frame3 siteurl after old reader')],
    [4, 0, $page('wal76 frame4 plugin draft after old reader')],
    [5, 0, $page('wal76 frame5 transient draft')],
    [4, 5, $page('wal76 frame6 plugin commit before next reader')],
    [2, 5, $page('wal76 frame7 siteurl final before next reader')],
]), null, true);

$currentShm = SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4));
$nextReaderShm = SQLiteShmIndex::parse($makeShm([0, 2, 7, null, null], [false, true, true, false, false], 1, 6));
$currentReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, 7, null, null], [false, false, true, false, false], 2, 7));
$allReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7));

$restart = static fn (): array => $wal->checkpointReaderPinRestartCurrentNext($databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [1, 2, 3, 4, 5], 'restart');
$truncate = static fn (): array => $wal->checkpointReaderPinRestartCurrentNext($databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2, 4, 5], 'truncate');

$cases = [
    'restart status' => [static fn (): mixed => $restart()['status'], 'reader-pin-next-reader-blocks-restart-current-next76'],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart first status pinned' => [static fn (): mixed => $restart()['first']['status'], 'current-reader-pinned'],
    'restart next pin status pinned' => [static fn (): mixed => $restart()['next_pin']['status'], 'current-reader-pinned'],
    'restart after current release still pinned' => [static fn (): mixed => $restart()['after_current_release']['status'], 'current-reader-pinned'],
    'restart after all release ready' => [static fn (): mixed => $restart()['after_all_release']['status'], 'restart-ready'],
    'restart current reader frame two' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart next reader frame seven' => [static fn (): mixed => $restart()['next_reader_end_frame'], 7],
    'restart final reader frame zero' => [static fn (): mixed => $restart()['final_reader_end_frame'], 0],
    'restart first busy reason' => [static fn (): mixed => $restart()['first']['checkpoint']['reason'], 'reader_blocks_checkpoint_completion'],
    'restart current release busy reason' => [static fn (): mixed => $restart()['after_current_release']['checkpoint']['reason'], 'reader_blocks_wal_reset'],
    'restart all release reason' => [static fn (): mixed => $restart()['after_all_release']['checkpoint']['reason'], 'restart_checkpoint_can_reset_wal'],
    'restart first action preserve' => [static fn (): mixed => $restart()['first']['checkpoint']['wal_action'], 'preserve_wal'],
    'restart current release action preserve' => [static fn (): mixed => $restart()['after_current_release']['checkpoint']['wal_action'], 'preserve_wal'],
    'restart final action restart' => [static fn (): mixed => $restart()['after_all_release']['checkpoint']['wal_action'], 'restart_wal'],
    'restart current read marks' => [static fn (): mixed => $restart()['first']['next_read_marks'], [null, 2, null, null, null]],
    'restart next pin read marks' => [static fn (): mixed => $restart()['next_pin']['next_read_marks'], [null, 2, 7, null, null]],
    'restart after current release read marks' => [static fn (): mixed => $restart()['after_current_release']['next_read_marks'], [0, null, 7, null, null]],
    'restart final read marks' => [static fn (): mixed => $restart()['after_all_release']['next_read_marks'], [0, null, null, null, null]],
    'restart next reader slot after old pin' => [static fn (): mixed => $restart()['first']['next_reader_slot'], 0],
    'restart next reader frame after old pin' => [static fn (): mixed => $restart()['first']['next_reader_frame'], 7],
    'restart next pin chooses database slot' => [static fn (): mixed => $restart()['next_pin']['next_reader_slot'], 0],
    'restart after current release reset blocked' => [static fn (): mixed => $restart()['after_current_release']['next_read_mark_plan']['reset_blocked'], false],
    'restart after current release checkpoint can finish' => [static fn (): mixed => $restart()['after_current_release']['next_read_mark_plan']['checkpoint_can_finish'], true],
    'restart next reader blocks reset' => [static fn (): mixed => $restart()['next_reader_blocks_reset'], true],
    'restart final reset ready' => [static fn (): mixed => $restart()['final_reset_ready'], true],
    'restart current kept snapshot' => [static fn (): mixed => $restart()['current_reader_kept_snapshot'], true],
    'restart next kept snapshot' => [static fn (): mixed => $restart()['next_reader_kept_snapshot'], true],
    'restart current sources' => [static fn (): mixed => $restart()['current_reader_sources'], ['database', 'wal', 'wal', 'missing', 'missing']],
    'restart next sources' => [static fn (): mixed => $restart()['next_reader_sources'], ['database', 'wal', 'wal', 'wal', 'wal']],
    'restart final sources' => [static fn (): mixed => $restart()['final_reader_sources'], ['database', 'database', 'database', 'database', 'database']],
    'restart current frame indexes' => [static fn (): mixed => $restart()['current_reader_frame_indexes'], [null, 1, 2, null, null]],
    'restart next frame indexes' => [static fn (): mixed => $restart()['next_reader_frame_indexes'], [null, 7, 2, 6, 5]],
    'restart final frame indexes' => [static fn (): mixed => $restart()['final_reader_frame_indexes'], [null, null, null, null, null]],
    'restart current errors' => [static fn (): mixed => $restart()['current_reader_errors'], ['SQLite WAL reader page 4 is beyond the committed database size', 'SQLite WAL reader page 5 is beyond the committed database size']],
    'restart next errors empty' => [static fn (): mixed => $restart()['next_reader_errors'], []],
    'restart final errors empty' => [static fn (): mixed => $restart()['final_reader_errors'], []],
    'restart current siteurl old' => [static fn (): mixed => str_contains($restart()['current_reader'][1]['image'], 'before old reader'), true],
    'restart next siteurl final' => [static fn (): mixed => str_contains($restart()['next_reader'][1]['image'], 'siteurl final'), true],
    'restart next plugin committed' => [static fn (): mixed => str_contains($restart()['next_reader'][3]['image'], 'plugin commit'), true],
    'restart final siteurl checkpointed' => [static fn (): mixed => str_contains($restart()['final_reader'][1]['image'], 'siteurl final'), true],
    'restart final autoload checkpointed' => [static fn (): mixed => str_contains($restart()['final_reader'][2]['image'], 'autoload commit'), true],
    'restart final plugin checkpointed' => [static fn (): mixed => str_contains($restart()['final_reader'][3]['image'], 'plugin commit'), true],
    'restart final transient checkpointed' => [static fn (): mixed => str_contains($restart()['final_reader'][4]['image'], 'transient draft'), true],
    'restart current next images differ' => [static fn (): mixed => $restart()['current_next_images_match'], false],
    'restart next final images differ only by source' => [static fn (): mixed => $restart()['next_final_images_match'], true],
    'restart dependency marker' => [static fn (): mixed => in_array('wal-reader-pin-checkpoint-restart-current-next76', $restart()['dependencies'], true), true],
    'restart dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-restart', $restart()['dependencies'], true), true],
    'truncate status' => [static fn (): mixed => $truncate()['status'], 'reader-pin-next-reader-blocks-restart-current-next76'],
    'truncate final action' => [static fn (): mixed => $truncate()['after_all_release']['checkpoint']['wal_action'], 'truncate_wal'],
    'truncate final frame zero' => [static fn (): mixed => $truncate()['final_reader_end_frame'], 0],
    'truncate final sources' => [static fn (): mixed => $truncate()['final_reader_sources'], ['database', 'database', 'database']],
    'truncate next sources' => [static fn (): mixed => $truncate()['next_reader_sources'], ['wal', 'wal', 'wal']],
    'truncate current sources' => [static fn (): mixed => $truncate()['current_reader_sources'], ['wal', 'missing', 'missing']],
    'truncate next reader blocks reset' => [static fn (): mixed => $truncate()['next_reader_blocks_reset'], true],
    'truncate final reset ready' => [static fn (): mixed => $truncate()['final_reset_ready'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader pin checkpoint restart current next76 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader pin checkpoint restart current next76 rejects empty page list'] = static function (TestRunner $t) use ($wal, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinRestartCurrentNext($databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, []));
};

$tests['wal reader pin checkpoint restart current next76 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinRestartCurrentNext($databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, ['2']));
};

$tests['wal reader pin checkpoint restart current next76 rejects passive mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinRestartCurrentNext($databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2], 'passive'));
};

return $tests;
