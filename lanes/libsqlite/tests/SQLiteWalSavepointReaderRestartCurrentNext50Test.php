<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x50505050;
$salt2 = 0x90909090;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('base schema page for wp_options')
    . $page('base active_plugins option row')
    . $page('base autoload option index')
    . $page('base plugin settings page');

$makeWalBytes = static function () use ($pageSize, $salt1, $salt2, $page): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 50, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    $appendFrame = static function (int $pageNumber, int $commitPageCount, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $appendFrame(2, 0, $page('frame 1 retained active_plugins import'));
    $appendFrame(3, 0, $page('frame 2 retained autoload index draft'));
    $appendFrame(3, 4, $page('frame 3 retained autoload index commit'));
    $appendFrame(2, 0, $page('frame 4 rolled back plugin draft'));
    $appendFrame(4, 4, $page('frame 5 rolled back plugin commit'));

    return $bytes;
};

$walBytes = $makeWalBytes();
$wal = SQLiteWal::parse($walBytes, null, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordWalFrameWrite(1, 2);
    $stack->recordWalFrameWrite(2, 3);
    $stack->recordWalFrameWrite(3, 3, true);
    $stack->savepoint('plugin-settings');
    $stack->recordWalFrameWrite(4, 2);
    $stack->recordWalFrameWrite(5, 4, true);

    return $stack;
};

$restartPlan = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerRestartCurrentNextAfterRollbackTo(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4],
    'restart'
);

$truncatePlan = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerRestartCurrentNextAfterRollbackTo(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [2, 3, 4],
    'truncate'
);

$cases = [
    'restart status ready' => [static fn (): mixed => $restartPlan()['status'], 'ready'],
    'restart mode preserved' => [static fn (): mixed => $restartPlan()['mode'], 'restart'],
    'restart savepoint name preserved' => [static fn (): mixed => $restartPlan()['savepoint'], 'plugin-settings'],
    'restart retained frame count' => [static fn (): mixed => $restartPlan()['retained_frame_count'], 3],
    'restart discarded frame count' => [static fn (): mixed => $restartPlan()['discarded_frame_count'], 2],
    'restart current reader end frame is retained prefix' => [static fn (): mixed => $restartPlan()['current_reader_end_frame'], 3],
    'restart next reader end frame is restarted header only' => [static fn (): mixed => $restartPlan()['next_reader_end_frame'], 0],
    'restart checkpoint not busy' => [static fn (): mixed => $restartPlan()['checkpoint_busy'], false],
    'restart checkpoint reason' => [static fn (): mixed => $restartPlan()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'restart wal action' => [static fn (): mixed => $restartPlan()['wal_action'], 'restart_wal'],
    'restart truncated wal bytes length' => [static fn (): mixed => $restartPlan()['truncated_wal_bytes'], 32 + (3 * (24 + $pageSize))],
    'restart restarted wal bytes length' => [static fn (): mixed => $restartPlan()['restarted_wal_bytes'], 32],
    'restart checkpoint sequence increments' => [static fn (): mixed => $restartPlan()['restarted_checkpoint_sequence'], 51],
    'restart salt one increments' => [static fn (): mixed => $restartPlan()['restarted_salt1'], ($salt1 + 1) & 0xffffffff],
    'restart salt two preserved' => [static fn (): mixed => $restartPlan()['restarted_salt2'], $salt2],
    'restart current reader sources' => [static fn (): mixed => $restartPlan()['current_reader_sources'], ['database', 'wal', 'wal', 'database']],
    'restart next reader sources' => [static fn (): mixed => $restartPlan()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'restart current reader frame indexes' => [static fn (): mixed => $restartPlan()['current_reader_frame_indexes'], [null, 1, 3, null]],
    'restart next reader frame indexes' => [static fn (): mixed => $restartPlan()['next_reader_frame_indexes'], [null, null, null, null]],
    'restart current page one stays database' => [static fn (): mixed => str_contains($restartPlan()['current_reader'][0]['image'], 'base schema page'), true],
    'restart current page two sees retained wal' => [static fn (): mixed => str_contains($restartPlan()['current_reader'][1]['image'], 'retained active_plugins'), true],
    'restart current page three sees committed retained wal' => [static fn (): mixed => str_contains($restartPlan()['current_reader'][2]['image'], 'retained autoload index commit'), true],
    'restart current page four sees base page' => [static fn (): mixed => str_contains($restartPlan()['current_reader'][3]['image'], 'base plugin settings'), true],
    'restart next page one stays database' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][0]['image'], 'base schema page'), true],
    'restart next page two comes from checkpoint database' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][1]['image'], 'retained active_plugins'), true],
    'restart next page three comes from checkpoint database' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][2]['image'], 'retained autoload index commit'), true],
    'restart next page four keeps base page' => [static fn (): mixed => str_contains($restartPlan()['next_reader'][3]['image'], 'base plugin settings'), true],
    'restart rolled back page two draft omitted' => [static fn (): mixed => str_contains(implode('', $restartPlan()['next_reader_images']), 'rolled back plugin draft'), false],
    'restart rolled back page four commit omitted' => [static fn (): mixed => str_contains(implode('', $restartPlan()['next_reader_images']), 'rolled back plugin commit'), false],
    'restart current kept retained wal' => [static fn (): mixed => $restartPlan()['current_reader_kept_retained_wal'], true],
    'restart next uses checkpoint database' => [static fn (): mixed => $restartPlan()['next_reader_uses_checkpoint_database'], true],
    'restart next uses restarted header' => [static fn (): mixed => $restartPlan()['next_reader_uses_restarted_header'], true],
    'restart images match after checkpoint restart' => [static fn (): mixed => $restartPlan()['images_match'], true],
    'restart dependency includes savepoint current prefix' => [static fn (): mixed => in_array('sqlite-savepoint-wal-current-prefix', $restartPlan()['dependencies'], true), true],
    'restart dependency includes checkpoint current' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $restartPlan()['dependencies'], true), true],
    'restart dependency includes reader restart marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-reader-restart-current-next', $restartPlan()['dependencies'], true), true],
    'truncate status ready' => [static fn (): mixed => $truncatePlan()['status'], 'ready'],
    'truncate mode preserved' => [static fn (): mixed => $truncatePlan()['mode'], 'truncate'],
    'truncate checkpoint reason' => [static fn (): mixed => $truncatePlan()['checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'truncate wal action' => [static fn (): mixed => $truncatePlan()['wal_action'], 'truncate_wal'],
    'truncate next reader end frame' => [static fn (): mixed => $truncatePlan()['next_reader_end_frame'], 0],
    'truncate restarted wal bytes empty' => [static fn (): mixed => $truncatePlan()['restarted_wal_bytes'], 0],
    'truncate no restarted checkpoint sequence' => [static fn (): mixed => $truncatePlan()['restarted_checkpoint_sequence'], null],
    'truncate no restarted salt one' => [static fn (): mixed => $truncatePlan()['restarted_salt1'], null],
    'truncate current sources' => [static fn (): mixed => $truncatePlan()['current_reader_sources'], ['wal', 'wal', 'database']],
    'truncate next sources' => [static fn (): mixed => $truncatePlan()['next_reader_sources'], ['database', 'database', 'database']],
    'truncate current frame indexes' => [static fn (): mixed => $truncatePlan()['current_reader_frame_indexes'], [1, 3, null]],
    'truncate next frame indexes' => [static fn (): mixed => $truncatePlan()['next_reader_frame_indexes'], [null, null, null]],
    'truncate current page two retained' => [static fn (): mixed => str_contains($truncatePlan()['current_reader'][0]['image'], 'retained active_plugins'), true],
    'truncate next page two checkpointed' => [static fn (): mixed => str_contains($truncatePlan()['next_reader'][0]['image'], 'retained active_plugins'), true],
    'truncate page four base survives' => [static fn (): mixed => str_contains($truncatePlan()['next_reader'][2]['image'], 'base plugin settings'), true],
    'truncate current kept retained wal' => [static fn (): mixed => $truncatePlan()['current_reader_kept_retained_wal'], true],
    'truncate next uses checkpoint database' => [static fn (): mixed => $truncatePlan()['next_reader_uses_checkpoint_database'], true],
    'truncate next does not use restarted header' => [static fn (): mixed => $truncatePlan()['next_reader_uses_restarted_header'], false],
    'truncate images match after checkpoint truncate' => [static fn (): mixed => $truncatePlan()['images_match'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal savepoint reader restart current next50 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal savepoint reader restart current next50 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerRestartCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, []));
};

$tests['wal savepoint reader restart current next50 rejects passive mode'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerRestartCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, [2], 'passive'));
};

$tests['wal savepoint reader restart current next50 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerRestartCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, ['2']));
};

$tests['wal savepoint reader restart current next50 rejects missing savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerRestartCurrentNextAfterRollbackTo($makeStack(), 'missing', $wal, $walBytes, $databaseBytes, [2]));
};

$tests['wal savepoint reader restart current next50 rejects mismatched wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerRestartCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $wal, $walBytes . 'x', $databaseBytes, [2]));
};

return $tests;
