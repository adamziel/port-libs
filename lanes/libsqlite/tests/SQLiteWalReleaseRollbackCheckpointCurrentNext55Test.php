<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x55555555;
$salt2 = 0x95959595;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('base schema before plugin import')
    . $page('base active plugins option')
    . $page('base transient cache option')
    . $page('base plugin settings option')
    . $page('base autoload index page');

$makeWalBytes = static function () use ($pageSize, $salt1, $salt2, $page): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 55, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    $appendFrame = static function (int $pageNumber, int $commitPageCount, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $appendFrame(1, 0, $page('frame 1 retained schema import'));
    $appendFrame(2, 5, $page('frame 2 retained active_plugins commit'));
    $appendFrame(3, 0, $page('frame 3 parent transient draft'));
    $appendFrame(4, 0, $page('frame 4 released plugin settings draft'));
    $appendFrame(5, 5, $page('frame 5 released autoload index commit'));
    $appendFrame(3, 0, $page('frame 6 parent transient retry'));
    $appendFrame(4, 5, $page('frame 7 parent plugin settings commit'));

    return $bytes;
};

$walBytes = $makeWalBytes();
$wal = SQLiteWal::parse($walBytes, null, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch');
    $stack->recordWalFrameWrite(3, 3);
    $stack->savepoint('autoload-index');
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 5, true);
    $stack->recordWalFrameWrite(6, 3);
    $stack->recordWalFrameWrite(7, 4, true);

    return $stack;
};

$restart = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext(
    $makeStack(),
    'autoload-index',
    'plugin-batch',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'restart'
);

$truncate = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext(
    $makeStack(),
    'autoload-index',
    'plugin-batch',
    $wal,
    $walBytes,
    $databaseBytes,
    [2, 3, 4, 5],
    'truncate'
);

$busy = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext(
    $makeStack(),
    'autoload-index',
    'plugin-batch',
    $wal,
    $walBytes,
    $databaseBytes,
    [2, 4, 5],
    'restart',
    1
);

$cases = [
    'restart status ready' => [static fn (): mixed => $restart()['status'], 'ready'],
    'restart released savepoint name' => [static fn (): mixed => $restart()['released_savepoint'], 'autoload-index'],
    'restart rollback savepoint name' => [static fn (): mixed => $restart()['rollback_savepoint'], 'plugin-batch'],
    'restart release frame names include nested' => [static fn (): mixed => $restart()['release']['released_frame_names'], ['autoload-index']],
    'restart released names exclude rollback parent' => [static fn (): mixed => $restart()['released_frame_names'], ['autoload-index']],
    'restart release found index' => [static fn (): mixed => $restart()['release']['found_index'], 2],
    'restart release result depth' => [static fn (): mixed => $restart()['release']['result_depth'], 2],
    'restart release leaves transaction active' => [static fn (): mixed => $restart()['release']['transaction_active_after'], true],
    'restart release target is not transaction' => [static fn (): mixed => $restart()['release']['target_is_transaction'], false],
    'restart merged page numbers' => [static fn (): mixed => $restart()['merged_page_numbers'], [3, 4, 5]],
    'restart retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'restart discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 5],
    'restart rolled back released frame indexes' => [static fn (): mixed => $restart()['rolled_back_released_frames'], [4, 5, 6, 7]],
    'restart rolled back released pages' => [static fn (): mixed => $restart()['rolled_back_released_pages'], [3, 4, 5]],
    'restart boundary mode' => [static fn (): mixed => $restart()['boundary']['mode'], 'restart'],
    'restart boundary wal action' => [static fn (): mixed => $restart()['boundary']['wal_action'], 'restart_wal'],
    'restart boundary checkpoint reason' => [static fn (): mixed => $restart()['boundary']['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'restart current end frame' => [static fn (): mixed => $restart()['boundary']['current_reader_end_frame'], 2],
    'restart next end frame' => [static fn (): mixed => $restart()['boundary']['next_reader_end_frame'], 0],
    'restart current sources' => [static fn (): mixed => $restart()['current_reader_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'restart next sources' => [static fn (): mixed => $restart()['next_reader_sources'], ['database', 'database', 'database', 'database', 'database']],
    'restart current frame indexes' => [static fn (): mixed => $restart()['current_reader_frame_indexes'], [1, 2, null, null, null]],
    'restart next frame indexes' => [static fn (): mixed => $restart()['next_reader_frame_indexes'], [null, null, null, null, null]],
    'restart images match' => [static fn (): mixed => $restart()['images_match'], true],
    'restart current page one retained wal' => [static fn (): mixed => str_contains($restart()['boundary']['current_reader'][0]['image'], 'retained schema import'), true],
    'restart current page two retained wal' => [static fn (): mixed => str_contains($restart()['boundary']['current_reader'][1]['image'], 'retained active_plugins commit'), true],
    'restart current page three base after rollback' => [static fn (): mixed => str_contains($restart()['boundary']['current_reader'][2]['image'], 'base transient cache option'), true],
    'restart current page four base after released rollback' => [static fn (): mixed => str_contains($restart()['boundary']['current_reader'][3]['image'], 'base plugin settings option'), true],
    'restart current page five base after released rollback' => [static fn (): mixed => str_contains($restart()['boundary']['current_reader'][4]['image'], 'base autoload index page'), true],
    'restart next page one checkpoint database' => [static fn (): mixed => str_contains($restart()['boundary']['next_reader'][0]['image'], 'retained schema import'), true],
    'restart next page two checkpoint database' => [static fn (): mixed => str_contains($restart()['boundary']['next_reader'][1]['image'], 'retained active_plugins commit'), true],
    'restart next page four omits released draft' => [static fn (): mixed => str_contains(implode('', $restart()['boundary']['next_reader_images']), 'released plugin settings draft'), false],
    'restart next page five omits released commit' => [static fn (): mixed => str_contains(implode('', $restart()['boundary']['next_reader_images']), 'released autoload index commit'), false],
    'restart dependency has release rollback marker' => [static fn (): mixed => in_array('sqlite-wal-release-rollback-checkpoint-current-next', $restart()['dependencies'], true), true],
    'restart dependency has current next marker' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-boundary-current-next', $restart()['dependencies'], true), true],
    'restart dependency has current prefix marker' => [static fn (): mixed => in_array('sqlite-savepoint-wal-current-prefix', $restart()['dependencies'], true), true],
    'truncate status ready' => [static fn (): mixed => $truncate()['status'], 'ready'],
    'truncate mode' => [static fn (): mixed => $truncate()['boundary']['mode'], 'truncate'],
    'truncate wal action' => [static fn (): mixed => $truncate()['boundary']['wal_action'], 'truncate_wal'],
    'truncate checkpoint reason' => [static fn (): mixed => $truncate()['boundary']['checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'truncate retained frame count' => [static fn (): mixed => $truncate()['retained_frame_count'], 2],
    'truncate discarded frame count' => [static fn (): mixed => $truncate()['discarded_frame_count'], 5],
    'truncate current sources' => [static fn (): mixed => $truncate()['current_reader_sources'], ['wal', 'database', 'database', 'database']],
    'truncate next sources' => [static fn (): mixed => $truncate()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'truncate current frame indexes' => [static fn (): mixed => $truncate()['current_reader_frame_indexes'], [2, null, null, null]],
    'truncate next frame indexes' => [static fn (): mixed => $truncate()['next_reader_frame_indexes'], [null, null, null, null]],
    'truncate rolled back released frames' => [static fn (): mixed => $truncate()['rolled_back_released_frames'], [4, 5, 6, 7]],
    'truncate rolled back released pages' => [static fn (): mixed => $truncate()['rolled_back_released_pages'], [3, 4, 5]],
    'truncate images match' => [static fn (): mixed => $truncate()['images_match'], true],
    'truncate next omits parent retry' => [static fn (): mixed => str_contains(implode('', $truncate()['boundary']['next_reader_images']), 'parent transient retry'), false],
    'truncate next omits parent plugin commit' => [static fn (): mixed => str_contains(implode('', $truncate()['boundary']['next_reader_images']), 'parent plugin settings commit'), false],
    'busy status' => [static fn (): mixed => $busy()['status'], 'busy'],
    'busy checkpoint reason' => [static fn (): mixed => $busy()['boundary']['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'busy wal action preserves wal' => [static fn (): mixed => $busy()['boundary']['wal_action'], 'preserve_wal'],
    'busy current sources' => [static fn (): mixed => $busy()['current_reader_sources'], ['database', 'database', 'database']],
    'busy next sources preserve wal' => [static fn (): mixed => $busy()['next_reader_sources'], ['wal', 'database', 'database']],
    'busy next frame indexes preserve retained frame' => [static fn (): mixed => $busy()['next_reader_frame_indexes'], [2, null, null]],
    'busy images do not match after partial checkpoint' => [static fn (): mixed => $busy()['images_match'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal release rollback checkpoint current next55 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal release rollback checkpoint current next55 rejects empty released savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext($makeStack(), '', 'plugin-batch', $wal, $walBytes, $databaseBytes, [2]));
};

$tests['wal release rollback checkpoint current next55 rejects same savepoint names'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext($makeStack(), 'plugin-batch', 'plugin-batch', $wal, $walBytes, $databaseBytes, [2]));
};

$tests['wal release rollback checkpoint current next55 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext($makeStack(), 'autoload-index', 'plugin-batch', $wal, $walBytes, $databaseBytes, []));
};

$tests['wal release rollback checkpoint current next55 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext($makeStack(), 'autoload-index', 'plugin-batch', $wal, $walBytes, $databaseBytes, ['2']));
};

$tests['wal release rollback checkpoint current next55 rejects missing released savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext($makeStack(), 'missing', 'plugin-batch', $wal, $walBytes, $databaseBytes, [2]));
};

$tests['wal release rollback checkpoint current next55 rejects missing rollback savepoint after release'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext($makeStack(), 'autoload-index', 'missing', $wal, $walBytes, $databaseBytes, [2]));
};

$tests['wal release rollback checkpoint current next55 rejects mismatched wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext($makeStack(), 'autoload-index', 'plugin-batch', $wal, $walBytes . 'x', $databaseBytes, [2]));
};

return $tests;
