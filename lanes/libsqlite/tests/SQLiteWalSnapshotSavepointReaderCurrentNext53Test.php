<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db schema before release')
    . $page('db option before release')
    . $page('db autoload before release');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x53535353;
    $salt2 = 0x53535354;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 53, $salt1, $salt2);
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
    [1, 0, 'frame1 schema retained'],
    [2, 3, 'frame2 option before plugin'],
    [2, 0, 'frame3 plugin option draft'],
    [3, 3, 'frame4 plugin index commit'],
    [2, 3, 'frame5 nested transient commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('db schema before release'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordPageImageWrite(2, $page('db option before release'));
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings');
    $stack->recordWalFrameWrite(3, 2);
    $stack->recordPageImageWrite(3, $page('db autoload before release'));
    $stack->recordWalFrameWrite(4, 3, true);
    $stack->savepoint('nested-transient');
    $stack->recordWalFrameWrite(5, 2, true);

    return $stack;
};

$releasePinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease(
    $makeStack(),
    'plugin-settings',
    $wal,
    $databaseBytes,
    [1, 2, 3],
    [2, null],
    'restart'
);

$releaseLatest = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease(
    $makeStack(),
    'plugin-settings',
    $wal,
    $databaseBytes,
    [1, 2, 3],
    [5, null],
    'truncate'
);

$releasePassivePinned = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease(
    $makeStack(),
    'plugin-settings',
    $wal,
    $databaseBytes,
    [2, 3],
    [2],
    'passive'
);

$releaseNested = static fn (): array => SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease(
    $makeStack(),
    'nested-transient',
    $wal,
    $databaseBytes,
    [2, 3],
    [4],
    'restart'
);

$cases = [
    'pinned status is busy' => [static fn (): mixed => $releasePinned()['status'], 'busy'],
    'pinned checkpoint is restart' => [static fn (): mixed => $releasePinned()['mode'], 'restart'],
    'pinned reason blocks completion' => [static fn (): mixed => $releasePinned()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'pinned wal action preserves wal' => [static fn (): mixed => $releasePinned()['wal_action'], 'preserve_wal'],
    'pinned current reader end frame' => [static fn (): mixed => $releasePinned()['current_reader_end_frame'], 2],
    'pinned next reader end frame' => [static fn (): mixed => $releasePinned()['next_reader_end_frame'], 5],
    'pinned release names include nested' => [static fn (): mixed => $releasePinned()['released_frame_names'], ['plugin-settings', 'nested-transient']],
    'pinned merged pages are sorted' => [static fn (): mixed => $releasePinned()['merged_page_numbers'], [2, 3]],
    'pinned target is not transaction' => [static fn (): mixed => $releasePinned()['target_is_transaction'], false],
    'pinned result depth keeps outer transaction' => [static fn (): mixed => $releasePinned()['result_depth'], 1],
    'pinned current sources' => [static fn (): mixed => $releasePinned()['current_reader_sources'], ['wal', 'wal', 'database']],
    'pinned next sources' => [static fn (): mixed => $releasePinned()['next_reader_sources'], ['wal', 'wal', 'wal']],
    'pinned current frames' => [static fn (): mixed => $releasePinned()['current_reader_frame_indexes'], [1, 2, null]],
    'pinned next frames' => [static fn (): mixed => $releasePinned()['next_reader_frame_indexes'], [1, 5, 4]],
    'pinned current keeps snapshot' => [static fn (): mixed => $releasePinned()['current_reader_kept_snapshot'], true],
    'pinned next sees released savepoint' => [static fn (): mixed => $releasePinned()['next_reader_sees_released_savepoint'], true],
    'pinned next uses preserved wal' => [static fn (): mixed => $releasePinned()['next_reader_uses_preserved_wal'], true],
    'pinned next does not use checkpoint database only' => [static fn (): mixed => $releasePinned()['next_reader_uses_checkpoint_database'], false],
    'pinned images differ' => [static fn (): mixed => $releasePinned()['images_match'], false],
    'pinned read mark pins frame' => [static fn (): mixed => $releasePinned()['current_read_marks']['checkpoint_pinned_frame'], 2],
    'pinned read mark cannot finish' => [static fn (): mixed => $releasePinned()['current_read_marks']['checkpoint_can_finish'], false],
    'pinned reusable slots include stale and unused slots' => [static fn (): mixed => $releasePinned()['current_read_marks']['reusable_slots'], [0, 1]],
    'pinned dependency names release boundary' => [static fn (): mixed => in_array('sqlite-wal-savepoint-release-reader-current-next', $releasePinned()['dependencies'], true), true],
    'pinned dependency names application import' => [static fn (): mixed => in_array('application-import-release-reader-current-next', $releasePinned()['dependencies'], true), true],

    'latest status is ready' => [static fn (): mixed => $releaseLatest()['status'], 'ready'],
    'latest mode is truncate' => [static fn (): mixed => $releaseLatest()['mode'], 'truncate'],
    'latest reason can truncate' => [static fn (): mixed => $releaseLatest()['checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'latest wal action truncates' => [static fn (): mixed => $releaseLatest()['wal_action'], 'truncate_wal'],
    'latest current frame' => [static fn (): mixed => $releaseLatest()['current_reader_end_frame'], 5],
    'latest next frame after truncate' => [static fn (): mixed => $releaseLatest()['next_reader_end_frame'], 0],
    'latest current sources' => [static fn (): mixed => $releaseLatest()['current_reader_sources'], ['wal', 'wal', 'wal']],
    'latest next sources are database' => [static fn (): mixed => $releaseLatest()['next_reader_sources'], ['database', 'database', 'database']],
    'latest current frames' => [static fn (): mixed => $releaseLatest()['current_reader_frame_indexes'], [1, 5, 4]],
    'latest next frames are database only' => [static fn (): mixed => $releaseLatest()['next_reader_frame_indexes'], [null, null, null]],
    'latest images match checkpoint' => [static fn (): mixed => $releaseLatest()['images_match'], true],
    'latest next uses checkpoint database' => [static fn (): mixed => $releaseLatest()['next_reader_uses_checkpoint_database'], true],
    'latest next does not preserve wal' => [static fn (): mixed => $releaseLatest()['next_reader_uses_preserved_wal'], false],
    'latest read mark has no pin' => [static fn (): mixed => $releaseLatest()['current_read_marks']['checkpoint_pinned_frame'], null],
    'latest read mark can finish' => [static fn (): mixed => $releaseLatest()['current_read_marks']['checkpoint_can_finish'], true],

    'passive pinned status ready' => [static fn (): mixed => $releasePassivePinned()['status'], 'ready'],
    'passive pinned reason is reader limited' => [static fn (): mixed => $releasePassivePinned()['checkpoint_reason'], 'reader_limited_passive_checkpoint'],
    'passive pinned action preserves wal' => [static fn (): mixed => $releasePassivePinned()['wal_action'], 'preserve_wal'],
    'passive pinned current sources' => [static fn (): mixed => $releasePassivePinned()['current_reader_sources'], ['wal', 'database']],
    'passive pinned next sources' => [static fn (): mixed => $releasePassivePinned()['next_reader_sources'], ['wal', 'wal']],
    'passive pinned current frames' => [static fn (): mixed => $releasePassivePinned()['current_reader_frame_indexes'], [2, null]],
    'passive pinned next frames' => [static fn (): mixed => $releasePassivePinned()['next_reader_frame_indexes'], [5, 4]],
    'passive pinned images differ' => [static fn (): mixed => $releasePassivePinned()['images_match'], false],
    'passive pinned merged pages' => [static fn (): mixed => $releasePassivePinned()['merged_page_numbers'], [2, 3]],

    'nested release names only nested' => [static fn (): mixed => $releaseNested()['released_frame_names'], ['nested-transient']],
    'nested merged page only page two' => [static fn (): mixed => $releaseNested()['merged_page_numbers'], [2]],
    'nested result depth keeps plugin frame' => [static fn (): mixed => $releaseNested()['result_depth'], 2],
    'nested current frame is four' => [static fn (): mixed => $releaseNested()['current_reader_end_frame'], 4],
    'nested checkpoint is busy' => [static fn (): mixed => $releaseNested()['status'], 'busy'],
    'nested current sources' => [static fn (): mixed => $releaseNested()['current_reader_sources'], ['wal', 'wal']],
    'nested next sources' => [static fn (): mixed => $releaseNested()['next_reader_sources'], ['wal', 'wal']],
    'nested current frames' => [static fn (): mixed => $releaseNested()['current_reader_frame_indexes'], [3, 4]],
    'nested next frames' => [static fn (): mixed => $releaseNested()['next_reader_frame_indexes'], [5, 4]],
    'nested reader images differ on page two' => [static fn (): mixed => $releaseNested()['images_match'], false],
    'nested next sees released page' => [static fn (): mixed => $releaseNested()['next_reader_sees_released_savepoint'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal snapshot savepoint reader current next53 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal snapshot savepoint reader current next53 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease($makeStack(), 'plugin-settings', $wal, $databaseBytes, [], [2]));
};

$tests['wal snapshot savepoint reader current next53 rejects empty read marks'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease($makeStack(), 'plugin-settings', $wal, $databaseBytes, [2], []));
};

$tests['wal snapshot savepoint reader current next53 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease($makeStack(), 'plugin-settings', $wal, $databaseBytes, ['2'], [2]));
};

$tests['wal snapshot savepoint reader current next53 rejects negative read mark'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease($makeStack(), 'plugin-settings', $wal, $databaseBytes, [2], [-1]));
};

$tests['wal snapshot savepoint reader current next53 rejects missing savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease($makeStack(), 'missing', $wal, $databaseBytes, [2], [2]));
};

$tests['wal snapshot savepoint reader current next53 rejects invalid checkpoint mode'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease($makeStack(), 'plugin-settings', $wal, $databaseBytes, [2], [2], 'invalid'));
};

return $tests;
