<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db page 1 wp_options schema base')
    . $page('db page 2 active_plugins base')
    . $page('db page 3 autoload index base')
    . $page('db page 4 transient cache base');

$makeWal = static function (array $frames, int $checkpointSequence = 48) use ($pageSize): string {
    $salt1 = 0x48484848;
    $salt2 = 0x98989898;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
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
    [1, 0, $page('frame 1 schema draft before savepoint')],
    [2, 4, $page('frame 2 active plugins committed before savepoint')],
    [3, 0, $page('frame 3 autoload draft inside plugin savepoint')],
    [2, 4, $page('frame 4 active plugins rolled back savepoint commit')],
    [4, 0, $page('frame 5 transient nested draft')],
    [4, 4, $page('frame 6 transient nested commit')],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 2, true);
    $stack->savepoint('transient-refresh');
    $stack->recordWalFrameWrite(5, 4);
    $stack->recordWalFrameWrite(6, 4, true);

    return $stack;
};

$truncateYield = static fn (): array => SQLiteWalSavepointCheckpointPlan::yieldReaderSavepointCurrentNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4],
    'truncate'
);

$restartPinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::yieldReaderSavepointCurrentNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [2, 3, 4],
    'restart',
    1
);

$passivePinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::yieldReaderSavepointCurrentNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [2, 3],
    'passive',
    1
);

$cases = [
    'truncate status busy with current reader at retained frame' => [static fn (): mixed => $truncateYield()['status'], 'busy'],
    'truncate mode normalized' => [static fn (): mixed => $truncateYield()['mode'], 'truncate'],
    'truncate savepoint carried' => [static fn (): mixed => $truncateYield()['savepoint'], 'plugin-settings'],
    'truncate original reader sees full wal' => [static fn (): mixed => $truncateYield()['original_reader_end_frame'], 6],
    'truncate current reader clamps to retained prefix' => [static fn (): mixed => $truncateYield()['current_reader_end_frame'], 2],
    'truncate next reader keeps retained wal while reset is blocked' => [static fn (): mixed => $truncateYield()['next_reader_end_frame'], 2],
    'truncate retained frame count' => [static fn (): mixed => $truncateYield()['retained_frame_count'], 2],
    'truncate discarded frame count' => [static fn (): mixed => $truncateYield()['discarded_frame_count'], 4],
    'truncate rolled back frame indexes' => [static fn (): mixed => $truncateYield()['rolled_back_frame_indexes'], [3, 4, 5, 6]],
    'truncate rolled back page numbers' => [static fn (): mixed => $truncateYield()['rolled_back_page_numbers'], [2, 3, 4]],
    'truncate wal action preserves wal while reader is active' => [static fn (): mixed => $truncateYield()['wal_action'], 'preserve_wal'],
    'truncate checkpoint busy true' => [static fn (): mixed => $truncateYield()['checkpoint_busy'], true],
    'truncate checkpoint reason' => [static fn (): mixed => $truncateYield()['checkpoint_reason'], 'reader_blocks_wal_reset'],
    'truncate yield count' => [static fn (): mixed => $truncateYield()['yield_count'], 12],
    'truncate before sources' => [static fn (): mixed => $truncateYield()['before_reader_sources'], ['wal', 'wal', 'wal', 'wal']],
    'truncate current sources' => [static fn (): mixed => $truncateYield()['current_reader_sources'], ['wal', 'wal', 'database', 'database']],
    'truncate next sources' => [static fn (): mixed => $truncateYield()['next_reader_sources'], ['wal', 'wal', 'database', 'database']],
    'truncate before frame indexes' => [static fn (): mixed => $truncateYield()['before_reader_frame_indexes'], [1, 4, 3, 6]],
    'truncate current frame indexes' => [static fn (): mixed => $truncateYield()['current_reader_frame_indexes'], [1, 2, null, null]],
    'truncate next frame indexes' => [static fn (): mixed => $truncateYield()['next_reader_frame_indexes'], [1, 2, null, null]],
    'truncate before page two sees rolled back commit' => [static fn (): mixed => str_contains($truncateYield()['before_reader'][1]['image'], 'rolled back'), true],
    'truncate current page two sees retained commit' => [static fn (): mixed => str_contains($truncateYield()['current_reader'][1]['image'], 'committed before'), true],
    'truncate next page two sees checkpointed retained commit' => [static fn (): mixed => str_contains($truncateYield()['next_reader'][1]['image'], 'committed before'), true],
    'truncate before page four sees nested commit' => [static fn (): mixed => str_contains($truncateYield()['before_reader'][3]['image'], 'nested commit'), true],
    'truncate current page four returns base image' => [static fn (): mixed => str_contains($truncateYield()['current_reader'][3]['image'], 'transient cache base'), true],
    'truncate next page four remains base image' => [static fn (): mixed => str_contains($truncateYield()['next_reader'][3]['image'], 'transient cache base'), true],
    'truncate before to current differs' => [static fn (): mixed => $truncateYield()['before_to_current_images_match'], false],
    'truncate current to next matches' => [static fn (): mixed => $truncateYield()['current_to_next_images_match'], true],
    'truncate stage names' => [static fn (): mixed => array_column($truncateYield()['stages'], 'stage'), ['before_rollback', 'after_rollback', 'after_checkpoint']],
    'truncate stage readers' => [static fn (): mixed => array_column($truncateYield()['stages'], 'reader'), ['current_reader_original_savepoint', 'current_reader_after_rollback_to', 'next_reader_after_checkpoint']],
    'truncate stage end frames' => [static fn (): mixed => array_column($truncateYield()['stages'], 'end_frame'), [6, 2, 2]],
    'truncate stage wal lengths' => [static fn (): mixed => array_column($truncateYield()['stages'], 'wal_bytes_length'), [3248, 1104, 1104]],
    'truncate stage wal actions' => [static fn (): mixed => array_column($truncateYield()['stages'], 'wal_action'), [null, 'truncate_to_savepoint_prefix', 'preserve_wal']],
    'truncate stage page numbers' => [static fn (): mixed => $truncateYield()['stages'][2]['page_numbers'], [1, 2, 3, 4]],
    'truncate dependency yield marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-yield-current-next', $truncateYield()['dependencies'], true), true],
    'truncate dependency application marker' => [static fn (): mixed => in_array('application-import-yield-savepoint-current-next', $truncateYield()['dependencies'], true), true],
    'truncate dependency checkpoint marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $truncateYield()['dependencies'], true), true],
    'restart pinned status busy' => [static fn (): mixed => $restartPinned()['status'], 'busy'],
    'restart pinned reason' => [static fn (): mixed => $restartPinned()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'restart pinned action preserves wal' => [static fn (): mixed => $restartPinned()['wal_action'], 'preserve_wal'],
    'restart pinned original reader frame' => [static fn (): mixed => $restartPinned()['original_reader_end_frame'], 1],
    'restart pinned current reader frame' => [static fn (): mixed => $restartPinned()['current_reader_end_frame'], 1],
    'restart pinned next reader frame' => [static fn (): mixed => $restartPinned()['next_reader_end_frame'], 2],
    'restart pinned before sources' => [static fn (): mixed => $restartPinned()['before_reader_sources'], ['database', 'database', 'database']],
    'restart pinned current sources' => [static fn (): mixed => $restartPinned()['current_reader_sources'], ['database', 'database', 'database']],
    'restart pinned next sources' => [static fn (): mixed => $restartPinned()['next_reader_sources'], ['wal', 'database', 'database']],
    'restart pinned before current match' => [static fn (): mixed => $restartPinned()['before_to_current_images_match'], true],
    'restart pinned current next differ' => [static fn (): mixed => $restartPinned()['current_to_next_images_match'], false],
    'restart pinned yield count' => [static fn (): mixed => $restartPinned()['yield_count'], 9],
    'restart pinned stage end frames' => [static fn (): mixed => array_column($restartPinned()['stages'], 'end_frame'), [1, 1, 2]],
    'passive pinned status ready' => [static fn (): mixed => $passivePinned()['status'], 'ready'],
    'passive pinned reason' => [static fn (): mixed => $passivePinned()['checkpoint_reason'], 'reader_limited_passive_checkpoint'],
    'passive pinned action preserves wal' => [static fn (): mixed => $passivePinned()['wal_action'], 'preserve_wal'],
    'passive pinned next sources' => [static fn (): mixed => $passivePinned()['next_reader_sources'], ['wal', 'database']],
    'passive pinned current to next differs' => [static fn (): mixed => $passivePinned()['current_to_next_images_match'], false],
    'passive pinned rolled back frames still reported' => [static fn (): mixed => $passivePinned()['rolled_back_frame_indexes'], [3, 4, 5, 6]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint reader savepoint yield current next48 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint reader savepoint yield current next48 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::yieldReaderSavepointCurrentNext($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, []));
};

$tests['wal checkpoint reader savepoint yield current next48 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::yieldReaderSavepointCurrentNext($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, ['2']));
};

$tests['wal checkpoint reader savepoint yield current next48 rejects negative reader frame'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::yieldReaderSavepointCurrentNext($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, [2], 'truncate', -1));
};

$tests['wal checkpoint reader savepoint yield current next48 rejects missing savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::yieldReaderSavepointCurrentNext($makeStack(), 'missing', $wal, $walBytes, $databaseBytes, [2]));
};

$tests['wal checkpoint reader savepoint yield current next48 rejects unsupported checkpoint mode'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::yieldReaderSavepointCurrentNext($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, [2], 'invalid'));
};

return $tests;
