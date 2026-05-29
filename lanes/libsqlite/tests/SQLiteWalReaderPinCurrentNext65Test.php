<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x65656565;
$salt2 = 0x25252525;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0", STR_PAD_RIGHT);
$databaseBytes = $page('db page one schema before reader')
    . $page('db page two siteurl before reader')
    . $page('db page three autoload index before reader');

$walHeaderBytes = static function () use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 65, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};

$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$baseWalBytes = static function () use ($walHeaderBytes, $appendFrame, $page): string {
    $bytes = $walHeaderBytes();
    $seed = SQLiteWal::checksumPair(substr($bytes, 0, 24), false);
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('wal frame one siteurl draft before pin'));
    $bytes = $appendFrame($bytes, $seed, 3, 3, $page('wal frame two autoload commit current pin'));
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('wal frame three later draft hidden'));

    return $bytes;
};

$baseWal = static fn (): SQLiteWal => SQLiteWal::parse($baseWalBytes(), null, true);
$transactions = static fn (): array => [
    [
        'pages' => [
            2 => $page('writer committed siteurl next reader'),
            4 => $page('writer committed plugin option page'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ],
    [
        'pages' => [
            3 => $page('writer draft autoload not committed'),
            5 => $page('writer draft future option'),
        ],
        'commit' => false,
    ],
];

$plan = static fn (): array => SQLiteWalAppendPlan::readerPinCurrentNext(
    $baseWal(),
    $databaseBytes,
    $databasePath,
    $transactions(),
    [1, 2, 3, 4, 5],
    [0, 2, null, 99]
);
$uncommittedOnly = static fn (): array => SQLiteWalAppendPlan::readerPinCurrentNext(
    $baseWal(),
    $databaseBytes,
    $databasePath,
    [['pages' => [2 => $page('only draft update')], 'commit' => false]],
    [2],
    [0, 2, null]
);
$noReusableSlot = static fn (): array => SQLiteWalAppendPlan::readerPinCurrentNext(
    $baseWal(),
    $databaseBytes,
    $databasePath,
    $transactions(),
    [2, 4],
    [0, 2, 3]
);
$noSync = static fn (): array => SQLiteWalAppendPlan::readerPinCurrentNext(
    $baseWal(),
    $databaseBytes,
    $databasePath,
    $transactions(),
    [2],
    [0, 2, null],
    false,
    false
);

$cases = [
    'status planned' => [static fn (): mixed => $plan()['status'], 'planned'],
    'reason reports next advance' => [static fn (): mixed => $plan()['reason'], 'reader_pin_current_snapshot_next_reader_advances'],
    'database path preserved' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path derived' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'base writer end frame' => [static fn (): mixed => $plan()['base_writer_end_frame'], 3],
    'current reader end frame pinned' => [static fn (): mixed => $plan()['current_reader_end_frame'], 2],
    'next reader end frame advances' => [static fn (): mixed => $plan()['next_reader_end_frame'], 5],
    'current reader slot is database reusable slot' => [static fn (): mixed => $plan()['current_reader_slot'], 0],
    'next reader slot reuses database slot' => [static fn (): mixed => $plan()['next_reader_slot'], 0],
    'next read marks updated' => [static fn (): mixed => $plan()['next_read_marks'], [5, 2, null, 99]],
    'current plan mx frame' => [static fn (): mixed => $plan()['current_read_mark_plan']['mx_frame'], 3],
    'current plan last commit' => [static fn (): mixed => $plan()['current_read_mark_plan']['last_commit_frame'], 2],
    'current plan pinned frame' => [static fn (): mixed => $plan()['current_read_mark_plan']['checkpoint_pinned_frame'], null],
    'current plan reusable slots' => [static fn (): mixed => $plan()['current_read_mark_plan']['reusable_slots'], [0, 2, 3]],
    'current invalid mark reason' => [static fn (): mixed => $plan()['current_read_mark_plan']['read_marks'][3]['reason'], 'beyond_wal_mx_frame'],
    'next plan mx frame' => [static fn (): mixed => $plan()['next_read_mark_plan']['mx_frame'], 7],
    'next plan last commit' => [static fn (): mixed => $plan()['next_read_mark_plan']['last_commit_frame'], 5],
    'next plan pinned frame' => [static fn (): mixed => $plan()['next_read_mark_plan']['checkpoint_pinned_frame'], 2],
    'next plan reset blocked' => [static fn (): mixed => $plan()['next_read_mark_plan']['reset_blocked'], true],
    'next plan new slot pins latest' => [static fn (): mixed => $plan()['next_read_mark_plan']['read_marks'][0]['reason'], 'pins_latest_commit'],
    'current database page count' => [static fn (): mixed => $plan()['current_database_page_count'], 3],
    'next database page count grows' => [static fn (): mixed => $plan()['next_database_page_count'], 4],
    'current commit frame' => [static fn (): mixed => $plan()['current_commit_frame'], 2],
    'next commit frame' => [static fn (): mixed => $plan()['next_commit_frame'], 5],
    'appended frame count' => [static fn (): mixed => $plan()['appended_frame_count'], 4],
    'committed transaction count' => [static fn (): mixed => $plan()['committed_transaction_count'], 1],
    'uncommitted transaction count' => [static fn (): mixed => $plan()['uncommitted_transaction_count'], 1],
    'current reader pins old snapshot' => [static fn (): mixed => $plan()['current_reader_pins_old_snapshot'], true],
    'next reader uses reusable slot' => [static fn (): mixed => $plan()['next_reader_uses_reusable_slot'], true],
    'uncommitted tail hidden' => [static fn (): mixed => $plan()['uncommitted_tail_visible'], false],
    'images differ' => [static fn (): mixed => $plan()['images_match'], false],
    'current sources' => [static fn (): mixed => $plan()['current_reader_sources'], ['database', 'wal', 'wal', 'error', 'error']],
    'next sources' => [static fn (): mixed => $plan()['next_reader_sources'], ['database', 'wal', 'wal', 'wal', 'error']],
    'current frame indexes' => [static fn (): mixed => $plan()['current_reader_frame_indexes'], [null, 1, 2, null, null]],
    'next frame indexes' => [static fn (): mixed => $plan()['next_reader_frame_indexes'], [null, 4, 2, 5, null]],
    'current errors count' => [static fn (): mixed => count($plan()['current_reader_errors']), 2],
    'next errors count' => [static fn (): mixed => count($plan()['next_reader_errors']), 1],
    'current page two old image' => [static fn (): mixed => str_contains($plan()['current_reader'][1]['image'], 'before pin'), true],
    'next page two writer image' => [static fn (): mixed => str_contains($plan()['next_reader'][1]['image'], 'next reader'), true],
    'next page four writer image' => [static fn (): mixed => str_contains($plan()['next_reader'][3]['image'], 'plugin option'), true],
    'next page five rejected' => [static fn (): mixed => str_contains($plan()['next_reader_errors'][0], 'beyond the committed database size'), true],
    'frames hidden from current' => [static fn (): mixed => $plan()['frames_hidden_from_current'], [3, 4, 5, 6, 7]],
    'frames visible to next' => [static fn (): mixed => $plan()['frames_visible_to_next'], [1, 2, 3, 4, 5]],
    'append last commit frame' => [static fn (): mixed => $plan()['append']['last_commit_frame'], 5],
    'append last database page count' => [static fn (): mixed => $plan()['append']['last_database_page_count'], 4],
    'append end frame includes drafts' => [static fn (): mixed => $plan()['append']['end_frame'], 7],
    'dependency includes pin current next' => [static fn (): mixed => in_array('sqlite-wal-reader-pin-current-next65', $plan()['dependencies'], true), true],
    'dependency includes read marks' => [static fn (): mixed => in_array('wal-index-read-marks', $plan()['dependencies'], true), true],
    'uncommitted-only reason' => [static fn (): mixed => $uncommittedOnly()['reason'], 'reader_pin_append_has_no_committed_next_snapshot'],
    'uncommitted-only next frame stays current' => [static fn (): mixed => $uncommittedOnly()['next_reader_end_frame'], 2],
    'uncommitted-only next mark replaces database slot' => [static fn (): mixed => $uncommittedOnly()['next_read_marks'][0], 2],
    'database slot can be reused for next reader' => [static fn (): mixed => $noReusableSlot()['next_reader_slot'], 0],
    'database slot reuse mark' => [static fn (): mixed => $noReusableSlot()['next_read_marks'], [5, 2, 3]],
    'sync disabled only writes wal' => [static fn (): mixed => array_column($noSync()['append']['operations'], 'op'), ['write']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader pin current next65 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader pin current next65 rejects empty pages'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerPinCurrentNext($baseWal(), $databaseBytes, $databasePath, $transactions(), [], [0, 2]));
};

$tests['wal reader pin current next65 rejects empty read marks'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerPinCurrentNext($baseWal(), $databaseBytes, $databasePath, $transactions(), [2], []));
};

$tests['wal reader pin current next65 rejects non integer page'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerPinCurrentNext($baseWal(), $databaseBytes, $databasePath, $transactions(), ['2'], [0, 2]));
};

$tests['wal reader pin current next65 rejects negative read mark'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerPinCurrentNext($baseWal(), $databaseBytes, $databasePath, $transactions(), [2], [0, -1]));
};

return $tests;
