<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x73112233;
$salt2 = 0x73445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db73 page1 wp schema')
    . $page('db73 page2 siteurl base')
    . $page('db73 page3 autoload base')
    . $page('db73 page4 plugin base');
$databasePath = '/tmp/wp-reader-pin-restart-current-next73.sqlite';

$makeWal = static function (array $frames, int $checkpointSequence = 73) use ($pageSize, $salt1, $salt2): string {
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

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 5) use ($pageSize): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack(
        'V*',
        3007000,
        $backfill,
        173,
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
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$wal = SQLiteWal::parse($makeWal([
    [2, 0, $page('wal73 frame1 siteurl before reader')],
    [3, 3, $page('wal73 frame2 autoload commit before reader')],
    [2, 0, $page('wal73 frame3 siteurl after reader')],
    [4, 0, $page('wal73 frame4 plugin draft')],
    [4, 4, $page('wal73 frame5 plugin committed before restart')],
]), null, true);

$currentShm = SQLiteShmIndex::parse($makeShm([0, 2, 5, null, null], [false, true, true, false, false], 1, 4));
$releasedShm = SQLiteShmIndex::parse($makeShm([0, 5, null, null, null], [false, false, false, false, false], 5, 5));
$stillPinnedShm = SQLiteShmIndex::parse($makeShm([0, 2, 5, null, null], [false, true, false, false, false], 1, 4));

$transactions = [
    [
        'pages' => [
            2 => $page('wal73 frame next siteurl after restart'),
            4 => $page('wal73 frame next plugin after restart'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ],
];

$restartPlan = static fn (): array => SQLiteWalAppendPlan::checkpointRestartAppendReaderCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    $currentShm,
    $releasedShm,
    'restart',
);

$truncatePlan = static fn (): array => SQLiteWalAppendPlan::checkpointRestartAppendReaderCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    $currentShm,
    $releasedShm,
    'truncate',
);

$uncommittedPlan = static fn (): array => SQLiteWalAppendPlan::checkpointRestartAppendReaderCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    [[
        'pages' => [2 => $page('wal73 uncommitted draft after restart')],
        'database_page_count' => 4,
        'commit' => false,
    ]],
    [2, 4],
    $currentShm,
    $releasedShm,
    'restart',
);

$cases = [
    'restart status' => [static fn (): mixed => $restartPlan()['status'], 'reader-pin-restart-append-current-next'],
    'restart reason' => [static fn (): mixed => $restartPlan()['reason'], 'released_reader_restart_checkpoint_then_append_advances_next_snapshot'],
    'restart mode' => [static fn (): mixed => $restartPlan()['mode'], 'restart'],
    'restart first busy' => [static fn (): mixed => $restartPlan()['first']['checkpoint']['busy'], true],
    'restart first action preserve' => [static fn (): mixed => $restartPlan()['first']['checkpoint']['wal_action'], 'preserve_wal'],
    'restart retry ready' => [static fn (): mixed => $restartPlan()['retry']['status'], 'restart-ready'],
    'restart retry action' => [static fn (): mixed => $restartPlan()['retry']['checkpoint']['wal_action'], 'restart_wal'],
    'restart retry reset ready' => [static fn (): mixed => $restartPlan()['retry_reset_ready'], true],
    'restart current frame two' => [static fn (): mixed => $restartPlan()['current_reader_end_frame'], 2],
    'restart next frame two' => [static fn (): mixed => $restartPlan()['next_reader_end_frame'], 2],
    'restart append starts restarted frame one' => [static fn (): mixed => $restartPlan()['append']['start_frame'], 1],
    'restart append ends frame two' => [static fn (): mixed => $restartPlan()['append']['end_frame'], 2],
    'restart append committed count' => [static fn (): mixed => $restartPlan()['append']['committed_transaction_count'], 1],
    'restart append uncommitted count' => [static fn (): mixed => $restartPlan()['append']['uncommitted_transaction_count'], 0],
    'restart current sources' => [static fn (): mixed => $restartPlan()['current_reader_sources'], ['wal', 'wal', 'error']],
    'restart next sources' => [static fn (): mixed => $restartPlan()['next_reader_sources'], ['wal', 'database', 'wal']],
    'restart current frame indexes' => [static fn (): mixed => $restartPlan()['current_reader_frame_indexes'], [1, 2, null]],
    'restart next frame indexes' => [static fn (): mixed => $restartPlan()['next_reader_frame_indexes'], [1, null, 2]],
    'restart current has one error' => [static fn (): mixed => count($restartPlan()['current_reader_errors']), 1],
    'restart next errors empty' => [static fn (): mixed => $restartPlan()['next_reader_errors'], []],
    'restart current siteurl before reader' => [static fn (): mixed => str_contains($restartPlan()['current_reader'][0]['image'], 'before reader'), true],
    'restart next siteurl appended' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][0]['image'], 'next siteurl'), true],
    'restart next autoload checkpoint database' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][1]['image'], 'autoload commit'), true],
    'restart next plugin appended' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][2]['image'], 'next plugin'), true],
    'restart current kept snapshot' => [static fn (): mixed => $restartPlan()['current_reader_kept_snapshot'], true],
    'restart next checkpoint database' => [static fn (): mixed => $restartPlan()['next_uses_checkpoint_database'], true],
    'restart next restarted generation' => [static fn (): mixed => $restartPlan()['next_uses_restarted_generation'], true],
    'restart next appended wal' => [static fn (): mixed => $restartPlan()['next_uses_appended_wal'], true],
    'restart images differ' => [static fn (): mixed => $restartPlan()['images_match'], false],
    'restart hidden frames' => [static fn (): mixed => $restartPlan()['frames_hidden_from_current'], [3, 4, 5]],
    'restart visible next frames' => [static fn (): mixed => $restartPlan()['frames_visible_to_next'], [1, 2]],
    'restart dependency marker' => [static fn (): mixed => in_array('sqlite-wal-reader-pin-restart-snapshot-current-next73', $restartPlan()['dependencies'], true), true],
    'restart dependency append' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $restartPlan()['dependencies'], true), true],
    'restart dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-restart', $restartPlan()['dependencies'], true), true],
    'restart wal path' => [static fn (): mixed => $restartPlan()['wal_path'], $databasePath . '-wal'],
    'truncate status' => [static fn (): mixed => $truncatePlan()['status'], 'reader-pin-restart-append-current-next'],
    'truncate retry action' => [static fn (): mixed => $truncatePlan()['retry']['checkpoint']['wal_action'], 'truncate_wal'],
    'truncate next restarted generation' => [static fn (): mixed => $truncatePlan()['next_uses_restarted_generation'], true],
    'truncate next sources' => [static fn (): mixed => $truncatePlan()['next_reader_sources'], ['wal', 'database', 'wal']],
    'truncate append starts frame one' => [static fn (): mixed => $truncatePlan()['append']['start_frame'], 1],
    'truncate visible frames' => [static fn (): mixed => $truncatePlan()['frames_visible_to_next'], [1, 2]],
    'uncommitted status' => [static fn (): mixed => $uncommittedPlan()['status'], 'reader-pin-restart-append-current-next'],
    'uncommitted reason' => [static fn (): mixed => $uncommittedPlan()['reason'], 'released_reader_restart_checkpoint_append_has_no_committed_next_snapshot'],
    'uncommitted next frame zero' => [static fn (): mixed => $uncommittedPlan()['next_reader_end_frame'], 1],
    'uncommitted committed count' => [static fn (): mixed => $uncommittedPlan()['append']['committed_transaction_count'], 0],
    'uncommitted appended wal false' => [static fn (): mixed => $uncommittedPlan()['next_uses_appended_wal'], false],
    'uncommitted visible frames include draft frame' => [static fn (): mixed => $uncommittedPlan()['frames_visible_to_next'], [1]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader pin restart snapshot current next73 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader pin restart snapshot current next73 rejects empty database path'] = static function (TestRunner $t) use ($wal, $databaseBytes, $transactions, $currentShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointRestartAppendReaderCurrentNext($wal, $databaseBytes, '', $transactions, [2], $currentShm, $releasedShm));
};

$tests['wal reader pin restart snapshot current next73 rejects empty pages'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions, $currentShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointRestartAppendReaderCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [], $currentShm, $releasedShm));
};

$tests['wal reader pin restart snapshot current next73 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions, $currentShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointRestartAppendReaderCurrentNext($wal, $databaseBytes, $databasePath, $transactions, ['2'], $currentShm, $releasedShm));
};

$tests['wal reader pin restart snapshot current next73 rejects passive mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions, $currentShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointRestartAppendReaderCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [2], $currentShm, $releasedShm, 'passive'));
};

$tests['wal reader pin restart snapshot current next73 rejects still pinned retry'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions, $currentShm, $stillPinnedShm): void {
    $t->throws(RuntimeException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointRestartAppendReaderCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [2], $currentShm, $stillPinnedShm));
};

return $tests;
