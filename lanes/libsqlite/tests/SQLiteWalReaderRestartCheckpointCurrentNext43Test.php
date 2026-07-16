<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderRestartCheckpointPlan;

$tests = [];

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db schema before restart checkpoint')
    . $page('db siteurl before restart checkpoint')
    . $page('db autoload index before restart checkpoint')
    . $page('db plugin settings before restart checkpoint');
$salt1 = 0x43434343;
$salt2 = 0x90909090;

$makeWal = static function (array $frames, int $checkpointSequence = 43) use ($pageSize, $salt1, $salt2): string {
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

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 6) use ($pageSize): SQLiteShmIndex {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack(
        'V*',
        3007000,
        $backfill,
        43,
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
    $marks = array_map(static fn (?int $frame): int => $frame ?? 0xffffffff, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return SQLiteShmIndex::parse($header . $header . $checkpoint);
};

$wal = SQLiteWal::parse($makeWal([
    [2, 0, $page('wal frame 1 siteurl draft before current reader')],
    [3, 4, $page('wal frame 2 autoload index first commit')],
    [2, 0, $page('wal frame 3 siteurl later draft')],
    [4, 0, $page('wal frame 4 plugin setting draft')],
    [1, 0, $page('wal frame 5 schema draft')],
    [2, 4, $page('wal frame 6 siteurl final commit')],
]), null, true);

$pinnedShm = static fn (): SQLiteShmIndex => $makeShm([0, 2, 6, null, 99], [false, true, true, false, true], 1, 5);
$releasedShm = static fn (): SQLiteShmIndex => $makeShm([0, 6, null, null, null], [false, false, false, false, false], 6, 6);
$databaseOnlyShm = static fn (): SQLiteShmIndex => $makeShm([0, null, null, null, null], [true, false, false, false, false], 6, 6);

$pinnedRestart = static fn (): array => SQLiteWalReaderRestartCheckpointPlan::plan($wal, $databaseBytes, $pinnedShm(), [1, 2, 3, 4], $databasePath, 'restart');
$pinnedTruncate = static fn (): array => SQLiteWalReaderRestartCheckpointPlan::plan($wal, $databaseBytes, $pinnedShm(), [1, 2, 3, 4], $databasePath, 'truncate');
$releasedRestart = static fn (): array => SQLiteWalReaderRestartCheckpointPlan::plan($wal, $databaseBytes, $releasedShm(), [1, 2, 3, 4], $databasePath, 'restart');
$releasedTruncate = static fn (): array => SQLiteWalReaderRestartCheckpointPlan::plan($wal, $databaseBytes, $releasedShm(), [1, 2, 3, 4], $databasePath, 'truncate');
$databaseOnlyRestart = static fn (): array => SQLiteWalReaderRestartCheckpointPlan::plan($wal, $databaseBytes, $databaseOnlyShm(), [1, 2], $databasePath, 'restart');

$cases = [
    'pinned restart status' => [static fn (): mixed => $pinnedRestart()['status'], 'current-reader-pinned'],
    'pinned restart mode' => [static fn (): mixed => $pinnedRestart()['mode'], 'restart'],
    'pinned restart path' => [static fn (): mixed => $pinnedRestart()['database_path'], $databasePath],
    'pinned restart wal path' => [static fn (): mixed => $pinnedRestart()['wal_path'], $databasePath . '-wal'],
    'pinned restart busy' => [static fn (): mixed => $pinnedRestart()['checkpoint_busy'], true],
    'pinned restart reason' => [static fn (): mixed => $pinnedRestart()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'pinned restart preserves wal' => [static fn (): mixed => $pinnedRestart()['wal_action'], 'preserve_wal'],
    'pinned restart backfill count' => [static fn (): mixed => $pinnedRestart()['backfilled_frame_count'], 1],
    'pinned restart attempted count' => [static fn (): mixed => $pinnedRestart()['backfill_attempted_frame_count'], 5],
    'pinned restart pinned frame' => [static fn (): mixed => $pinnedRestart()['checkpoint_pinned_frame'], 2],
    'pinned restart current end frame' => [static fn (): mixed => $pinnedRestart()['current_reader_end_frame'], 2],
    'pinned restart next end frame' => [static fn (): mixed => $pinnedRestart()['next_reader_end_frame'], 6],
    'pinned restart next slot' => [static fn (): mixed => $pinnedRestart()['next_reader_slot'], 0],
    'pinned restart next frame' => [static fn (): mixed => $pinnedRestart()['next_reader_frame'], 6],
    'pinned restart read marks keep locked readers' => [static fn (): mixed => $pinnedRestart()['next_read_marks'], [null, 2, 6, null, null]],
    'pinned restart current sources' => [static fn (): mixed => $pinnedRestart()['current_sources'], ['database', 'wal', 'wal', 'database']],
    'pinned restart next sources' => [static fn (): mixed => $pinnedRestart()['next_sources'], ['wal', 'wal', 'wal', 'wal']],
    'pinned restart current frames' => [static fn (): mixed => $pinnedRestart()['current_frame_indexes'], [null, 1, 2, null]],
    'pinned restart next frames' => [static fn (): mixed => $pinnedRestart()['next_frame_indexes'], [5, 6, 2, 4]],
    'pinned restart changed pages' => [static fn (): mixed => $pinnedRestart()['changed_pages'], [1, 2, 4]],
    'pinned restart current keeps snapshot' => [static fn (): mixed => $pinnedRestart()['current_reader_kept_snapshot'], true],
    'pinned restart next not database only' => [static fn (): mixed => $pinnedRestart()['next_reader_uses_database'], false],
    'pinned restart not restarted header' => [static fn (): mixed => $pinnedRestart()['next_reader_uses_restarted_wal'], false],
    'pinned restart images differ' => [static fn (): mixed => $pinnedRestart()['images_match'], false],
    'pinned restart checkpointed one frame' => [static fn (): mixed => $pinnedRestart()['checkpointed_frame_count'], 1],
    'pinned restart remaining committed' => [static fn (): mixed => $pinnedRestart()['remaining_committed_frame_count'], 3],
    'pinned restart total committable' => [static fn (): mixed => $pinnedRestart()['total_committable_frame_count'], 4],
    'pinned restart operation preserves wal' => [static fn (): mixed => $pinnedRestart()['operations'][1]['op'], 'preserve'],
    'pinned restart operation reason' => [static fn (): mixed => $pinnedRestart()['operations'][1]['reason'], 'current_reader_pins_restart_checkpoint'],
    'pinned restart dependency current next43' => [static fn (): mixed => in_array('sqlite-wal-reader-restart-checkpoint-current-next43', $pinnedRestart()['dependencies'], true), true],
    'pinned restart dependency shm' => [static fn (): mixed => in_array('sqlite-shm-index', $pinnedRestart()['dependencies'], true), true],
    'pinned restart page one label next' => [static fn (): mixed => $pinnedRestart()['next_pages'][0]['label'], 'wal frame 5 schema draft'],
    'pinned restart page two current label' => [static fn (): mixed => $pinnedRestart()['current_pages'][1]['label'], 'wal frame 1 siteurl draft before current reader'],
    'pinned restart page two next label' => [static fn (): mixed => $pinnedRestart()['next_pages'][1]['label'], 'wal frame 6 siteurl final commit'],
    'pinned truncate still preserves wal' => [static fn (): mixed => $pinnedTruncate()['wal_action'], 'preserve_wal'],
    'pinned truncate status remains pinned' => [static fn (): mixed => $pinnedTruncate()['status'], 'current-reader-pinned'],
    'pinned truncate next uses wal' => [static fn (): mixed => $pinnedTruncate()['next_reader_uses_database'], false],
    'released restart status' => [static fn (): mixed => $releasedRestart()['status'], 'restart-ready'],
    'released restart not busy' => [static fn (): mixed => $releasedRestart()['checkpoint_busy'], false],
    'released restart reason' => [static fn (): mixed => $releasedRestart()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released restart action' => [static fn (): mixed => $releasedRestart()['wal_action'], 'restart_wal'],
    'released restart no pinned frame' => [static fn (): mixed => $releasedRestart()['checkpoint_pinned_frame'], null],
    'released restart current end null' => [static fn (): mixed => $releasedRestart()['current_reader_end_frame'], null],
    'released restart next frame zero' => [static fn (): mixed => $releasedRestart()['next_reader_end_frame'], 0],
    'released restart current sources old wal' => [static fn (): mixed => $releasedRestart()['current_sources'], ['wal', 'wal', 'wal', 'wal']],
    'released restart next sources database' => [static fn (): mixed => $releasedRestart()['next_sources'], ['database', 'database', 'database', 'database']],
    'released restart current frames latest' => [static fn (): mixed => $releasedRestart()['current_frame_indexes'], [5, 6, 2, 4]],
    'released restart next frames none' => [static fn (): mixed => $releasedRestart()['next_frame_indexes'], [null, null, null, null]],
    'released restart changed none' => [static fn (): mixed => $releasedRestart()['changed_pages'], []],
    'released restart images match' => [static fn (): mixed => $releasedRestart()['images_match'], true],
    'released restart next database only' => [static fn (): mixed => $releasedRestart()['next_reader_uses_database'], true],
    'released restart next uses restarted wal' => [static fn (): mixed => $releasedRestart()['next_reader_uses_restarted_wal'], true],
    'released restart wal header length' => [static fn (): mixed => $releasedRestart()['wal_bytes_length'], 32],
    'released restart checkpointed all commits' => [static fn (): mixed => $releasedRestart()['checkpointed_frame_count'], 4],
    'released restart remaining zero' => [static fn (): mixed => $releasedRestart()['remaining_committed_frame_count'], 0],
    'released restart operation writes database' => [static fn (): mixed => $releasedRestart()['operations'][0]['op'], 'write'],
    'released restart operation restarts wal' => [static fn (): mixed => $releasedRestart()['operations'][1]['reason'], 'restart_wal_header_for_next_reader'],
    'released truncate status' => [static fn (): mixed => $releasedTruncate()['status'], 'restart-ready'],
    'released truncate action' => [static fn (): mixed => $releasedTruncate()['wal_action'], 'truncate_wal'],
    'released truncate wal bytes zero' => [static fn (): mixed => $releasedTruncate()['wal_bytes_length'], 0],
    'released truncate operation truncates' => [static fn (): mixed => $releasedTruncate()['operations'][1]['op'], 'truncate'],
    'released truncate not restarted header' => [static fn (): mixed => $releasedTruncate()['next_reader_uses_restarted_wal'], false],
    'database only restart ready' => [static fn (): mixed => $databaseOnlyRestart()['status'], 'restart-ready'],
    'database only next marks reset' => [static fn (): mixed => $databaseOnlyRestart()['next_read_marks'], [0, null, null, null, null]],
    'database only next slot' => [static fn (): mixed => $databaseOnlyRestart()['next_reader_slot'], 1],
    'database only next frame' => [static fn (): mixed => $databaseOnlyRestart()['next_reader_frame'], 0],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader restart checkpoint current next43 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader restart checkpoint current next43 rejects empty path'] = static function (TestRunner $t) use ($wal, $databaseBytes, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderRestartCheckpointPlan::plan($wal, $databaseBytes, $releasedShm(), [1], ''));
};

$tests['wal reader restart checkpoint current next43 rejects empty pages'] = static function (TestRunner $t) use ($wal, $databaseBytes, $releasedShm, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderRestartCheckpointPlan::plan($wal, $databaseBytes, $releasedShm(), [], $databasePath));
};

$tests['wal reader restart checkpoint current next43 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes, $releasedShm, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderRestartCheckpointPlan::plan($wal, $databaseBytes, $releasedShm(), ['2'], $databasePath));
};

$tests['wal reader restart checkpoint current next43 rejects passive mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $releasedShm, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderRestartCheckpointPlan::plan($wal, $databaseBytes, $releasedShm(), [1], $databasePath, 'passive'));
};

return $tests;
