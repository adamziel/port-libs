<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x83112233;
$salt2 = 0x83445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db83 page1 schema baseline')
    . $page('db83 page2 siteurl baseline')
    . $page('db83 page3 autoload baseline')
    . $page('db83 page4 plugin baseline')
    . $page('db83 page5 transient baseline');

$makeWal = static function (array $frames, int $checkpointSequence = 83) use ($pageSize, $salt1, $salt2): string {
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
    $header = pack('V*', 3007000, $backfill, 183, $pageSizeField, $mxFrame, 5, 1, 2, 0x83112233, 0x83445566, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$wal = SQLiteWal::parse($makeWal([
    [2, 0, $page('wal83 frame1 siteurl before old reader')],
    [3, 3, $page('wal83 frame2 autoload first commit')],
    [2, 0, $page('wal83 frame3 siteurl after old reader')],
    [4, 0, $page('wal83 frame4 plugin draft')],
    [5, 0, $page('wal83 frame5 transient draft')],
    [4, 5, $page('wal83 frame6 plugin committed')],
    [2, 5, $page('wal83 frame7 siteurl final')],
]), null, true);

$currentShm = SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4));
$nextReaderShm = SQLiteShmIndex::parse($makeShm([0, 2, 7, null, null], [false, true, true, false, false], 1, 6));
$currentReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, 7, null, null], [false, false, true, false, false], 2, 7));
$allReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7));

$restart = static fn (): array => $wal->checkpointReaderPinRestartCurrentSourceNext(
    $databaseBytes,
    $currentShm,
    $nextReaderShm,
    $currentReleasedShm,
    $allReleasedShm,
    [1, 2, 3, 4, 5],
    'restart'
);
$truncate = static fn (): array => $wal->checkpointReaderPinRestartCurrentSourceNext(
    $databaseBytes,
    $currentShm,
    $nextReaderShm,
    $currentReleasedShm,
    $allReleasedShm,
    [2, 4, 5],
    'truncate'
);

$cases = [
    'restart status' => [static fn (): mixed => $restart()['status'], 'reader-pin-next-reader-blocks-restart-current-source-next83'],
    'restart keeps base next76 dependency' => [static fn (): mixed => in_array('wal-reader-pin-checkpoint-restart-current-next76', $restart()['dependencies'], true), true],
    'restart adds current source dependency' => [static fn (): mixed => in_array('wal-reader-pin-checkpoint-restart-current-source-next83', $restart()['dependencies'], true), true],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart first is pinned' => [static fn (): mixed => $restart()['first']['status'], 'current-reader-pinned'],
    'restart next pin is pinned' => [static fn (): mixed => $restart()['next_pin']['status'], 'current-reader-pinned'],
    'restart current release is pinned' => [static fn (): mixed => $restart()['after_current_release']['status'], 'current-reader-pinned'],
    'restart all release is ready' => [static fn (): mixed => $restart()['after_all_release']['status'], 'restart-ready'],
    'restart current frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart next frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 7],
    'restart final frame' => [static fn (): mixed => $restart()['final_reader_end_frame'], 0],
    'restart current source names' => [static fn (): mixed => $restart()['current_source_names'], ['database', 'preserved-wal', 'preserved-wal', 'missing', 'missing']],
    'restart next source names after current release' => [static fn (): mixed => $restart()['next_source_names_after_current_release'], ['database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'restart final source names after all release' => [static fn (): mixed => $restart()['final_source_names_after_all_release'], ['reset-database', 'reset-database', 'reset-database', 'reset-database', 'reset-database']],
    'restart next not mixed because next reader pins latest commit' => [static fn (): mixed => $restart()['next_mixed_checkpoint_database_and_wal'], false],
    'restart final database only' => [static fn (): mixed => $restart()['final_uses_reset_database_only'], true],
    'restart current page one not checkpoint matched' => [static fn (): mixed => $restart()['current_source_rows'][0]['checkpoint_database_has_page'], false],
    'restart current page two preserved wal' => [static fn (): mixed => $restart()['current_source_rows'][1]['current_source'], 'preserved-wal'],
    'restart current page three preserved wal' => [static fn (): mixed => $restart()['current_source_rows'][2]['current_source'], 'preserved-wal'],
    'restart current page four missing' => [static fn (): mixed => $restart()['current_source_rows'][3]['current_source'], 'missing'],
    'restart current page five missing' => [static fn (): mixed => $restart()['current_source_rows'][4]['current_source'], 'missing'],
    'restart next page one database' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][0]['current_source'], 'database'],
    'restart next page two checkpoint database' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][1]['current_source'], 'checkpoint-database'],
    'restart next page three checkpoint database' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][2]['current_source'], 'checkpoint-database'],
    'restart next page four checkpoint database' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][3]['current_source'], 'checkpoint-database'],
    'restart next page five checkpoint database' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][4]['current_source'], 'checkpoint-database'],
    'restart next page two checkpoint matched' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][1]['checkpoint_database_has_page'], true],
    'restart next page three checkpoint matched' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][2]['checkpoint_database_has_page'], true],
    'restart next page four checkpoint matched' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][3]['checkpoint_database_has_page'], true],
    'restart next page five checkpoint matched' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][4]['checkpoint_database_has_page'], true],
    'restart final page one reset database' => [static fn (): mixed => $restart()['final_source_rows_after_all_release'][0]['current_source'], 'reset-database'],
    'restart final page two reset database' => [static fn (): mixed => $restart()['final_source_rows_after_all_release'][1]['current_source'], 'reset-database'],
    'restart final page three reset database' => [static fn (): mixed => $restart()['final_source_rows_after_all_release'][2]['current_source'], 'reset-database'],
    'restart final page four reset database' => [static fn (): mixed => $restart()['final_source_rows_after_all_release'][3]['current_source'], 'reset-database'],
    'restart final page five reset database' => [static fn (): mixed => $restart()['final_source_rows_after_all_release'][4]['current_source'], 'reset-database'],
    'restart current sources stay old snapshot' => [static fn (): mixed => str_contains($restart()['current_reader'][1]['image'], 'before old reader'), true],
    'restart next source sees final siteurl' => [static fn (): mixed => str_contains($restart()['next_source_rows_after_current_release'][1]['image'], 'siteurl final'), true],
    'restart final source sees final siteurl' => [static fn (): mixed => str_contains($restart()['final_source_rows_after_all_release'][1]['image'], 'siteurl final'), true],
    'restart next source sees committed plugin' => [static fn (): mixed => str_contains($restart()['next_source_rows_after_current_release'][3]['image'], 'plugin committed'), true],
    'restart final source sees committed plugin' => [static fn (): mixed => str_contains($restart()['final_source_rows_after_all_release'][3]['image'], 'plugin committed'), true],
    'restart after current release checkpointed frames' => [static fn (): mixed => $restart()['after_current_release_checkpointed_frame_count'], 4],
    'restart after all release checkpointed frames' => [static fn (): mixed => $restart()['after_all_release_checkpointed_frame_count'], 4],
    'restart current release reason' => [static fn (): mixed => $restart()['after_current_release']['checkpoint']['reason'], 'reader_blocks_wal_reset'],
    'restart all release reason' => [static fn (): mixed => $restart()['after_all_release']['checkpoint']['reason'], 'restart_checkpoint_can_reset_wal'],
    'restart current release action' => [static fn (): mixed => $restart()['after_current_release']['checkpoint']['wal_action'], 'preserve_wal'],
    'restart all release action' => [static fn (): mixed => $restart()['after_all_release']['checkpoint']['wal_action'], 'restart_wal'],
    'restart next final images match' => [static fn (): mixed => $restart()['next_final_images_match'], true],
    'restart current next images differ' => [static fn (): mixed => $restart()['current_next_images_match'], false],
    'truncate status' => [static fn (): mixed => $truncate()['status'], 'reader-pin-next-reader-blocks-restart-current-source-next83'],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate final action' => [static fn (): mixed => $truncate()['after_all_release']['checkpoint']['wal_action'], 'truncate_wal'],
    'truncate current source names' => [static fn (): mixed => $truncate()['current_source_names'], ['preserved-wal', 'missing', 'missing']],
    'truncate next source names' => [static fn (): mixed => $truncate()['next_source_names_after_current_release'], ['checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'truncate final source names' => [static fn (): mixed => $truncate()['final_source_names_after_all_release'], ['reset-database', 'reset-database', 'reset-database']],
    'truncate final database only' => [static fn (): mixed => $truncate()['final_uses_reset_database_only'], true],
    'truncate page two checkpoint matched' => [static fn (): mixed => $truncate()['next_source_rows_after_current_release'][0]['checkpoint_database_has_page'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader pin checkpoint current source next83 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader pin checkpoint current source next83 rejects empty page list'] = static function (TestRunner $t) use ($wal, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinRestartCurrentSourceNext($databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, []));
};

$tests['wal reader pin checkpoint current source next83 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinRestartCurrentSourceNext($databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, ['2']));
};

$tests['wal reader pin checkpoint current source next83 rejects passive mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinRestartCurrentSourceNext($databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2], 'passive'));
};

return $tests;
