<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp86 schema base')
    . $page('wp86 option active_plugins base')
    . $page('wp86 autoload index base')
    . $page('wp86 cron option base');

$makeWalBytes = static function (int $checkpoint, int $salt1, int $salt2, string $tag) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $append(2, 0, $page("{$tag} active_plugins draft"));
    $append(3, 4, $page("{$tag} autoload index commit"));
    $append(2, 0, $page("{$tag} active_plugins latest"));
    $append(4, 4, $page("{$tag} cron commit"));

    return $bytes;
};

$currentWalBytes = $makeWalBytes(86, 0x86000011, 0x86000022, 'current');
$staleSaltBytes = $makeWalBytes(86, 0x86000012, 0x86000022, 'stale salt');
$staleCheckpointBytes = $makeWalBytes(87, 0x86000011, 0x86000022, 'stale checkpoint');
$shortWalBytes = substr($currentWalBytes, 0, 32 + (3 * (24 + $pageSize)));
$currentWal = SQLiteWal::parse($currentWalBytes, null, true);
$staleWal = SQLiteWal::parse($staleSaltBytes, null, true);

$restart = static fn (?int $reader = null): array => $currentWal->restartTruncateReaderCurrentSourceNext(
    $currentWalBytes,
    $databaseBytes,
    [1, 2, 3, 4],
    'restart',
    $reader
);
$truncate = static fn (?int $reader = null): array => $currentWal->restartTruncateReaderCurrentSourceNext(
    $currentWalBytes,
    $databaseBytes,
    [2, 3, 4],
    'truncate',
    $reader
);
$partial = static fn (): array => $currentWal->restartTruncateReaderCurrentSourceNext(
    $currentWalBytes,
    $databaseBytes,
    [2, 4],
    'restart',
    2
);

$cases = [
    'restart status ready' => [static fn (): mixed => $restart()['status'], 'ready'],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart source status' => [static fn (): mixed => $restart()['source_status'], 'current-source'],
    'restart reason' => [static fn (): mixed => $restart()['reason'], 'restart_checkpoint_can_reset_wal'],
    'restart wal action' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal'],
    'restart wal header only length' => [static fn (): mixed => $restart()['wal_bytes_length'], 32],
    'restart current reader end frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 4],
    'restart next reader end frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 0],
    'restart checkpoint sequence' => [static fn (): mixed => $restart()['checkpoint_sequence'], 86],
    'restart restarted checkpoint sequence' => [static fn (): mixed => $restart()['restarted_checkpoint_sequence'], 87],
    'restart current salt' => [static fn (): mixed => $restart()['current_salt'], [0x86000011, 0x86000022]],
    'restart next salt advances salt one' => [static fn (): mixed => $restart()['next_salt'], [0x86000012, 0x86000022]],
    'restart current sources' => [static fn (): mixed => $restart()['current_sources'], ['database', 'wal', 'wal', 'wal']],
    'restart next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'database', 'database', 'database']],
    'restart current frame indexes' => [static fn (): mixed => $restart()['current_frame_indexes'], [null, 3, 2, 4]],
    'restart next frame indexes' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, null, null, null]],
    'restart current errors' => [static fn (): mixed => $restart()['current_errors'], []],
    'restart next errors' => [static fn (): mixed => $restart()['next_errors'], []],
    'restart images match' => [static fn (): mixed => $restart()['images_match'], true],
    'restart uses checkpoint database' => [static fn (): mixed => $restart()['next_uses_checkpoint_database'], true],
    'restart uses restarted header' => [static fn (): mixed => $restart()['next_uses_restarted_header'], true],
    'restart source frame count' => [static fn (): mixed => $restart()['source_frame_count'], 4],
    'restart parsed frame count' => [static fn (): mixed => $restart()['parsed_frame_count'], 4],
    'restart checkpoint can reset' => [static fn (): mixed => $restart()['checkpoint']['can_reset'], true],
    'restart checkpoint can not truncate' => [static fn (): mixed => $restart()['checkpoint']['can_truncate'], false],
    'restart checkpointed frame count' => [static fn (): mixed => $restart()['checkpoint']['checkpointed_frame_count'], 3],
    'restart committable frame count' => [static fn (): mixed => $restart()['checkpoint']['total_committable_frame_count'], 3],
    'restart uncommitted frame count' => [static fn (): mixed => $restart()['checkpoint']['uncommitted_frame_count'], 0],
    'restart page one remains database' => [static fn (): mixed => str_contains($restart()['next_reader'][0]['image'], 'schema base'), true],
    'restart page two latest checkpointed' => [static fn (): mixed => str_contains($restart()['next_reader'][1]['image'], 'active_plugins latest'), true],
    'restart page three checkpointed' => [static fn (): mixed => str_contains($restart()['next_reader'][2]['image'], 'autoload index commit'), true],
    'restart page four checkpointed' => [static fn (): mixed => str_contains($restart()['next_reader'][3]['image'], 'cron commit'), true],
    'restart dependency includes sidecar' => [static fn (): mixed => in_array('durable-sidecar-write', $restart()['dependencies'], true), true],
    'restart dependency includes current source' => [static fn (): mixed => in_array('sqlite-wal-current-source-admission', $restart()['dependencies'], true), true],
    'restart dependency includes slice' => [static fn (): mixed => in_array('sqlite-wal-restart-truncate-reader-current-source-next86', $restart()['dependencies'], true), true],
    'truncate status ready' => [static fn (): mixed => $truncate()['status'], 'ready'],
    'truncate reason' => [static fn (): mixed => $truncate()['reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal'],
    'truncate wal bytes length' => [static fn (): mixed => $truncate()['wal_bytes_length'], 0],
    'truncate next reader end frame' => [static fn (): mixed => $truncate()['next_reader_end_frame'], 0],
    'truncate restart sequence null' => [static fn (): mixed => $truncate()['restarted_checkpoint_sequence'], null],
    'truncate next salt null' => [static fn (): mixed => $truncate()['next_salt'], null],
    'truncate current sources' => [static fn (): mixed => $truncate()['current_sources'], ['wal', 'wal', 'wal']],
    'truncate next sources' => [static fn (): mixed => $truncate()['next_sources'], ['database', 'database', 'database']],
    'truncate current frames' => [static fn (): mixed => $truncate()['current_frame_indexes'], [3, 2, 4]],
    'truncate next frames' => [static fn (): mixed => $truncate()['next_frame_indexes'], [null, null, null]],
    'truncate images match' => [static fn (): mixed => $truncate()['images_match'], true],
    'truncate uses checkpoint database' => [static fn (): mixed => $truncate()['next_uses_checkpoint_database'], true],
    'truncate no restarted header' => [static fn (): mixed => $truncate()['next_uses_restarted_header'], false],
    'truncate checkpoint can truncate' => [static fn (): mixed => $truncate()['checkpoint']['can_truncate'], true],
    'partial reader end frame' => [static fn (): mixed => $partial()['current_reader_end_frame'], 2],
    'partial current sources' => [static fn (): mixed => $partial()['current_sources'], ['wal', 'database']],
    'partial current frames' => [static fn (): mixed => $partial()['current_frame_indexes'], [1, null]],
    'partial next sources' => [static fn (): mixed => $partial()['next_sources'], ['database', 'database']],
    'partial images differ' => [static fn (): mixed => $partial()['images_match'], false],
    'partial page two old draft current' => [static fn (): mixed => str_contains($partial()['current_reader'][0]['image'], 'active_plugins draft'), true],
    'partial page two latest next' => [static fn (): mixed => str_contains($partial()['next_reader'][0]['image'], 'active_plugins latest'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal restart truncate reader current source next86 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal restart truncate reader current source next86 rejects stale salt bytes'] = static function (TestRunner $t) use ($currentWal, $staleSaltBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->restartTruncateReaderCurrentSourceNext($staleSaltBytes, $databaseBytes, [2], 'restart'));
};

$tests['wal restart truncate reader current source next86 rejects stale checkpoint bytes'] = static function (TestRunner $t) use ($currentWal, $staleCheckpointBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->restartTruncateReaderCurrentSourceNext($staleCheckpointBytes, $databaseBytes, [2], 'restart'));
};

$tests['wal restart truncate reader current source next86 rejects short frame count bytes'] = static function (TestRunner $t) use ($currentWal, $shortWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->restartTruncateReaderCurrentSourceNext($shortWalBytes, $databaseBytes, [2], 'restart'));
};

$tests['wal restart truncate reader current source next86 rejects stale parsed wal'] = static function (TestRunner $t) use ($staleWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $staleWal->restartTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [2], 'restart'));
};

$tests['wal restart truncate reader current source next86 rejects empty page list'] = static function (TestRunner $t) use ($currentWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->restartTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [], 'restart'));
};

$tests['wal restart truncate reader current source next86 rejects non integer page'] = static function (TestRunner $t) use ($currentWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->restartTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [2, '3'], 'restart'));
};

$tests['wal restart truncate reader current source next86 rejects unsupported mode'] = static function (TestRunner $t) use ($currentWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->restartTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [2], 'passive'));
};

$tests['wal restart truncate reader current source next86 rejects negative reader frame'] = static function (TestRunner $t) use ($currentWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->restartTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [2], 'restart', -1));
};

$tests['wal restart truncate reader current source next86 rejects reader beyond wal'] = static function (TestRunner $t) use ($currentWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->restartTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [2], 'restart', 5));
};

return $tests;
