<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp schema base current source next79')
    . $page('wp active_plugins base current source next79')
    . $page('wp autoload index base current source next79');

$makeWalBytes = static function (int $checkpoint, int $salt1, int $salt2, string $tag) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $append(2, 0, $page("{$tag} retained active_plugins draft"));
    $append(2, 2, $page("{$tag} retained active_plugins commit"));
    $append(3, 0, $page("{$tag} rolled back autoload draft"));
    $append(3, 3, $page("{$tag} rolled back autoload commit"));

    return $bytes;
};

$currentWalBytes = $makeWalBytes(79, 0x79797979, 0x19191919, 'current');
$sameFrameStaleSaltBytes = $makeWalBytes(79, 0x7979797a, 0x19191919, 'stale salt');
$sameFrameStaleCheckpointBytes = $makeWalBytes(80, 0x79797979, 0x19191919, 'stale checkpoint');
$shorterWalBytes = substr($currentWalBytes, 0, 32 + (3 * (24 + $pageSize)));
$currentWal = SQLiteWal::parse($currentWalBytes, null, true);
$staleWal = SQLiteWal::parse($sameFrameStaleSaltBytes, null, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp_import');
    $stack->recordWalFrameWrite(1, 2);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin_settings');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 3, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart'): array => SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
    $makeStack(),
    'plugin_settings',
    $currentWal,
    $currentWalBytes,
    $databaseBytes,
    $mode
);

$boundary = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo(
    $makeStack(),
    'plugin_settings',
    $currentWal,
    $currentWalBytes,
    $databaseBytes,
    [2],
    'truncate'
);

$restart = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerRestartCurrentNextAfterRollbackTo(
    $makeStack(),
    'plugin_settings',
    $currentWal,
    $currentWalBytes,
    $databaseBytes,
    [2],
    'restart'
);

$cases = [
    'safe source status ready' => [static fn (): mixed => $plan()['status'], 'ready'],
    'safe source mode restart' => [static fn (): mixed => $plan()['mode'], 'restart'],
    'safe source retains savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'plugin_settings'],
    'safe source original frame count' => [static fn (): mixed => $plan()['original_frame_count'], 4],
    'safe source retained frame count' => [static fn (): mixed => $plan()['retained_frame_count'], 2],
    'safe source discarded frame count' => [static fn (): mixed => $plan()['discarded_frame_count'], 2],
    'safe source truncate bytes' => [static fn (): mixed => $plan()['truncate_to_bytes'], 32 + (2 * (24 + $pageSize))],
    'safe source current wal bytes length' => [static fn (): mixed => $plan()['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'safe source checkpoint can run' => [static fn (): mixed => $plan()['can_checkpoint'], true],
    'safe source can reset' => [static fn (): mixed => $plan()['can_reset'], true],
    'safe source can truncate' => [static fn (): mixed => $plan('truncate')['can_truncate'], true],
    'safe source checkpoint reason' => [static fn (): mixed => $plan()['reason'], 'restart_checkpoint_can_reset_wal'],
    'safe source durable action restart' => [static fn (): mixed => $plan()['current_durable']['wal_action'], 'restart_wal'],
    'safe source truncate durable action' => [static fn (): mixed => $plan('truncate')['current_durable']['wal_action'], 'truncate_wal'],
    'safe source durable database has retained commit' => [static fn (): mixed => str_contains($plan()['current_durable']['database_bytes'], 'current retained active_plugins commit'), true],
    'safe source durable database omits rollback draft' => [static fn (): mixed => str_contains($plan()['current_durable']['database_bytes'], 'rolled back autoload draft'), false],
    'safe source durable database omits rollback commit' => [static fn (): mixed => str_contains($plan()['current_durable']['database_bytes'], 'rolled back autoload commit'), false],
    'safe source current wal keeps header salt one' => [static fn (): mixed => SQLiteWal::parse($plan()['current_wal_bytes'], null, true)->header->salt1, 0x79797979],
    'safe source current wal keeps header salt two' => [static fn (): mixed => SQLiteWal::parse($plan()['current_wal_bytes'], null, true)->header->salt2, 0x19191919],
    'safe source current wal keeps checkpoint sequence' => [static fn (): mixed => SQLiteWal::parse($plan()['current_wal_bytes'], null, true)->header->checkpointSequence, 79],
    'safe source current wal frame count' => [static fn (): mixed => SQLiteWal::parse($plan()['current_wal_bytes'], null, true)->frameCount(), 2],
    'safe source current wal last commit frame' => [static fn (): mixed => SQLiteWal::parse($plan()['current_wal_bytes'], null, true)->lastCommitFrame()?->index, 2],
    'safe source dependency includes current prefix' => [static fn (): mixed => in_array('sqlite-savepoint-wal-current-prefix', $plan()['dependencies'], true), true],
    'safe source dependency includes checkpoint current' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $plan()['dependencies'], true), true],
    'boundary status ready' => [static fn (): mixed => $boundary()['status'], 'ready'],
    'boundary retained frame count' => [static fn (): mixed => $boundary()['retained_frame_count'], 2],
    'boundary discarded frame count' => [static fn (): mixed => $boundary()['discarded_frame_count'], 2],
    'boundary current reader sources' => [static fn (): mixed => $boundary()['current_reader_sources'], ['wal']],
    'boundary next reader sources' => [static fn (): mixed => $boundary()['next_reader_sources'], ['database']],
    'boundary current reader frame indexes' => [static fn (): mixed => $boundary()['current_reader_frame_indexes'], [2]],
    'boundary next reader frame indexes' => [static fn (): mixed => $boundary()['next_reader_frame_indexes'], [null]],
    'boundary current reader sees retained commit' => [static fn (): mixed => str_contains($boundary()['current_reader'][0]['image'], 'current retained active_plugins commit'), true],
    'boundary next reader sees checkpointed retained commit' => [static fn (): mixed => str_contains($boundary()['next_reader'][0]['image'], 'current retained active_plugins commit'), true],
    'boundary next reader excludes rolled back commit' => [static fn (): mixed => str_contains(implode('', $boundary()['next_reader_images']), 'rolled back autoload commit'), false],
    'boundary images match' => [static fn (): mixed => $boundary()['images_match'], true],
    'boundary next uses checkpoint database' => [static fn (): mixed => $boundary()['next_reader_uses_checkpoint_database'], true],
    'restart status ready' => [static fn (): mixed => $restart()['status'], 'ready'],
    'restart current reader end frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart next reader end frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 0],
    'restart wal action' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal'],
    'restart restarted checkpoint sequence' => [static fn (): mixed => $restart()['restarted_checkpoint_sequence'], 80],
    'restart restarted salt one advances' => [static fn (): mixed => $restart()['restarted_salt1'], 0x7979797a],
    'restart restarted salt two preserved' => [static fn (): mixed => $restart()['restarted_salt2'], 0x19191919],
    'restart next uses restarted header' => [static fn (): mixed => $restart()['next_reader_uses_restarted_header'], true],
    'restart current keeps retained wal' => [static fn (): mixed => $restart()['current_reader_kept_retained_wal'], true],
    'restart next uses checkpoint database' => [static fn (): mixed => $restart()['next_reader_uses_checkpoint_database'], true],
    'restart images match' => [static fn (): mixed => $restart()['images_match'], true],
    'restart dependencies include reader restart' => [static fn (): mixed => in_array('sqlite-wal-savepoint-reader-restart-current-next', $restart()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal savepoint checkpoint current source next79 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal savepoint checkpoint current source next79 rejects stale salt bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal, $sameFrameStaleSaltBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::afterRollbackTo($makeStack(), 'plugin_settings', $currentWal, $sameFrameStaleSaltBytes, $databaseBytes, 'restart'));
};

$tests['wal savepoint checkpoint current source next79 rejects stale checkpoint bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal, $sameFrameStaleCheckpointBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::afterRollbackTo($makeStack(), 'plugin_settings', $currentWal, $sameFrameStaleCheckpointBytes, $databaseBytes, 'restart'));
};

$tests['wal savepoint checkpoint current source next79 rejects shorter current bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal, $shorterWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::afterRollbackTo($makeStack(), 'plugin_settings', $currentWal, $shorterWalBytes, $databaseBytes, 'restart'));
};

$tests['wal savepoint checkpoint current source next79 rejects stale parsed wal with current bytes'] = static function (TestRunner $t) use ($makeStack, $staleWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::afterRollbackTo($makeStack(), 'plugin_settings', $staleWal, $currentWalBytes, $databaseBytes, 'restart'));
};

return $tests;
