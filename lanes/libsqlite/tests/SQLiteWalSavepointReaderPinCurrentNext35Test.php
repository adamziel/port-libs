<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('base schema page before import')
    . $page('base wp_options rows before import')
    . $page('base autoload index before import');

$makeWal = static function (array $frames) use ($pageSize): string {
    $salt1 = 0x35353535;
    $salt2 = 0x75757575;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 35, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [2, 0, $page('frame 1 draft option import')],
    [3, 3, $page('frame 2 committed autoload index')],
    [2, 0, $page('frame 3 plugin savepoint draft')],
    [2, 3, $page('frame 4 plugin savepoint commit')],
    [3, 0, $page('frame 5 nested transient draft')],
]);
$wal = SQLiteWal::parse($walBytes, null, true);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(2, $page('base wp_options rows before import'));
    $stack->recordWalFrameWrite(1, 2);
    $stack->recordPageImageWrite(3, $page('base autoload index before import'));
    $stack->recordWalFrameWrite(2, 3, true);
    $stack->savepoint('plugin-settings');
    $stack->recordWalFrameWrite(3, 2);
    $stack->recordWalFrameWrite(4, 2, true);
    $stack->savepoint('nested-transient');
    $stack->recordWalFrameWrite(5, 3);

    return $stack;
};

$restartPinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerPinCurrentNextAfterRollbackTo(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3],
    [2, 4, null],
    'restart'
);

$truncatePinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerPinCurrentNextAfterRollbackTo(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [2, 3],
    [2],
    'truncate'
);

$passivePinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerPinCurrentNextAfterRollbackTo(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [2, 3],
    [1],
    'passive'
);

$cases = [
    'restart status busy while pinned reader holds retained frame' => [static fn (): mixed => $restartPinned()['status'], 'busy'],
    'restart mode normalized' => [static fn (): mixed => $restartPinned()['mode'], 'restart'],
    'restart savepoint preserved' => [static fn (): mixed => $restartPinned()['savepoint'], 'plugin-settings'],
    'restart retained frame count stops before savepoint' => [static fn (): mixed => $restartPinned()['retained_frame_count'], 2],
    'restart discarded frame count covers savepoint and nested frames' => [static fn (): mixed => $restartPinned()['discarded_frame_count'], 3],
    'restart current reader uses pinned frame two' => [static fn (): mixed => $restartPinned()['current_reader_end_frame'], 2],
    'restart next reader uses retained wal frame count' => [static fn (): mixed => $restartPinned()['next_reader_end_frame'], 2],
    'restart checkpoint busy flag set' => [static fn (): mixed => $restartPinned()['checkpoint_busy'], true],
    'restart checkpoint reason is reset blocked' => [static fn (): mixed => $restartPinned()['checkpoint_reason'], 'reader_blocks_wal_reset'],
    'restart wal action preserves wal' => [static fn (): mixed => $restartPinned()['wal_action'], 'preserve_wal'],
    'restart current sources include database and wal' => [static fn (): mixed => $restartPinned()['current_reader_sources'], ['database', 'wal', 'wal']],
    'restart next sources include database and wal' => [static fn (): mixed => $restartPinned()['next_reader_sources'], ['database', 'wal', 'wal']],
    'restart current frame indexes use retained snapshot' => [static fn (): mixed => $restartPinned()['current_reader_frame_indexes'], [null, 1, 2]],
    'restart next frame indexes keep retained wal' => [static fn (): mixed => $restartPinned()['next_reader_frame_indexes'], [null, 1, 2]],
    'restart current page two sees retained draft row' => [static fn (): mixed => str_contains($restartPinned()['current_reader'][1]['image'], 'draft option import'), true],
    'restart current page three sees committed index' => [static fn (): mixed => str_contains($restartPinned()['current_reader'][2]['image'], 'committed autoload index'), true],
    'restart next page two sees retained draft row after rollback' => [static fn (): mixed => str_contains($restartPinned()['next_reader'][1]['image'], 'draft option import'), true],
    'restart next page three keeps committed index' => [static fn (): mixed => str_contains($restartPinned()['next_reader'][2]['image'], 'committed autoload index'), true],
    'restart current reader kept wal snapshot' => [static fn (): mixed => $restartPinned()['current_reader_kept_wal_snapshot'], true],
    'restart next reader does not use checkpoint-only database' => [static fn (): mixed => $restartPinned()['next_reader_uses_checkpoint_database'], false],
    'restart next reader uses preserved wal' => [static fn (): mixed => $restartPinned()['next_reader_uses_preserved_wal'], true],
    'restart images match across current and next' => [static fn (): mixed => $restartPinned()['images_match'], true],
    'restart current readmark mx frame is original wal count' => [static fn (): mixed => $restartPinned()['current_read_marks']['mx_frame'], 5],
    'restart current readmark last commit is savepoint commit' => [static fn (): mixed => $restartPinned()['current_read_marks']['last_commit_frame'], 4],
    'restart current readmark pinned frame is oldest active reader' => [static fn (): mixed => $restartPinned()['current_read_marks']['checkpoint_pinned_frame'], 2],
    'restart current readmark cannot finish checkpoint' => [static fn (): mixed => $restartPinned()['current_read_marks']['checkpoint_can_finish'], false],
    'restart current readmark reset blocked' => [static fn (): mixed => $restartPinned()['current_read_marks']['reset_blocked'], true],
    'restart current readmark stale reader reason' => [static fn (): mixed => $restartPinned()['current_read_marks']['read_marks'][0]['reason'], 'reader_pins_older_snapshot'],
    'restart current readmark reusable slots include stale and unused' => [static fn (): mixed => $restartPinned()['current_read_marks']['reusable_slots'], [0, 2]],
    'restart next readmark mx frame is retained prefix' => [static fn (): mixed => $restartPinned()['next_read_marks']['mx_frame'], 2],
    'restart next readmark last commit is retained frame two' => [static fn (): mixed => $restartPinned()['next_read_marks']['last_commit_frame'], 2],
    'restart next readmark can finish checkpoint' => [static fn (): mixed => $restartPinned()['next_read_marks']['checkpoint_can_finish'], true],
    'restart next readmark reset not blocked' => [static fn (): mixed => $restartPinned()['next_read_marks']['reset_blocked'], false],
    'restart dependency includes reader pin marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-reader-pin-current-next', $restartPinned()['dependencies'], true), true],
    'restart dependency includes savepoint checkpoint marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $restartPinned()['dependencies'], true), true],
    'truncate status busy with retained reader pin' => [static fn (): mixed => $truncatePinned()['status'], 'busy'],
    'truncate checkpoint reason is reset blocked' => [static fn (): mixed => $truncatePinned()['checkpoint_reason'], 'reader_blocks_wal_reset'],
    'truncate wal action remains preserve while reader pinned' => [static fn (): mixed => $truncatePinned()['wal_action'], 'preserve_wal'],
    'truncate current sources' => [static fn (): mixed => $truncatePinned()['current_reader_sources'], ['wal', 'wal']],
    'truncate next sources' => [static fn (): mixed => $truncatePinned()['next_reader_sources'], ['wal', 'wal']],
    'truncate current frames' => [static fn (): mixed => $truncatePinned()['current_reader_frame_indexes'], [1, 2]],
    'truncate next frames' => [static fn (): mixed => $truncatePinned()['next_reader_frame_indexes'], [1, 2]],
    'truncate next readmarks retained prefix' => [static fn (): mixed => $truncatePinned()['next_read_marks']['mx_frame'], 2],
    'truncate next reader uses preserved wal' => [static fn (): mixed => $truncatePinned()['next_reader_uses_preserved_wal'], true],
    'passive status ready despite older reader pin' => [static fn (): mixed => $passivePinned()['status'], 'ready'],
    'passive checkpoint reason is reader limited' => [static fn (): mixed => $passivePinned()['checkpoint_reason'], 'reader_limited_passive_checkpoint'],
    'passive checkpoint busy false' => [static fn (): mixed => $passivePinned()['checkpoint_busy'], false],
    'passive current reader frame is oldest pin' => [static fn (): mixed => $passivePinned()['current_reader_end_frame'], 1],
    'passive next reader frame count retained' => [static fn (): mixed => $passivePinned()['next_reader_end_frame'], 2],
    'passive current sources show database-only precommit reader' => [static fn (): mixed => $passivePinned()['current_reader_sources'], ['database', 'database']],
    'passive next sources show retained wal view' => [static fn (): mixed => $passivePinned()['next_reader_sources'], ['wal', 'wal']],
    'passive current page two sees base import row' => [static fn (): mixed => str_contains($passivePinned()['current_reader'][0]['image'], 'base wp_options rows'), true],
    'passive next page two sees retained import frame' => [static fn (): mixed => str_contains($passivePinned()['next_reader'][0]['image'], 'draft option import'), true],
    'passive images differ across current and next' => [static fn (): mixed => $passivePinned()['images_match'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal savepoint reader pin current next35 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal savepoint reader pin current next35 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerPinCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, [], [1]));
};

$tests['wal savepoint reader pin current next35 rejects empty readmarks'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerPinCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, [2], []));
};

$tests['wal savepoint reader pin current next35 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerPinCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, ['2'], [1]));
};

$tests['wal savepoint reader pin current next35 rejects invalid readmark'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerPinCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, [2], [-1]));
};

$tests['wal savepoint reader pin current next35 rejects missing savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerPinCurrentNextAfterRollbackTo($makeStack(), 'missing', $wal, $walBytes, $databaseBytes, [2], [1]));
};

return $tests;
