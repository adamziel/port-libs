<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x25252525;
$salt2 = 0x67676767;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = static fn (): string => $page('database page 1 original schema') . $page('database page 2 original options');

$walHeaderBytes = static function (int $checkpoint = 25) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
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
    $bytes = $appendFrame($bytes, $seed, 1, 0, $page('wal page 1 schema before checkpoint'));
    $bytes = $appendFrame($bytes, $seed, 2, 2, $page('wal page 2 options before checkpoint'));

    return $bytes;
};

$baseWal = static fn (): SQLiteWal => SQLiteWal::parse($baseWalBytes(), null, true);
$transactions = static fn (): array => [[
    'pages' => [
        2 => $page('next writer page 2 updated active_plugins'),
        3 => $page('next writer page 3 new autoload index'),
    ],
    'database_page_count' => 3,
    'commit' => true,
], [
    'pages' => [
        3 => $page('next writer page 3 draft not committed'),
    ],
    'commit' => false,
]];

$restartPlan = static fn (): array => SQLiteWalAppendPlan::checkpointAppendCurrentNext(
    $baseWal(),
    $databaseBytes(),
    $databasePath,
    $transactions(),
    [1, 2, 3],
    'restart'
);
$truncatePlan = static fn (): array => SQLiteWalAppendPlan::checkpointAppendCurrentNext(
    $baseWal(),
    $databaseBytes(),
    $databasePath,
    $transactions(),
    [1, 2, 3],
    'truncate'
);
$pinnedPlan = static fn (): array => SQLiteWalAppendPlan::checkpointAppendCurrentNext(
    $baseWal(),
    $databaseBytes(),
    $databasePath,
    $transactions(),
    [1, 2],
    'restart',
    1
);
$restartNextWal = static fn (): SQLiteWal => SQLiteWal::parse($restartPlan()['append']['wal_bytes'], null, true);
$truncateNextWal = static fn (): SQLiteWal => SQLiteWal::parse($truncatePlan()['append']['wal_bytes'], null, true);

$cases = [
    'restart status' => [static fn (): mixed => $restartPlan()['status'], 'planned'],
    'restart reason' => [static fn (): mixed => $restartPlan()['reason'], 'checkpoint_then_append_current_next_visibility'],
    'restart mode preserved' => [static fn (): mixed => $restartPlan()['mode'], 'restart'],
    'database path preserved' => [static fn (): mixed => $restartPlan()['database_path'], $databasePath],
    'wal path derived' => [static fn (): mixed => $restartPlan()['wal_path'], $databasePath . '-wal'],
    'checkpoint restart action' => [static fn (): mixed => $restartPlan()['checkpoint']['wal_action'], 'restart_wal'],
    'checkpoint restart not busy' => [static fn (): mixed => $restartPlan()['checkpoint']['busy'], false],
    'checkpoint database includes old wal page one' => [static fn (): mixed => str_contains($restartPlan()['checkpoint']['database_bytes'], 'schema before checkpoint'), true],
    'checkpoint database includes old wal page two' => [static fn (): mixed => str_contains($restartPlan()['checkpoint']['database_bytes'], 'options before checkpoint'), true],
    'checkpoint database page count before append' => [static fn (): mixed => $restartPlan()['checkpoint']['database_page_count'], 2],
    'checkpoint wal bytes restart header only' => [static fn (): mixed => $restartPlan()['checkpoint']['wal_bytes_length'], 32],
    'append starts after restarted header' => [static fn (): mixed => $restartPlan()['append']['start_offset'], 32],
    'append bytes three frames' => [static fn (): mixed => $restartPlan()['append']['append_bytes_length'], 3 * (24 + $pageSize)],
    'restart full wal contains header plus frames' => [static fn (): mixed => $restartPlan()['append']['wal_bytes_length'], 32 + (3 * (24 + $pageSize))],
    'append start frame one after checkpoint reset' => [static fn (): mixed => $restartPlan()['append']['start_frame'], 1],
    'append end frame three' => [static fn (): mixed => $restartPlan()['append']['end_frame'], 3],
    'append committed count' => [static fn (): mixed => $restartPlan()['append']['committed_transaction_count'], 1],
    'append uncommitted count' => [static fn (): mixed => $restartPlan()['append']['uncommitted_transaction_count'], 1],
    'append last commit frame two' => [static fn (): mixed => $restartPlan()['append']['last_commit_frame'], 2],
    'append last database page count three' => [static fn (): mixed => $restartPlan()['append']['last_database_page_count'], 3],
    'first appended frame page two' => [static fn (): mixed => $restartPlan()['append']['frames'][0]['page_number'], 2],
    'first appended frame no commit marker' => [static fn (): mixed => $restartPlan()['append']['frames'][0]['commit'], 0],
    'second appended frame page three' => [static fn (): mixed => $restartPlan()['append']['frames'][1]['page_number'], 3],
    'second appended frame commits db size' => [static fn (): mixed => $restartPlan()['append']['frames'][1]['commit'], 3],
    'third appended frame uncommitted' => [static fn (): mixed => $restartPlan()['append']['frames'][2]['committed'], false],
    'restart next reader end frame' => [static fn (): mixed => $restartPlan()['next_reader_end_frame'], 3],
    'restart current reader end frame remains old wal' => [static fn (): mixed => $restartPlan()['current_reader_end_frame'], 2],
    'restart current reader sources' => [static fn (): mixed => $restartPlan()['current_reader_sources'], ['wal', 'wal', 'missing']],
    'restart next reader sources' => [static fn (): mixed => $restartPlan()['next_reader_sources'], ['database', 'wal', 'wal']],
    'restart current frame indexes' => [static fn (): mixed => $restartPlan()['current_reader_frame_indexes'], [1, 2, null]],
    'restart next frame indexes' => [static fn (): mixed => $restartPlan()['next_reader_frame_indexes'], [null, 1, 2]],
    'restart current missing page three recorded' => [static fn (): mixed => count($restartPlan()['current_reader_errors']), 1],
    'restart next has no errors' => [static fn (): mixed => $restartPlan()['next_reader_errors'], []],
    'restart current stable flag' => [static fn (): mixed => $restartPlan()['current_stable_after_checkpoint'], true],
    'restart next uses checkpoint database' => [static fn (): mixed => $restartPlan()['next_uses_checkpoint_database'], true],
    'restart next uses appended wal' => [static fn (): mixed => $restartPlan()['next_uses_appended_wal'], true],
    'restart dependencies include checkpoint append' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-append-current-next', $restartPlan()['dependencies'], true), true],
    'restart dependencies include checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $restartPlan()['dependencies'], true), true],
    'restart dependencies include append transaction' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $restartPlan()['dependencies'], true), true],
    'restart parsed next wal frame count' => [static fn (): mixed => $restartNextWal()->frameCount(), 3],
    'restart parsed next wal last commit' => [static fn (): mixed => $restartNextWal()->lastCommitFrame()?->index, 2],
    'restart parsed next wal uncommitted tail' => [static fn (): mixed => $restartNextWal()->uncommittedFrameCount(), 1],
    'restart parsed next wal checkpoint image includes committed append' => [static fn (): mixed => str_contains($restartNextWal()->checkpointDatabaseImage($restartPlan()['checkpoint']['database_bytes']), 'updated active_plugins'), true],
    'restart parsed next wal checkpoint excludes draft' => [static fn (): mixed => str_contains($restartNextWal()->checkpointDatabaseImage($restartPlan()['checkpoint']['database_bytes']), 'draft not committed'), false],
    'restart current page two remains old image' => [static fn (): mixed => str_contains($restartPlan()['current_reader'][1]['image'], 'options before checkpoint'), true],
    'restart next page two sees appended image' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][1]['image'], 'updated active_plugins'), true],
    'restart next page three sees committed append' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][2]['image'], 'new autoload index'), true],
    'truncate checkpoint action' => [static fn (): mixed => $truncatePlan()['checkpoint']['wal_action'], 'truncate_wal'],
    'truncate checkpoint wal is empty before append' => [static fn (): mixed => $truncatePlan()['checkpoint']['wal_bytes_length'], 0],
    'truncate append starts at generated fresh header' => [static fn (): mixed => $truncatePlan()['append']['start_offset'], 32],
    'truncate append first frame index one' => [static fn (): mixed => $truncatePlan()['append']['frames'][0]['frame_index'], 1],
    'truncate parsed next wal frame count' => [static fn (): mixed => $truncateNextWal()->frameCount(), 3],
    'truncate parsed next wal validates commit' => [static fn (): mixed => $truncateNextWal()->lastCommitFrame()?->databasePageCountAfterCommit, 3],
    'truncate next sources use database and appended wal' => [static fn (): mixed => $truncatePlan()['next_reader_sources'], ['database', 'wal', 'wal']],
    'truncate current source page one still old wal' => [static fn (): mixed => $truncatePlan()['current_reader_sources'][0], 'wal'],
    'pinned restart reports busy' => [static fn (): mixed => $pinnedPlan()['status'], 'busy'],
    'pinned restart reason' => [static fn (): mixed => $pinnedPlan()['reason'], 'reader_blocks_checkpoint_completion'],
    'pinned restart append skipped' => [static fn (): mixed => $pinnedPlan()['append'], []],
    'pinned restart next not using appended wal' => [static fn (): mixed => $pinnedPlan()['next_uses_appended_wal'], false],
    'pinned restart dependencies include current next' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-append-current-next', $pinnedPlan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint append current next25 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint append current next25 rejects empty page list'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointAppendCurrentNext($baseWal(), $databaseBytes(), $databasePath, $transactions(), []));
};

$tests['wal checkpoint append current next25 rejects passive mode'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointAppendCurrentNext($baseWal(), $databaseBytes(), $databasePath, $transactions(), [1], 'passive'));
};

$tests['wal checkpoint append current next25 rejects non integer visibility page'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointAppendCurrentNext($baseWal(), $databaseBytes(), $databasePath, $transactions(), ['2']));
};

$tests['wal checkpoint append current next25 rejects invalid append transaction'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointAppendCurrentNext($baseWal(), $databaseBytes(), $databasePath, [['pages' => []]], [1]));
};

return $tests;
