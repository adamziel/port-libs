<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db schema before current source next87')
    . $page('db active_plugins before current source next87')
    . $page('db autoload index before current source next87')
    . $page('db transients before current source next87');

$makeWal = static function (array $frames, int $checkpoint = 87, int $salt1 = 0x20260587, int $salt2 = 0x87000001) use ($pageSize, $page): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [1, 0, 'wal schema retained current source next87'],
    [2, 4, 'wal active_plugins retained commit next87'],
    [3, 0, 'wal plugin index draft current source next87'],
    [2, 0, 'wal rolled back option draft next87'],
    [4, 4, 'wal rolled back transient commit next87'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('db schema before current source next87'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordPageImageWrite(2, $page('db active_plugins before current source next87'));
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch');
    $stack->recordPageImageWrite(3, $page('db autoload index before current source next87'));
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 2);
    $stack->recordWalFrameWrite(5, 4, true);

    return $stack;
};

$restart = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext(
    $makeStack(),
    'plugin-batch',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4],
    'restart'
);

$truncate = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext(
    $makeStack(),
    'plugin-batch',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4],
    'truncate'
);

$busy = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext(
    $makeStack(),
    'plugin-batch',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4],
    'restart',
    1
);

$passive = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext(
    $makeStack(),
    'plugin-batch',
    $wal,
    $walBytes,
    $databaseBytes,
    [2, 3],
    'passive',
    1,
    2
);

$cases = [
    'restart status ready' => [static fn (): mixed => $restart()['status'], 'ready'],
    'restart mode normalized' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart verifies current source' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'restart current source frame count' => [static fn (): mixed => $restart()['current_source']['frame_count'], 5],
    'restart current source wal bytes length' => [static fn (): mixed => $restart()['current_source']['wal_bytes_length'], strlen($walBytes)],
    'restart current source checkpoint' => [static fn (): mixed => $restart()['current_source']['checkpoint_sequence'], 87],
    'restart current source salt one' => [static fn (): mixed => $restart()['current_source']['salt1'], 0x20260587],
    'restart retained source frame count' => [static fn (): mixed => $restart()['retained_source']['frame_count'], 2],
    'restart retained source bytes length' => [static fn (): mixed => $restart()['retained_source']['wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'restart retained source keeps checkpoint' => [static fn (): mixed => $restart()['retained_source']['checkpoint_sequence'], 87],
    'restart retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'restart discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 3],
    'restart wal action' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal'],
    'restart next source kind' => [static fn (): mixed => $restart()['next_source']['kind'], 'restart_wal'],
    'restart next source frame count' => [static fn (): mixed => $restart()['next_source']['frame_count'], 0],
    'restart next source bytes length' => [static fn (): mixed => $restart()['next_source']['wal_bytes_length'], 32],
    'restart next source checkpoint increments' => [static fn (): mixed => $restart()['next_source']['checkpoint_sequence'], 88],
    'restart next source salt changes' => [static fn (): mixed => $restart()['next_source']['salt1'] !== $restart()['current_source']['salt1'], true],
    'restart next database length' => [static fn (): mixed => $restart()['next_source']['database_bytes_length'], strlen($databaseBytes)],
    'restart current reader end frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart next reader end frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 0],
    'restart current reader sources' => [static fn (): mixed => $restart()['current_reader_sources'], ['wal', 'wal', 'database', 'database']],
    'restart next reader sources' => [static fn (): mixed => $restart()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'restart current reader frames' => [static fn (): mixed => $restart()['current_reader_frame_indexes'], [1, 2, null, null]],
    'restart next reader frames' => [static fn (): mixed => $restart()['next_reader_frame_indexes'], [null, null, null, null]],
    'restart current reader kept wal snapshot' => [static fn (): mixed => $restart()['current_reader_kept_wal_snapshot'], true],
    'restart next uses checkpoint database' => [static fn (): mixed => $restart()['next_reader_uses_checkpoint_database'], true],
    'restart images match' => [static fn (): mixed => $restart()['images_match'], true],
    'restart current schema image retained' => [static fn (): mixed => str_contains($restart()['current_reader_images'][0], 'schema retained'), true],
    'restart current option image retained' => [static fn (): mixed => str_contains($restart()['current_reader_images'][1], 'active_plugins retained'), true],
    'restart next excludes rolled back option' => [static fn (): mixed => str_contains($restart()['next_reader_images'][1], 'rolled back option'), false],
    'restart dependency names next87' => [static fn (): mixed => in_array('sqlite-wal-savepoint-reader-current-source-next87', $restart()['dependencies'], true), true],

    'truncate status ready' => [static fn (): mixed => $truncate()['status'], 'ready'],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal'],
    'truncate next source kind database' => [static fn (): mixed => $truncate()['next_source']['kind'], 'checkpoint_database'],
    'truncate next source has no salt' => [static fn (): mixed => $truncate()['next_source']['salt1'], null],
    'truncate next source frame count zero' => [static fn (): mixed => $truncate()['next_source']['frame_count'], 0],
    'truncate next source wal bytes empty' => [static fn (): mixed => $truncate()['next_source']['wal_bytes_length'], 0],
    'truncate next source page size kept' => [static fn (): mixed => $truncate()['next_source']['page_size'], $pageSize],
    'truncate next reader sources' => [static fn (): mixed => $truncate()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'truncate images match' => [static fn (): mixed => $truncate()['images_match'], true],

    'busy status busy' => [static fn (): mixed => $busy()['status'], 'busy'],
    'busy reason reader blocks' => [static fn (): mixed => $busy()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'busy wal action preserves' => [static fn (): mixed => $busy()['wal_action'], 'preserve_wal'],
    'busy current reader end frame' => [static fn (): mixed => $busy()['current_reader_end_frame'], 1],
    'busy next reader end frame' => [static fn (): mixed => $busy()['next_reader_end_frame'], 2],
    'busy next source kind preserve' => [static fn (): mixed => $busy()['next_source']['kind'], 'preserve_wal'],
    'busy next source frame count' => [static fn (): mixed => $busy()['next_source']['frame_count'], 2],
    'busy next source bytes retained' => [static fn (): mixed => $busy()['next_source']['wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'busy current reader sources' => [static fn (): mixed => $busy()['current_reader_sources'], ['database', 'database', 'database', 'database']],
    'busy next reader sources' => [static fn (): mixed => $busy()['next_reader_sources'], ['wal', 'wal', 'database', 'database']],
    'busy images differ' => [static fn (): mixed => $busy()['images_match'], false],

    'passive status ready' => [static fn (): mixed => $passive()['status'], 'ready'],
    'passive reason reader limited' => [static fn (): mixed => $passive()['checkpoint_reason'], 'reader_limited_passive_checkpoint'],
    'passive wal preserved' => [static fn (): mixed => $passive()['wal_action'], 'preserve_wal'],
    'passive current frames' => [static fn (): mixed => $passive()['current_reader_frame_indexes'], [null, null]],
    'passive next frames' => [static fn (): mixed => $passive()['next_reader_frame_indexes'], [2, null]],
    'passive images differ' => [static fn (): mixed => $passive()['images_match'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint savepoint reader current source next87 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint savepoint reader current source next87 rejects stale checkpoint source'] = static function (TestRunner $t) use ($makeStack, $wal, $makeWal, $databaseBytes): void {
    $staleBytes = $makeWal([
        [1, 0, 'wal schema retained current source next87'],
        [2, 4, 'wal active_plugins retained commit next87'],
    ], 88);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext($makeStack(), 'plugin-batch', $wal, $staleBytes, $databaseBytes, [1]));
};

$tests['wal checkpoint savepoint reader current source next87 rejects stale salt source'] = static function (TestRunner $t) use ($makeStack, $wal, $makeWal, $databaseBytes): void {
    $staleBytes = $makeWal([
        [1, 0, 'wal schema retained current source next87'],
        [2, 4, 'wal active_plugins retained commit next87'],
        [3, 0, 'wal plugin index draft current source next87'],
        [2, 0, 'wal rolled back option draft next87'],
        [4, 4, 'wal rolled back transient commit next87'],
    ], 87, 0x20260588, 0x87000001);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext($makeStack(), 'plugin-batch', $wal, $staleBytes, $databaseBytes, [1]));
};

$tests['wal checkpoint savepoint reader current source next87 rejects stale frame count'] = static function (TestRunner $t) use ($makeStack, $wal, $makeWal, $databaseBytes): void {
    $staleBytes = $makeWal([
        [1, 0, 'wal schema retained current source next87'],
        [2, 4, 'wal active_plugins retained commit next87'],
    ]);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext($makeStack(), 'plugin-batch', $wal, $staleBytes, $databaseBytes, [1]));
};

$tests['wal checkpoint savepoint reader current source next87 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext($makeStack(), 'plugin-batch', $wal, $walBytes, $databaseBytes, []));
};

$tests['wal checkpoint savepoint reader current source next87 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext($makeStack(), 'plugin-batch', $wal, $walBytes, $databaseBytes, ['2']));
};

$tests['wal checkpoint savepoint reader current source next87 rejects missing savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext($makeStack(), 'missing', $wal, $walBytes, $databaseBytes, [1]));
};

return $tests;
