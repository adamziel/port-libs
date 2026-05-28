<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$salt1 = 0x56565656;
$salt2 = 0x78787878;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('database schema page') . $page('database options page') . $page('database autoload index');

$walHeaderBytes = static function () use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 56, $salt1, $salt2);
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
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('reader transaction option before import'));
    $bytes = $appendFrame($bytes, $seed, 3, 3, $page('reader transaction autoload before import'));
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('writer transaction option before plugin'));
    $bytes = $appendFrame($bytes, $seed, 4, 4, $page('writer transaction plugin before import'));

    return $bytes;
};

$baseWal = static fn (): SQLiteWal => SQLiteWal::parse($baseWalBytes(), null, true);
$transactions = static fn (): array => [
    [
        'pages' => [
            2 => $page('next import active_plugins committed'),
            5 => $page('next import transient cache committed'),
        ],
        'database_page_count' => 5,
        'commit' => true,
    ],
    [
        'pages' => [
            6 => $page('draft plugin settings uncommitted'),
        ],
        'commit' => false,
    ],
];

$plan = static fn (?int $readerFrame = 2, array $pages = [1, 2, 3, 4, 5, 6]): array => SQLiteWalAppendPlan::mvccReaderCurrentNext(
    $baseWal(),
    $databaseBytes,
    $databasePath,
    $transactions(),
    $pages,
    $readerFrame,
);

$cases = [
    'planned status' => [static fn (): mixed => $plan()['status'], 'planned'],
    'planned reason' => [static fn (): mixed => $plan()['reason'], 'mvcc_current_reader_pinned_next_reader_advances'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'base writer frame' => [static fn (): mixed => $plan()['base_writer_end_frame'], 4],
    'current reader frame pinned' => [static fn (): mixed => $plan()['current_reader_end_frame'], 2],
    'next reader frame advances' => [static fn (): mixed => $plan()['next_reader_end_frame'], 6],
    'current commit frame' => [static fn (): mixed => $plan()['current_commit_frame'], 2],
    'next commit frame' => [static fn (): mixed => $plan()['next_commit_frame'], 6],
    'current page count' => [static fn (): mixed => $plan()['current_database_page_count'], 3],
    'next page count grows' => [static fn (): mixed => $plan()['next_database_page_count'], 5],
    'appended frame count' => [static fn (): mixed => $plan()['appended_frame_count'], 3],
    'committed transaction count' => [static fn (): mixed => $plan()['committed_transaction_count'], 1],
    'uncommitted transaction count' => [static fn (): mixed => $plan()['uncommitted_transaction_count'], 1],
    'images differ' => [static fn (): mixed => $plan()['images_match'], false],
    'uncommitted tail hidden' => [static fn (): mixed => $plan()['uncommitted_tail_visible'], false],
    'current sources preserve old snapshot' => [static fn (): mixed => $plan()['current_reader_sources'], ['database', 'wal', 'wal', 'error', 'error', 'error']],
    'next sources include committed growth' => [static fn (): mixed => $plan()['next_reader_sources'], ['database', 'wal', 'wal', 'wal', 'wal', 'error']],
    'current frame indexes' => [static fn (): mixed => $plan()['current_reader_frame_indexes'], [null, 1, 2, null, null, null]],
    'next frame indexes' => [static fn (): mixed => $plan()['next_reader_frame_indexes'], [null, 5, 2, 4, 6, null]],
    'current page one uses database' => [static fn (): mixed => str_contains($plan()['current_reader'][0]['image'], 'database schema page'), true],
    'next page one uses database' => [static fn (): mixed => str_contains($plan()['next_reader'][0]['image'], 'database schema page'), true],
    'current page two old option' => [static fn (): mixed => str_contains($plan()['current_reader'][1]['image'], 'option before import'), true],
    'current page two excludes next import' => [static fn (): mixed => str_contains($plan()['current_reader'][1]['image'], 'active_plugins committed'), false],
    'next page two sees committed import' => [static fn (): mixed => str_contains($plan()['next_reader'][1]['image'], 'active_plugins committed'), true],
    'next page two hides older writer frame' => [static fn (): mixed => str_contains($plan()['next_reader'][1]['image'], 'writer transaction option'), false],
    'current page three old autoload' => [static fn (): mixed => str_contains($plan()['current_reader'][2]['image'], 'autoload before import'), true],
    'next page three keeps old autoload' => [static fn (): mixed => str_contains($plan()['next_reader'][2]['image'], 'autoload before import'), true],
    'current page four is beyond pinned page count' => [static fn (): mixed => $plan()['current_reader'][3]['source'], 'error'],
    'current page four error text' => [static fn (): mixed => $plan()['current_reader'][3]['error'], 'SQLite WAL reader page 4 is beyond the committed database size'],
    'next page four sees preexisting writer commit' => [static fn (): mixed => str_contains($plan()['next_reader'][3]['image'], 'plugin before import'), true],
    'current page five is hidden growth' => [static fn (): mixed => $plan()['current_reader'][4]['source'], 'error'],
    'next page five sees committed growth' => [static fn (): mixed => str_contains($plan()['next_reader'][4]['image'], 'transient cache committed'), true],
    'current page six is hidden draft' => [static fn (): mixed => $plan()['current_reader'][5]['source'], 'error'],
    'next page six stays hidden draft' => [static fn (): mixed => $plan()['next_reader'][5]['source'], 'error'],
    'next page six error text' => [static fn (): mixed => $plan()['next_reader'][5]['error'], 'SQLite WAL reader page 6 is beyond the committed database size'],
    'current errors count' => [static fn (): mixed => count($plan()['current_reader_errors']), 3],
    'next errors count' => [static fn (): mixed => count($plan()['next_reader_errors']), 1],
    'frames hidden from current' => [static fn (): mixed => $plan()['frames_hidden_from_current'], [3, 4, 5, 6, 7]],
    'frames visible to next' => [static fn (): mixed => $plan()['frames_visible_to_next'], [1, 2, 3, 4, 5, 6]],
    'append starts after base wal' => [static fn (): mixed => $plan()['append']['start_frame'], 5],
    'append ends after draft' => [static fn (): mixed => $plan()['append']['end_frame'], 7],
    'append last commit frame' => [static fn (): mixed => $plan()['append']['last_commit_frame'], 6],
    'append last page count' => [static fn (): mixed => $plan()['append']['last_database_page_count'], 5],
    'append has draft frame' => [static fn (): mixed => $plan()['append']['frames'][2]['committed'], false],
    'append first op writes wal' => [static fn (): mixed => $plan()['append']['operations'][0]['op'], 'write'],
    'append write offset' => [static fn (): mixed => $plan()['append']['operations'][0]['offset'], strlen($baseWalBytes())],
    'append write bytes' => [static fn (): mixed => $plan()['append']['operations'][0]['bytes'], 3 * (24 + $pageSize)],
    'append syncs wal' => [static fn (): mixed => $plan()['append']['operations'][1]['op'], 'sync'],
    'append syncs directory' => [static fn (): mixed => $plan()['append']['operations'][2]['op'], 'sync_directory'],
    'dependency includes mvcc boundary' => [static fn (): mixed => in_array('sqlite-pager-mvcc-reader-current-next', $plan()['dependencies'], true), true],
    'dependency includes wal append' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $plan()['dependencies'], true), true],
    'single page returns one current row' => [static fn (): mixed => count($plan(2, [2])['current_reader']), 1],
    'single page returns one next row' => [static fn (): mixed => count($plan(2, [2])['next_reader']), 1],
    'latest current reader sees base writer frame' => [static fn (): mixed => $plan(null, [2])['current_reader_frame_indexes'], [3]],
    'latest current reader still precedes appended commit' => [static fn (): mixed => $plan(null, [2])['next_reader_frame_indexes'], [5]],
    'database-only current reader uses base pages' => [static fn (): mixed => $plan(0, [2])['current_reader_sources'], ['database']],
    'database-only current page count' => [static fn (): mixed => $plan(0, [2])['current_database_page_count'], 3],
    'no committed append reason' => [static fn (): mixed => SQLiteWalAppendPlan::mvccReaderCurrentNext($baseWal(), $databaseBytes, $databasePath, [['pages' => [2 => $page('draft only')], 'commit' => false]], [2], 2)['reason'], 'mvcc_append_has_no_committed_next_snapshot'],
    'no committed append next frame remains base writer' => [static fn (): mixed => SQLiteWalAppendPlan::mvccReaderCurrentNext($baseWal(), $databaseBytes, $databasePath, [['pages' => [2 => $page('draft only')], 'commit' => false]], [2], 2)['next_reader_end_frame'], 4],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager mvcc reader current next56 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager mvcc reader current next56 can omit sync operations'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $plan = SQLiteWalAppendPlan::mvccReaderCurrentNext($baseWal(), $databaseBytes, $databasePath, $transactions(), [2], 2, false, false);
    $t->same(['write'], array_column($plan['append']['operations'], 'op'));
};

$tests['pager mvcc reader current next56 rejects empty page list'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::mvccReaderCurrentNext($baseWal(), $databaseBytes, $databasePath, $transactions(), [], 2));
};

$tests['pager mvcc reader current next56 rejects non integer page'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::mvccReaderCurrentNext($baseWal(), $databaseBytes, $databasePath, $transactions(), ['2'], 2));
};

$tests['pager mvcc reader current next56 rejects negative reader frame'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::mvccReaderCurrentNext($baseWal(), $databaseBytes, $databasePath, $transactions(), [2], -1));
};

$tests['pager mvcc reader current next56 rejects future reader frame'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::mvccReaderCurrentNext($baseWal(), $databaseBytes, $databasePath, $transactions(), [2], 99));
};

return $tests;
