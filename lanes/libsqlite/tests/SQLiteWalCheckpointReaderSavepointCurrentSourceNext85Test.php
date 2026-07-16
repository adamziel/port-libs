<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db schema before plugin import')
    . $page('db wp_options before plugin import')
    . $page('db plugin settings before savepoint')
    . $page('db autoload index before savepoint')
    . $page('db transient before savepoint');

$makeWalBytes = static function () use ($pageSize, $page): string {
    $salt1 = 0x85858585;
    $salt2 = 0x85858586;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 85, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $label) use (&$bytes, &$seed, $salt1, $salt2, $page): void {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $append(1, 0, 'wal schema retained before savepoint');
    $append(2, 5, 'wal wp_options retained commit');
    $append(3, 0, 'wal plugin settings draft discarded');
    $append(4, 5, 'wal autoload index commit discarded');
    $append(5, 5, 'wal transient commit discarded');

    return $bytes;
};

$walBytes = $makeWalBytes();
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('db schema before plugin import'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordPageImageWrite(2, $page('db wp_options before plugin import'));
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings');
    $stack->recordPageImageWrite(3, $page('db plugin settings before savepoint'));
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordPageImageWrite(4, $page('db autoload index before savepoint'));
    $stack->recordWalFrameWrite(4, 4, true);
    $stack->savepoint('transient-cache');
    $stack->recordPageImageWrite(5, $page('db transient before savepoint'));
    $stack->recordWalFrameWrite(5, 5, true);

    return $stack;
};

$restartPinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointCurrentSourceNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'restart',
    5
);

$truncateLatest = static fn (): array => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointCurrentSourceNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'truncate',
    2
);

$passivePinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointCurrentSourceNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [2, 3, 4],
    'passive',
    2
);

$cases = [
    'restart status busy while reader pins reset' => [static fn (): mixed => $restartPinned()['status'], 'busy'],
    'restart mode preserved' => [static fn (): mixed => $restartPinned()['mode'], 'restart'],
    'restart wal action preserves wal' => [static fn (): mixed => $restartPinned()['wal_action'], 'preserve_wal'],
    'restart reason is reader reset block' => [static fn (): mixed => $restartPinned()['checkpoint_reason'], 'reader_blocks_wal_reset'],
    'restart checkpoint busy' => [static fn (): mixed => $restartPinned()['checkpoint_busy'], true],
    'restart original reader frame' => [static fn (): mixed => $restartPinned()['original_reader_end_frame'], 5],
    'restart current reader frame clamps to retained prefix' => [static fn (): mixed => $restartPinned()['current_reader_end_frame'], 2],
    'restart next reader frame preserves retained wal' => [static fn (): mixed => $restartPinned()['next_reader_end_frame'], 2],
    'restart retained frames' => [static fn (): mixed => $restartPinned()['retained_frame_count'], 2],
    'restart discarded frames' => [static fn (): mixed => $restartPinned()['discarded_frame_count'], 3],
    'restart rolled back pages' => [static fn (): mixed => $restartPinned()['rolled_back_page_numbers'], [3, 4, 5]],
    'restart rolled back frames' => [static fn (): mixed => $restartPinned()['rolled_back_frame_indexes'], [3, 4, 5]],
    'restart current sources' => [static fn (): mixed => $restartPinned()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'restart next sources' => [static fn (): mixed => $restartPinned()['next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'restart source transitions' => [static fn (): mixed => $restartPinned()['source_transitions'], ['wal>wal>wal', 'wal>wal>wal', 'wal>database>database', 'wal>database>database', 'wal>database>database']],
    'restart current source counts wal' => [static fn (): mixed => $restartPinned()['current_source_counts']['wal'], 2],
    'restart current source counts database' => [static fn (): mixed => $restartPinned()['current_source_counts']['database'], 3],
    'restart next source counts wal' => [static fn (): mixed => $restartPinned()['next_source_counts']['wal'], 2],
    'restart next source counts database' => [static fn (): mixed => $restartPinned()['next_source_counts']['database'], 3],
    'restart uses rollback prefix' => [static fn (): mixed => $restartPinned()['current_uses_rollback_prefix'], true],
    'restart next keeps preserved wal' => [static fn (): mixed => $restartPinned()['next_uses_preserved_wal'], true],
    'restart next is not database only' => [static fn (): mixed => $restartPinned()['next_uses_checkpoint_database'], false],
    'restart images match after checkpoint' => [static fn (): mixed => $restartPinned()['images_match'], true],
    'restart yield count covers three readers' => [static fn (): mixed => $restartPinned()['yield_count'], 15],
    'restart row page one transition' => [static fn (): mixed => $restartPinned()['current_source_rows'][0]['source_transition'], 'wal>wal>wal'],
    'restart row page two current frame' => [static fn (): mixed => $restartPinned()['current_source_rows'][1]['current_frame'], 2],
    'restart row page three before frame' => [static fn (): mixed => $restartPinned()['current_source_rows'][2]['before_frame'], 3],
    'restart row page three current frame null' => [static fn (): mixed => $restartPinned()['current_source_rows'][2]['current_frame'], null],
    'restart row page four rollback changed' => [static fn (): mixed => $restartPinned()['current_source_rows'][3]['rollback_changed_current'], true],
    'restart row page five checkpoint unchanged' => [static fn (): mixed => $restartPinned()['current_source_rows'][4]['checkpoint_changed_next'], false],
    'restart row page three current label' => [static fn (): mixed => $restartPinned()['current_source_rows'][2]['current_label'], 'db plugin settings before savepoint'],
    'restart row page four next label' => [static fn (): mixed => $restartPinned()['current_source_rows'][3]['next_label'], 'db autoload index before savepoint'],
    'restart dependency marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-savepoint-current-source-next85', $restartPinned()['dependencies'], true), true],
    'restart preserves yield dependency' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-yield-current-next', $restartPinned()['dependencies'], true), true],

    'truncate status busy with pinned retained reader' => [static fn (): mixed => $truncateLatest()['status'], 'busy'],
    'truncate mode preserved' => [static fn (): mixed => $truncateLatest()['mode'], 'truncate'],
    'truncate wal action preserves wal while reader pins reset' => [static fn (): mixed => $truncateLatest()['wal_action'], 'preserve_wal'],
    'truncate reason is reader reset block' => [static fn (): mixed => $truncateLatest()['checkpoint_reason'], 'reader_blocks_wal_reset'],
    'truncate original reader frame' => [static fn (): mixed => $truncateLatest()['original_reader_end_frame'], 2],
    'truncate current reader frame' => [static fn (): mixed => $truncateLatest()['current_reader_end_frame'], 2],
    'truncate next reader frame' => [static fn (): mixed => $truncateLatest()['next_reader_end_frame'], 2],
    'truncate transitions match restart current source' => [static fn (): mixed => $truncateLatest()['source_transitions'][2], 'database>database>database'],
    'truncate page three before source database' => [static fn (): mixed => $truncateLatest()['current_source_rows'][2]['before_source'], 'database'],
    'truncate page three rollback unchanged' => [static fn (): mixed => $truncateLatest()['current_source_rows'][2]['rollback_changed_current'], false],
    'truncate page one checkpoint unchanged through wal source' => [static fn (): mixed => $truncateLatest()['current_source_rows'][0]['checkpoint_changed_next'], false],
    'truncate next keeps wal because reset is blocked' => [static fn (): mixed => $truncateLatest()['next_uses_preserved_wal'], true],
    'truncate images match' => [static fn (): mixed => $truncateLatest()['images_match'], true],

    'passive status ready' => [static fn (): mixed => $passivePinned()['status'], 'ready'],
    'passive mode preserved' => [static fn (): mixed => $passivePinned()['mode'], 'passive'],
    'passive reason complete' => [static fn (): mixed => $passivePinned()['checkpoint_reason'], 'passive_checkpoint_complete'],
    'passive wal action preserves wal' => [static fn (): mixed => $passivePinned()['wal_action'], 'preserve_wal'],
    'passive current sources' => [static fn (): mixed => $passivePinned()['current_sources'], ['wal', 'database', 'database']],
    'passive next sources' => [static fn (): mixed => $passivePinned()['next_sources'], ['wal', 'database', 'database']],
    'passive transitions' => [static fn (): mixed => $passivePinned()['source_transitions'], ['wal>wal>wal', 'database>database>database', 'database>database>database']],
    'passive current counts database' => [static fn (): mixed => $passivePinned()['current_source_counts']['database'], 2],
    'passive current counts wal' => [static fn (): mixed => $passivePinned()['current_source_counts']['wal'], 1],
    'passive next counts database' => [static fn (): mixed => $passivePinned()['next_source_counts']['database'], 2],
    'passive row page two frame' => [static fn (): mixed => $passivePinned()['current_source_rows'][0]['current_frame'], 2],
    'passive row page three label' => [static fn (): mixed => $passivePinned()['current_source_rows'][1]['next_label'], 'db plugin settings before savepoint'],
    'passive yield count' => [static fn (): mixed => $passivePinned()['yield_count'], 9],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint reader savepoint current source next85 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint reader savepoint current source next85 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointCurrentSourceNext($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, []));
};

$tests['wal checkpoint reader savepoint current source next85 rejects bad page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointCurrentSourceNext($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, ['3']));
};

$tests['wal checkpoint reader savepoint current source next85 rejects bad savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointCurrentSourceNext($makeStack(), 'missing', $wal, $walBytes, $databaseBytes, [1]));
};

$tests['wal checkpoint reader savepoint current source next85 rejects bad mode'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointCurrentSourceNext($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, [1], 'invalid'));
};

return $tests;
