<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp91 schema base')
    . $page('wp91 active_plugins base')
    . $page('wp91 autoload index base')
    . $page('wp91 cron transient base');

$makeWal = static function (int $checkpoint = 91, int $salt1 = 0x91000011, int $salt2 = 0x91000022) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $append(1, 4, $page('wp91 schema txn retained'));
    $append(2, 4, $page('wp91 parent active_plugins rolled back'));
    $append(3, 0, $page('wp91 parent autoload draft rolled back'));
    $append(2, 0, $page('wp91 released active_plugins rolled back'));
    $append(4, 4, $page('wp91 released cron commit rolled back'));

    return $bytes;
};

$walBytes = $makeWal();
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordWalFrameWrite(1, 1, true);
    $stack->savepoint('plugin-parent');
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->recordWalFrameWrite(3, 3);
    $stack->savepoint('released-plugin');
    $stack->recordWalFrameWrite(4, 2);
    $stack->recordWalFrameWrite(5, 4, true);

    return $stack;
};

$restart = static fn (?int $reader = null): array => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext(
    $stack(),
    'released-plugin',
    'plugin-parent',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4],
    'restart',
    $reader
);

$truncate = static fn (?int $reader = null): array => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext(
    $stack(),
    'released-plugin',
    'plugin-parent',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4],
    'truncate',
    $reader
);

$busy = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext(
    $stack(),
    'released-plugin',
    'plugin-parent',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 4],
    'restart',
    0
);

$partial = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext(
    $stack(),
    'released-plugin',
    'plugin-parent',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2],
    'restart',
    0,
    1
);

$staleCheckpointBytes = $makeWal(92);
$staleSaltBytes = $makeWal(91, 0x91000012, 0x91000022);
$shortWalBytes = substr($walBytes, 0, 32 + (4 * (24 + $pageSize)));

$cases = [
    'restart status ready' => [static fn (): mixed => $restart()['status'], 'ready'],
    'restart release name' => [static fn (): mixed => $restart()['released_savepoint'], 'released-plugin'],
    'restart rollback name' => [static fn (): mixed => $restart()['rollback_savepoint'], 'plugin-parent'],
    'restart verifies current source' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'restart current source frame count' => [static fn (): mixed => $restart()['current_source']['frame_count'], 5],
    'restart current source bytes length' => [static fn (): mixed => $restart()['current_source']['wal_bytes_length'], strlen($walBytes)],
    'restart current source checkpoint' => [static fn (): mixed => $restart()['current_source']['checkpoint_sequence'], 91],
    'restart current source salt one' => [static fn (): mixed => $restart()['current_source']['salt1'], 0x91000011],
    'restart retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 1],
    'restart discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 4],
    'restart retained source frame count' => [static fn (): mixed => $restart()['retained_source']['frame_count'], 1],
    'restart retained source bytes length' => [static fn (): mixed => $restart()['retained_source']['wal_bytes_length'], 32 + (1 * (24 + $pageSize))],
    'restart retained checkpoint unchanged' => [static fn (): mixed => $restart()['retained_source']['checkpoint_sequence'], 91],
    'restart next source kind' => [static fn (): mixed => $restart()['next_source']['kind'], 'restart_wal'],
    'restart next source frame count' => [static fn (): mixed => $restart()['next_source']['frame_count'], 0],
    'restart next source bytes length' => [static fn (): mixed => $restart()['next_source']['wal_bytes_length'], 32],
    'restart next source checkpoint increments' => [static fn (): mixed => $restart()['next_source']['checkpoint_sequence'], 92],
    'restart next source salt changes' => [static fn (): mixed => $restart()['next_source']['salt1'] !== $restart()['current_source']['salt1'], true],
    'restart next database length' => [static fn (): mixed => $restart()['next_source']['database_bytes_length'], strlen($databaseBytes)],
    'restart released frame names' => [static fn (): mixed => $restart()['released_frame_names'], ['released-plugin']],
    'restart merged page numbers' => [static fn (): mixed => $restart()['merged_page_numbers'], [2, 4]],
    'restart rolled back released frames' => [static fn (): mixed => $restart()['rolled_back_released_frames'], [4, 5]],
    'restart rolled back released pages' => [static fn (): mixed => $restart()['rolled_back_released_pages'], [2, 4]],
    'restart release target not transaction' => [static fn (): mixed => $restart()['release']['target_is_transaction'], false],
    'restart release result depth' => [static fn (): mixed => $restart()['release']['result_depth'], 2],
    'restart boundary mode' => [static fn (): mixed => $restart()['boundary']['mode'], 'restart'],
    'restart boundary wal action' => [static fn (): mixed => $restart()['boundary']['wal_action'], 'restart_wal'],
    'restart checkpoint not busy' => [static fn (): mixed => $restart()['boundary']['checkpoint_busy'], false],
    'restart checkpoint reason' => [static fn (): mixed => $restart()['boundary']['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'restart current reader end frame' => [static fn (): mixed => $restart()['boundary']['current_reader_end_frame'], 1],
    'restart next reader end frame' => [static fn (): mixed => $restart()['boundary']['next_reader_end_frame'], 0],
    'restart current reader sources' => [static fn (): mixed => $restart()['current_reader_sources'], ['wal', 'database', 'database', 'database']],
    'restart next reader sources' => [static fn (): mixed => $restart()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'restart current frames' => [static fn (): mixed => $restart()['current_reader_frame_indexes'], [1, null, null, null]],
    'restart next frames' => [static fn (): mixed => $restart()['next_reader_frame_indexes'], [null, null, null, null]],
    'restart images match' => [static fn (): mixed => $restart()['images_match'], true],
    'restart retained schema visible' => [static fn (): mixed => str_contains($restart()['boundary']['current_reader_images'][0], 'schema txn retained'), true],
    'restart parent option rolled back' => [static fn (): mixed => str_contains($restart()['boundary']['next_reader_images'][1], 'active_plugins base'), true],
    'restart released option rolled back' => [static fn (): mixed => str_contains($restart()['boundary']['next_reader_images'][1], 'released active_plugins'), false],
    'restart released cron rolled back' => [static fn (): mixed => str_contains($restart()['boundary']['next_reader_images'][3], 'cron transient base'), true],
    'restart dependency names current source' => [static fn (): mixed => in_array('sqlite-wal-release-rollback-checkpoint-current-source-next91', $restart()['dependencies'], true), true],
    'restart dependency keeps prior behavior' => [static fn (): mixed => in_array('sqlite-wal-release-rollback-checkpoint-current-next', $restart()['dependencies'], true), true],

    'truncate status ready' => [static fn (): mixed => $truncate()['status'], 'ready'],
    'truncate boundary mode' => [static fn (): mixed => $truncate()['boundary']['mode'], 'truncate'],
    'truncate wal action' => [static fn (): mixed => $truncate()['boundary']['wal_action'], 'truncate_wal'],
    'truncate next source kind database' => [static fn (): mixed => $truncate()['next_source']['kind'], 'checkpoint_database'],
    'truncate next source bytes empty' => [static fn (): mixed => $truncate()['next_source']['wal_bytes_length'], 0],
    'truncate next source checkpoint null' => [static fn (): mixed => $truncate()['next_source']['checkpoint_sequence'], null],
    'truncate next reader sources' => [static fn (): mixed => $truncate()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'truncate images match' => [static fn (): mixed => $truncate()['images_match'], true],

    'busy status' => [static fn (): mixed => $busy()['status'], 'busy'],
    'busy reason' => [static fn (): mixed => $busy()['boundary']['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'busy action preserves wal' => [static fn (): mixed => $busy()['boundary']['wal_action'], 'preserve_wal'],
    'busy next source preserve' => [static fn (): mixed => $busy()['next_source']['kind'], 'preserve_wal'],
    'busy next source frame count' => [static fn (): mixed => $busy()['next_source']['frame_count'], 1],
    'busy current sources' => [static fn (): mixed => $busy()['current_reader_sources'], ['database', 'database', 'database']],
    'busy next sources' => [static fn (): mixed => $busy()['next_reader_sources'], ['wal', 'database', 'database']],
    'busy images differ' => [static fn (): mixed => $busy()['images_match'], false],

    'partial current reader frame' => [static fn (): mixed => $partial()['boundary']['current_reader_end_frame'], 0],
    'partial next reader frame' => [static fn (): mixed => $partial()['boundary']['next_reader_end_frame'], 1],
    'partial current sources' => [static fn (): mixed => $partial()['current_reader_sources'], ['database', 'database']],
    'partial next sources' => [static fn (): mixed => $partial()['next_reader_sources'], ['wal', 'database']],
    'partial images differ' => [static fn (): mixed => $partial()['images_match'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal restart truncate savepoint current source next91 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal restart truncate savepoint current source next91 rejects stale checkpoint'] = static function (TestRunner $t) use ($stack, $wal, $staleCheckpointBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext($stack(), 'released-plugin', 'plugin-parent', $wal, $staleCheckpointBytes, $databaseBytes, [1]));
};

$tests['wal restart truncate savepoint current source next91 rejects stale salt'] = static function (TestRunner $t) use ($stack, $wal, $staleSaltBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext($stack(), 'released-plugin', 'plugin-parent', $wal, $staleSaltBytes, $databaseBytes, [1]));
};

$tests['wal restart truncate savepoint current source next91 rejects short current source'] = static function (TestRunner $t) use ($stack, $wal, $shortWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext($stack(), 'released-plugin', 'plugin-parent', $wal, $shortWalBytes, $databaseBytes, [1]));
};

$tests['wal restart truncate savepoint current source next91 rejects duplicate savepoint names'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext($stack(), 'plugin-parent', 'plugin-parent', $wal, $walBytes, $databaseBytes, [1]));
};

$tests['wal restart truncate savepoint current source next91 rejects missing release savepoint'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext($stack(), 'missing', 'plugin-parent', $wal, $walBytes, $databaseBytes, [1]));
};

$tests['wal restart truncate savepoint current source next91 rejects missing rollback savepoint'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext($stack(), 'released-plugin', 'missing', $wal, $walBytes, $databaseBytes, [1]));
};

$tests['wal restart truncate savepoint current source next91 rejects empty page list'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext($stack(), 'released-plugin', 'plugin-parent', $wal, $walBytes, $databaseBytes, []));
};

$tests['wal restart truncate savepoint current source next91 rejects non integer page'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext($stack(), 'released-plugin', 'plugin-parent', $wal, $walBytes, $databaseBytes, [1, '2']));
};

$tests['wal restart truncate savepoint current source next91 rejects unsupported mode'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext($stack(), 'released-plugin', 'plugin-parent', $wal, $walBytes, $databaseBytes, [1], 'passive'));
};

return $tests;
