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

$makeWal = static function (array $frames, int $checkpointSequence = 51) use ($pageSize): string {
    $salt1 = 0x51515151;
    $salt2 = 0x91919191;
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
    [1, 0, $page('frame 1 schema draft before release savepoint')],
    [2, 4, $page('frame 2 active_plugins committed before release savepoint')],
    [3, 0, $page('frame 3 plugin settings draft inside release savepoint')],
    [3, 4, $page('frame 4 plugin settings committed by release savepoint')],
    [4, 0, $page('frame 5 nested transient draft inside child savepoint')],
    [4, 4, $page('frame 6 nested transient committed by child release')],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
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

$restartReleased = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $databaseBytes,
    [1, 2, 3, 4],
    'restart'
);

$truncateReleased = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $databaseBytes,
    [2, 3, 4],
    'truncate'
);

$pinnedRestart = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $databaseBytes,
    [2, 3, 4],
    'restart',
    2
);

$passivePinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $databaseBytes,
    [2, 3, 4],
    'passive',
    2
);

$cases = [
    'restart status ready after release' => [static fn (): mixed => $restartReleased()['status'], 'ready'],
    'restart savepoint name' => [static fn (): mixed => $restartReleased()['savepoint'], 'plugin-settings'],
    'restart mode normalized' => [static fn (): mixed => $restartReleased()['mode'], 'restart'],
    'restart before reader uses full wal' => [static fn (): mixed => $restartReleased()['before_reader_end_frame'], 6],
    'restart after release reader uses full wal' => [static fn (): mixed => $restartReleased()['after_release_reader_end_frame'], 6],
    'restart next reader resets to header only' => [static fn (): mixed => $restartReleased()['next_reader_end_frame'], 0],
    'restart action restarts wal' => [static fn (): mixed => $restartReleased()['wal_action'], 'restart_wal'],
    'restart checkpoint not busy' => [static fn (): mixed => $restartReleased()['checkpoint_busy'], false],
    'restart checkpoint reason' => [static fn (): mixed => $restartReleased()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'restart release frame names' => [static fn (): mixed => $restartReleased()['released_frame_names'], ['plugin-settings', 'transient-refresh']],
    'restart release merged pages' => [static fn (): mixed => $restartReleased()['merged_page_numbers'], [3, 4]],
    'restart release depth remains outer transaction' => [static fn (): mixed => $restartReleased()['release']['result_depth'], 1],
    'restart release target is not transaction' => [static fn (): mixed => $restartReleased()['release']['target_is_transaction'], false],
    'restart transaction remains active after release' => [static fn (): mixed => $restartReleased()['release']['transaction_active_after'], true],
    'restart before sources are wal' => [static fn (): mixed => $restartReleased()['before_reader_sources'], ['wal', 'wal', 'wal', 'wal']],
    'restart after release sources are wal' => [static fn (): mixed => $restartReleased()['after_release_reader_sources'], ['wal', 'wal', 'wal', 'wal']],
    'restart next sources are checkpoint database' => [static fn (): mixed => $restartReleased()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'restart before frames' => [static fn (): mixed => $restartReleased()['before_reader_frame_indexes'], [1, 2, 4, 6]],
    'restart after release frames' => [static fn (): mixed => $restartReleased()['after_release_reader_frame_indexes'], [1, 2, 4, 6]],
    'restart next frames' => [static fn (): mixed => $restartReleased()['next_reader_frame_indexes'], [null, null, null, null]],
    'restart page three after release sees committed plugin settings' => [static fn (): mixed => str_contains($restartReleased()['after_release_reader'][2]['image'], 'committed by release'), true],
    'restart next page three checkpointed to database' => [static fn (): mixed => str_contains($restartReleased()['next_reader'][2]['image'], 'committed by release'), true],
    'restart page four after release sees child release' => [static fn (): mixed => str_contains($restartReleased()['after_release_reader'][3]['image'], 'child release'), true],
    'restart next page four checkpointed to database' => [static fn (): mixed => str_contains($restartReleased()['next_reader'][3]['image'], 'child release'), true],
    'restart before and release images match' => [static fn (): mixed => $restartReleased()['before_to_release_images_match'], true],
    'restart release and next images match' => [static fn (): mixed => $restartReleased()['release_to_next_images_match'], true],
    'restart yield count' => [static fn (): mixed => $restartReleased()['yield_count'], 12],
    'restart dependency release checkpoint marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-release-checkpoint-current-next', $restartReleased()['dependencies'], true), true],
    'restart dependency application marker' => [static fn (): mixed => in_array('application-import-release-savepoint-current-next', $restartReleased()['dependencies'], true), true],
    'restart dependency checkpoint marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $restartReleased()['dependencies'], true), true],
    'truncate status ready' => [static fn (): mixed => $truncateReleased()['status'], 'ready'],
    'truncate action truncates wal' => [static fn (): mixed => $truncateReleased()['wal_action'], 'truncate_wal'],
    'truncate reason complete' => [static fn (): mixed => $truncateReleased()['checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'truncate next frame is database only' => [static fn (): mixed => $truncateReleased()['next_reader_end_frame'], 0],
    'truncate before sources' => [static fn (): mixed => $truncateReleased()['before_reader_sources'], ['wal', 'wal', 'wal']],
    'truncate next sources' => [static fn (): mixed => $truncateReleased()['next_reader_sources'], ['database', 'database', 'database']],
    'truncate next frames' => [static fn (): mixed => $truncateReleased()['next_reader_frame_indexes'], [null, null, null]],
    'truncate released frame names include nested' => [static fn (): mixed => $truncateReleased()['released_frame_names'], ['plugin-settings', 'transient-refresh']],
    'truncate release next images match' => [static fn (): mixed => $truncateReleased()['release_to_next_images_match'], true],
    'pinned restart status busy' => [static fn (): mixed => $pinnedRestart()['status'], 'busy'],
    'pinned restart action preserves wal' => [static fn (): mixed => $pinnedRestart()['wal_action'], 'preserve_wal'],
    'pinned restart reason' => [static fn (): mixed => $pinnedRestart()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'pinned restart before frame is old reader' => [static fn (): mixed => $pinnedRestart()['before_reader_end_frame'], 2],
    'pinned restart after release uses full wal' => [static fn (): mixed => $pinnedRestart()['after_release_reader_end_frame'], 6],
    'pinned restart next reader keeps full wal' => [static fn (): mixed => $pinnedRestart()['next_reader_end_frame'], 6],
    'pinned restart before sources show old pages only' => [static fn (): mixed => $pinnedRestart()['before_reader_sources'], ['wal', 'database', 'database']],
    'pinned restart after release sources show all wal' => [static fn (): mixed => $pinnedRestart()['after_release_reader_sources'], ['wal', 'wal', 'wal']],
    'pinned restart next sources preserve wal' => [static fn (): mixed => $pinnedRestart()['next_reader_sources'], ['wal', 'wal', 'wal']],
    'pinned restart before frame indexes' => [static fn (): mixed => $pinnedRestart()['before_reader_frame_indexes'], [2, null, null]],
    'pinned restart after release frame indexes' => [static fn (): mixed => $pinnedRestart()['after_release_reader_frame_indexes'], [2, 4, 6]],
    'pinned restart next frame indexes' => [static fn (): mixed => $pinnedRestart()['next_reader_frame_indexes'], [2, 4, 6]],
    'pinned restart before release differs' => [static fn (): mixed => $pinnedRestart()['before_to_release_images_match'], false],
    'pinned restart release next matches' => [static fn (): mixed => $pinnedRestart()['release_to_next_images_match'], true],
    'passive pinned status ready' => [static fn (): mixed => $passivePinned()['status'], 'ready'],
    'passive pinned action preserves wal' => [static fn (): mixed => $passivePinned()['wal_action'], 'preserve_wal'],
    'passive pinned reason' => [static fn (): mixed => $passivePinned()['checkpoint_reason'], 'reader_limited_passive_checkpoint'],
    'passive pinned next reader uses full wal' => [static fn (): mixed => $passivePinned()['next_reader_end_frame'], 6],
    'passive pinned before sources' => [static fn (): mixed => $passivePinned()['before_reader_sources'], ['wal', 'database', 'database']],
    'passive pinned next sources' => [static fn (): mixed => $passivePinned()['next_reader_sources'], ['wal', 'wal', 'wal']],
    'passive pinned before release differs' => [static fn (): mixed => $passivePinned()['before_to_release_images_match'], false],
    'passive pinned release next matches' => [static fn (): mixed => $passivePinned()['release_to_next_images_match'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint savepoint release current next51 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint savepoint release current next51 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext($makeStack(), 'plugin-settings', $wal, $databaseBytes, []));
};

$tests['wal checkpoint savepoint release current next51 rejects empty database'] = static function (TestRunner $t) use ($makeStack, $wal): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext($makeStack(), 'plugin-settings', $wal, '', [2]));
};

$tests['wal checkpoint savepoint release current next51 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext($makeStack(), 'plugin-settings', $wal, $databaseBytes, ['2']));
};

$tests['wal checkpoint savepoint release current next51 rejects missing savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext($makeStack(), 'missing', $wal, $databaseBytes, [2]));
};

$tests['wal checkpoint savepoint release current next51 rejects unsupported checkpoint mode'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext($makeStack(), 'plugin-settings', $wal, $databaseBytes, [2], 'invalid'));
};

$tests['wal checkpoint savepoint release current next51 rejects negative reader frame'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext($makeStack(), 'plugin-settings', $wal, $databaseBytes, [2], 'restart', -1));
};

return $tests;
