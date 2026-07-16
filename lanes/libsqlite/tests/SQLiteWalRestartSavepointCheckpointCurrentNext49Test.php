<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x49494949;
$salt2 = 0x51515151;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = static fn (): string => $page('db page 1 schema base') . $page('db page 2 options base');

$walHeaderBytes = static function () use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 49, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};

$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$walBytes = static function () use ($walHeaderBytes, $appendFrame, $page): string {
    $bytes = $walHeaderBytes();
    $seed = SQLiteWal::checksumPair(substr($bytes, 0, 24), false);
    $bytes = $appendFrame($bytes, $seed, 1, 0, $page('wal page 1 schema retained before savepoint'));
    $bytes = $appendFrame($bytes, $seed, 2, 2, $page('wal page 2 options retained before savepoint'));
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('wal page 2 plugin draft discarded by rollback'));
    $bytes = $appendFrame($bytes, $seed, 3, 3, $page('wal page 3 transient draft discarded by rollback'));

    return $bytes;
};

$wal = static fn (): SQLiteWal => SQLiteWal::parse($walBytes(), null, true);
$savepoints = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp_import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin_batch');
    $stack->recordWalFrameWrite(3, 2);
    $stack->recordWalFrameWrite(4, 3, true);

    return $stack;
};
$transactions = static fn (): array => [[
    'pages' => [
        2 => $page('next writer page 2 active_plugins committed after restart'),
        3 => $page('next writer page 3 autoload index committed after restart'),
    ],
    'database_page_count' => 3,
    'commit' => true,
], [
    'pages' => [
        3 => $page('next writer page 3 uncommitted draft after restart'),
    ],
    'commit' => false,
]];

$restartPlan = static fn (): array => SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext(
    $savepoints(),
    'plugin_batch',
    $wal(),
    $walBytes(),
    $databaseBytes(),
    $databasePath,
    $transactions(),
    [1, 2, 3],
    'restart'
);
$truncatePlan = static fn (): array => SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext(
    $savepoints(),
    'plugin_batch',
    $wal(),
    $walBytes(),
    $databaseBytes(),
    $databasePath,
    $transactions(),
    [1, 2, 3],
    'truncate'
);
$busyPlan = static fn (): array => SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext(
    $savepoints(),
    'plugin_batch',
    $wal(),
    $walBytes(),
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
    'restart status planned' => [static fn (): mixed => $restartPlan()['status'], 'planned'],
    'restart reason' => [static fn (): mixed => $restartPlan()['reason'], 'savepoint_rollback_restart_checkpoint_then_append_current_next_visibility'],
    'savepoint named' => [static fn (): mixed => $restartPlan()['savepoint'], 'plugin_batch'],
    'mode restart preserved' => [static fn (): mixed => $restartPlan()['mode'], 'restart'],
    'database path preserved' => [static fn (): mixed => $restartPlan()['database_path'], $databasePath],
    'wal path derived' => [static fn (): mixed => $restartPlan()['wal_path'], $databasePath . '-wal'],
    'rollback retained two frames' => [static fn (): mixed => $restartPlan()['retained_frame_count'], 2],
    'rollback discarded two frames' => [static fn (): mixed => $restartPlan()['discarded_frame_count'], 2],
    'rollback truncate byte boundary' => [static fn (): mixed => $restartPlan()['rollback']['truncate_to_bytes'], 32 + (2 * (24 + $pageSize))],
    'rollback needs truncation' => [static fn (): mixed => $restartPlan()['rollback']['needs_truncate'], true],
    'rollback first discarded page' => [static fn (): mixed => $restartPlan()['rollback']['discarded_wal_frames'][0]['page_number'], 2],
    'rollback second discarded page' => [static fn (): mixed => $restartPlan()['rollback']['discarded_wal_frames'][1]['page_number'], 3],
    'current uses rollback prefix' => [static fn (): mixed => $restartPlan()['current_uses_rollback_wal_prefix'], true],
    'checkpoint action restart' => [static fn (): mixed => $restartPlan()['checkpoint']['wal_action'], 'restart_wal'],
    'checkpoint not busy' => [static fn (): mixed => $restartPlan()['checkpoint']['busy'], false],
    'checkpoint page count two before append' => [static fn (): mixed => $restartPlan()['checkpoint']['database_page_count'], 2],
    'checkpoint includes retained page one' => [static fn (): mixed => str_contains($restartPlan()['checkpoint']['database_bytes'], 'schema retained before savepoint'), true],
    'checkpoint includes retained page two' => [static fn (): mixed => str_contains($restartPlan()['checkpoint']['database_bytes'], 'options retained before savepoint'), true],
    'checkpoint excludes discarded plugin draft' => [static fn (): mixed => str_contains($restartPlan()['checkpoint']['database_bytes'], 'plugin draft discarded'), false],
    'checkpoint restart wal header only' => [static fn (): mixed => $restartPlan()['checkpoint']['wal_bytes_length'], 32],
    'append starts after restart header' => [static fn (): mixed => $restartPlan()['append']['start_offset'], 32],
    'append writes three frames' => [static fn (): mixed => $restartPlan()['append']['appended_frame_count'], 3],
    'append bytes length' => [static fn (): mixed => $restartPlan()['append']['append_bytes_length'], 3 * (24 + $pageSize)],
    'append committed transactions' => [static fn (): mixed => $restartPlan()['append']['committed_transaction_count'], 1],
    'append uncommitted transactions' => [static fn (): mixed => $restartPlan()['append']['uncommitted_transaction_count'], 1],
    'append last commit frame' => [static fn (): mixed => $restartPlan()['append']['last_commit_frame'], 2],
    'append last database page count' => [static fn (): mixed => $restartPlan()['append']['last_database_page_count'], 3],
    'append first frame page two' => [static fn (): mixed => $restartPlan()['append']['frames'][0]['page_number'], 2],
    'append second frame commits page count' => [static fn (): mixed => $restartPlan()['append']['frames'][1]['commit'], 3],
    'append third frame uncommitted' => [static fn (): mixed => $restartPlan()['append']['frames'][2]['committed'], false],
    'current reader end frame retained' => [static fn (): mixed => $restartPlan()['current_reader_end_frame'], 2],
    'next reader end frame last commit' => [static fn (): mixed => $restartPlan()['next_reader_end_frame'], 2],
    'current reader sources' => [static fn (): mixed => $restartPlan()['current_reader_sources'], ['wal', 'wal', 'missing']],
    'next reader sources' => [static fn (): mixed => $restartPlan()['next_reader_sources'], ['database', 'wal', 'wal']],
    'current reader frame indexes' => [static fn (): mixed => $restartPlan()['current_reader_frame_indexes'], [1, 2, null]],
    'next reader frame indexes' => [static fn (): mixed => $restartPlan()['next_reader_frame_indexes'], [null, 1, 2]],
    'current missing page three recorded' => [static fn (): mixed => count($restartPlan()['current_reader_errors']), 1],
    'next no reader errors' => [static fn (): mixed => $restartPlan()['next_reader_errors'], []],
    'next uses checkpoint database' => [static fn (): mixed => $restartPlan()['next_uses_checkpoint_database'], true],
    'next uses appended wal' => [static fn (): mixed => $restartPlan()['next_uses_appended_wal'], true],
    'images no longer match' => [static fn (): mixed => $restartPlan()['images_match'], false],
    'current page two retained image' => [static fn (): mixed => str_contains($restartPlan()['current_reader'][1]['image'], 'options retained before savepoint'), true],
    'current page two excludes draft' => [static fn (): mixed => str_contains($restartPlan()['current_reader'][1]['image'], 'plugin draft discarded'), false],
    'next page two appended image' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][1]['image'], 'active_plugins committed'), true],
    'next page three committed append image' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][2]['image'], 'autoload index committed'), true],
    'restart parsed next frame count' => [static fn (): mixed => $restartNextWal()->frameCount(), 3],
    'restart parsed next last commit' => [static fn (): mixed => $restartNextWal()->lastCommitFrame()?->index, 2],
    'restart parsed next uncommitted count' => [static fn (): mixed => $restartNextWal()->uncommittedFrameCount(), 1],
    'restart parsed checkpoint excludes uncommitted append' => [static fn (): mixed => str_contains($restartNextWal()->checkpointDatabaseImage($restartPlan()['checkpoint']['database_bytes']), 'uncommitted draft'), false],
    'restart operations include wal write' => [static fn (): mixed => $restartPlan()['operations'][0]['op'], 'write'],
    'restart operations include wal sync' => [static fn (): mixed => $restartPlan()['operations'][1]['op'], 'sync'],
    'restart operations include directory sync' => [static fn (): mixed => $restartPlan()['operations'][2]['op'], 'sync_directory'],
    'restart dependency savepoint checkpoint' => [static fn (): mixed => in_array('sqlite-wal-savepoint-restart-checkpoint-current-next', $restartPlan()['dependencies'], true), true],
    'restart dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $restartPlan()['dependencies'], true), true],
    'restart dependency append' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $restartPlan()['dependencies'], true), true],
    'truncate action' => [static fn (): mixed => $truncatePlan()['checkpoint']['wal_action'], 'truncate_wal'],
    'truncate checkpoint wal empty' => [static fn (): mixed => $truncatePlan()['checkpoint']['wal_bytes_length'], 0],
    'truncate append generated fresh header' => [static fn (): mixed => $truncatePlan()['append']['start_offset'], 32],
    'truncate first appended frame index one' => [static fn (): mixed => $truncatePlan()['append']['frames'][0]['frame_index'], 1],
    'truncate next reader sources' => [static fn (): mixed => $truncatePlan()['next_reader_sources'], ['database', 'wal', 'wal']],
    'truncate parsed frame count' => [static fn (): mixed => $truncateNextWal()->frameCount(), 3],
    'truncate parsed last commit db count' => [static fn (): mixed => $truncateNextWal()->lastCommitFrame()?->databasePageCountAfterCommit, 3],
    'busy status' => [static fn (): mixed => $busyPlan()['status'], 'busy'],
    'busy reason' => [static fn (): mixed => $busyPlan()['reason'], 'reader_blocks_checkpoint_completion'],
    'busy append skipped' => [static fn (): mixed => $busyPlan()['append'], []],
    'busy retained frame count' => [static fn (): mixed => $busyPlan()['retained_frame_count'], 2],
    'busy next uses no appended wal' => [static fn (): mixed => $busyPlan()['next_uses_appended_wal'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal restart savepoint checkpoint current next49 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal restart savepoint checkpoint current next49 rejects empty savepoint name'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext($savepoints(), '', $wal(), $walBytes(), $databaseBytes(), $databasePath, $transactions(), [1]));
};

$tests['wal restart savepoint checkpoint current next49 rejects empty pages'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext($savepoints(), 'plugin_batch', $wal(), $walBytes(), $databaseBytes(), $databasePath, $transactions(), []));
};

$tests['wal restart savepoint checkpoint current next49 rejects passive mode'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext($savepoints(), 'plugin_batch', $wal(), $walBytes(), $databaseBytes(), $databasePath, $transactions(), [1], 'passive'));
};

$tests['wal restart savepoint checkpoint current next49 rejects non integer page'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext($savepoints(), 'plugin_batch', $wal(), $walBytes(), $databaseBytes(), $databasePath, $transactions(), ['1']));
};

return $tests;
