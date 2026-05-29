<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base release current source next84')
    . $page('wp active_plugins base release current source next84')
    . $page('wp plugin settings base release current source next84')
    . $page('wp transient base release current source next84');

$makeWalBytes = static function (int $checkpoint, int $salt1, int $salt2, string $tag) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $append(1, 0, $page("{$tag} schema retained before release84"));
    $append(2, 4, $page("{$tag} active_plugins retained commit84"));
    $append(3, 0, $page("{$tag} plugin settings draft release84"));
    $append(3, 4, $page("{$tag} plugin settings committed release84"));
    $append(4, 0, $page("{$tag} transient nested draft release84"));
    $append(4, 4, $page("{$tag} transient nested committed release84"));

    return $bytes;
};

$currentWalBytes = $makeWalBytes(84, 0x84848484, 0x24242424, 'current');
$sameFrameStaleSaltBytes = $makeWalBytes(84, 0x84848485, 0x24242424, 'stale salt');
$sameFrameStaleCheckpointBytes = $makeWalBytes(85, 0x84848484, 0x24242424, 'stale checkpoint');
$shorterWalBytes = substr($currentWalBytes, 0, 32 + (5 * (24 + $pageSize)));
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$staleWal = SQLiteWal::parse($sameFrameStaleSaltBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wordpress-import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 3, true);
    $stack->savepoint('transient-refresh');
    $stack->recordWalFrameWrite(5, 4);
    $stack->recordWalFrameWrite(6, 4, true);

    return $stack;
};

$restart = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentSourceNext(
    $makeStack(),
    'plugin-settings',
    $currentWal,
    $currentWalBytes,
    $databaseBytes,
    [1, 2, 3, 4],
    'restart'
);

$truncate = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentSourceNext(
    $makeStack(),
    'plugin-settings',
    $currentWal,
    $currentWalBytes,
    $databaseBytes,
    [2, 3, 4],
    'truncate'
);

$pinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentSourceNext(
    $makeStack(),
    'plugin-settings',
    $currentWal,
    $currentWalBytes,
    $databaseBytes,
    [2, 3, 4],
    'restart',
    2
);

$cases = [
    'restart status ready' => [static fn (): mixed => $restart()['status'], 'ready'],
    'restart savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings'],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'restart wal bytes length' => [static fn (): mixed => $restart()['current_wal_bytes_length'], strlen($currentWalBytes)],
    'restart wal frame count' => [static fn (): mixed => $restart()['current_wal_frame_count'], 6],
    'restart checkpoint sequence' => [static fn (): mixed => $restart()['current_wal_checkpoint_sequence'], 84],
    'restart salt one' => [static fn (): mixed => $restart()['current_wal_salt1'], 0x84848484],
    'restart salt two' => [static fn (): mixed => $restart()['current_wal_salt2'], 0x24242424],
    'restart release frame names' => [static fn (): mixed => $restart()['released_frame_names'], ['plugin-settings', 'transient-refresh']],
    'restart merged pages' => [static fn (): mixed => $restart()['merged_page_numbers'], [3, 4]],
    'restart release depth' => [static fn (): mixed => $restart()['release']['result_depth'], 1],
    'restart transaction remains active' => [static fn (): mixed => $restart()['release']['transaction_active_after'], true],
    'restart before reader end frame' => [static fn (): mixed => $restart()['before_reader_end_frame'], 6],
    'restart after release reader end frame' => [static fn (): mixed => $restart()['after_release_reader_end_frame'], 6],
    'restart next reader end frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 0],
    'restart wal action' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal'],
    'restart checkpoint not busy' => [static fn (): mixed => $restart()['checkpoint_busy'], false],
    'restart checkpoint reason' => [static fn (): mixed => $restart()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'restart before sources' => [static fn (): mixed => $restart()['before_reader_sources'], ['wal', 'wal', 'wal', 'wal']],
    'restart after release sources' => [static fn (): mixed => $restart()['after_release_reader_sources'], ['wal', 'wal', 'wal', 'wal']],
    'restart next sources' => [static fn (): mixed => $restart()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'restart before frames' => [static fn (): mixed => $restart()['before_reader_frame_indexes'], [1, 2, 4, 6]],
    'restart after release frames' => [static fn (): mixed => $restart()['after_release_reader_frame_indexes'], [1, 2, 4, 6]],
    'restart next frames' => [static fn (): mixed => $restart()['next_reader_frame_indexes'], [null, null, null, null]],
    'restart page three after release has committed settings' => [static fn (): mixed => str_contains($restart()['after_release_reader'][2]['image'], 'plugin settings committed'), true],
    'restart page four after release has nested commit' => [static fn (): mixed => str_contains($restart()['after_release_reader'][3]['image'], 'transient nested committed'), true],
    'restart next page three checkpointed' => [static fn (): mixed => str_contains($restart()['next_reader'][2]['image'], 'plugin settings committed'), true],
    'restart next page four checkpointed' => [static fn (): mixed => str_contains($restart()['next_reader'][3]['image'], 'transient nested committed'), true],
    'restart before release images match' => [static fn (): mixed => $restart()['before_to_release_images_match'], true],
    'restart release next images match' => [static fn (): mixed => $restart()['release_to_next_images_match'], true],
    'restart yield count' => [static fn (): mixed => $restart()['yield_count'], 12],
    'restart dependency current source' => [static fn (): mixed => in_array('sqlite-wal-savepoint-release-checkpoint-current-source-next84', $restart()['dependencies'], true), true],
    'restart dependency release checkpoint' => [static fn (): mixed => in_array('sqlite-wal-savepoint-release-checkpoint-current-next', $restart()['dependencies'], true), true],
    'restart dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $restart()['dependencies'], true), true],
    'truncate status ready' => [static fn (): mixed => $truncate()['status'], 'ready'],
    'truncate action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal'],
    'truncate reason' => [static fn (): mixed => $truncate()['checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'truncate next reader frame' => [static fn (): mixed => $truncate()['next_reader_end_frame'], 0],
    'truncate before sources' => [static fn (): mixed => $truncate()['before_reader_sources'], ['wal', 'wal', 'wal']],
    'truncate next sources' => [static fn (): mixed => $truncate()['next_reader_sources'], ['database', 'database', 'database']],
    'truncate images match' => [static fn (): mixed => $truncate()['release_to_next_images_match'], true],
    'pinned status busy' => [static fn (): mixed => $pinned()['status'], 'busy'],
    'pinned action preserves wal' => [static fn (): mixed => $pinned()['wal_action'], 'preserve_wal'],
    'pinned reason' => [static fn (): mixed => $pinned()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'pinned before frame' => [static fn (): mixed => $pinned()['before_reader_end_frame'], 2],
    'pinned next frame keeps wal' => [static fn (): mixed => $pinned()['next_reader_end_frame'], 6],
    'pinned before sources' => [static fn (): mixed => $pinned()['before_reader_sources'], ['wal', 'database', 'database']],
    'pinned after release sources' => [static fn (): mixed => $pinned()['after_release_reader_sources'], ['wal', 'wal', 'wal']],
    'pinned next sources' => [static fn (): mixed => $pinned()['next_reader_sources'], ['wal', 'wal', 'wal']],
    'pinned before frames' => [static fn (): mixed => $pinned()['before_reader_frame_indexes'], [2, null, null]],
    'pinned after frames' => [static fn (): mixed => $pinned()['after_release_reader_frame_indexes'], [2, 4, 6]],
    'pinned next frames' => [static fn (): mixed => $pinned()['next_reader_frame_indexes'], [2, 4, 6]],
    'pinned before release differs' => [static fn (): mixed => $pinned()['before_to_release_images_match'], false],
    'pinned release next matches' => [static fn (): mixed => $pinned()['release_to_next_images_match'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal savepoint release checkpoint current source next84 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal savepoint release checkpoint current source next84 rejects stale salt bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal, $sameFrameStaleSaltBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentSourceNext($makeStack(), 'plugin-settings', $currentWal, $sameFrameStaleSaltBytes, $databaseBytes, [2]));
};

$tests['wal savepoint release checkpoint current source next84 rejects stale checkpoint bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal, $sameFrameStaleCheckpointBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentSourceNext($makeStack(), 'plugin-settings', $currentWal, $sameFrameStaleCheckpointBytes, $databaseBytes, [2]));
};

$tests['wal savepoint release checkpoint current source next84 rejects shorter current bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal, $shorterWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentSourceNext($makeStack(), 'plugin-settings', $currentWal, $shorterWalBytes, $databaseBytes, [2]));
};

$tests['wal savepoint release checkpoint current source next84 rejects stale parsed wal'] = static function (TestRunner $t) use ($makeStack, $staleWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentSourceNext($makeStack(), 'plugin-settings', $staleWal, $currentWalBytes, $databaseBytes, [2]));
};

return $tests;
