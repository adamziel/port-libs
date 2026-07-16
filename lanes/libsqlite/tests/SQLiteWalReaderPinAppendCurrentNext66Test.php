<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db66-page-1-schema-before') . $page('db66-page-2-option-before') . $page('db66-page-3-index-before') . $page('db66-page-4-meta-before');
$salt1 = 0x66112233;
$salt2 = 0x66556677;

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 66, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$wal = SQLiteWal::parse($makeWal([
    [2, 0, $page('wal66-frame-1-siteurl-current-reader')],
    [3, 4, $page('wal66-frame-2-autoload-index-commit')],
    [2, 0, $page('wal66-frame-3-siteurl-pre-append')],
    [4, 4, $page('wal66-frame-4-meta-pre-append-commit')],
]), null, true);

$appendFrames = [
    ['page_number' => 2, 'commit_page_count' => 0, 'page_image' => $page('wal66-frame-5-siteurl-appended-writer')],
    ['page_number' => 3, 'commit_page_count' => 0, 'page_image' => $page('wal66-frame-6-index-appended-writer')],
    ['page_number' => 4, 'commit_page_count' => 4, 'page_image' => $page('wal66-frame-7-meta-appended-commit')],
];

$pinnedAppend = static fn (): array => $wal->checkpointReaderPinAppendCurrentNext($databaseBytes, [2, 3, 4], [0, 2, null, null], $appendFrames, 'restart');
$passiveAppend = static fn (): array => $wal->checkpointReaderPinAppendCurrentNext($databaseBytes, [2, 3, 4], [0, 2, null, null], $appendFrames, 'passive');
$fullPinnedAppend = static fn (): array => $wal->checkpointReaderPinAppendCurrentNext($databaseBytes, [2, 3, 4], [2, 4], $appendFrames, 'restart');
$invalidImageAppend = [['page_number' => 2, 'commit_page_count' => 4, 'page_image' => 'short']];
$invalidPageAppend = [['page_number' => 0, 'commit_page_count' => 4, 'page_image' => $page('bad-page')]];
$invalidCommitAppend = [['page_number' => 2, 'commit_page_count' => -1, 'page_image' => $page('bad-commit')]];

$cases = [
    'pinned append status' => [static fn (): mixed => $pinnedAppend()['status'], 'current-reader-pinned-next-writer-appended'],
    'pinned append mode' => [static fn (): mixed => $pinnedAppend()['mode'], 'restart'],
    'pinned append checkpoint is busy' => [static fn (): mixed => $pinnedAppend()['checkpoint_busy'], true],
    'pinned append reason' => [static fn (): mixed => $pinnedAppend()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'pinned append preserves wal' => [static fn (): mixed => $pinnedAppend()['wal_action'], 'preserve_wal'],
    'pinned append current frame' => [static fn (): mixed => $pinnedAppend()['current_reader_end_frame'], 2],
    'pinned append next frame' => [static fn (): mixed => $pinnedAppend()['next_reader_end_frame'], 7],
    'pinned append frame count' => [static fn (): mixed => $pinnedAppend()['appended_frame_count'], 3],
    'pinned append wal frame count' => [static fn (): mixed => $pinnedAppend()['appended_wal_frame_count'], 7],
    'pinned append wal byte length' => [static fn (): mixed => $pinnedAppend()['appended_wal_bytes_length'], 32 + (24 + $pageSize) * 7],
    'pinned append next slot' => [static fn (): mixed => $pinnedAppend()['next_reader_slot'], 2],
    'pinned append original marks' => [static fn (): mixed => $pinnedAppend()['current_read_marks'], [0, 2, null, null]],
    'pinned append next marks' => [static fn (): mixed => $pinnedAppend()['next_read_marks'], [0, 2, 7, null]],
    'pinned append current sources' => [static fn (): mixed => $pinnedAppend()['current_sources'], ['wal', 'wal', 'database']],
    'pinned append next sources' => [static fn (): mixed => $pinnedAppend()['next_sources'], ['wal', 'wal', 'wal']],
    'pinned append current frame indexes' => [static fn (): mixed => $pinnedAppend()['current_frame_indexes'], [1, 2, null]],
    'pinned append next frame indexes' => [static fn (): mixed => $pinnedAppend()['next_frame_indexes'], [5, 6, 7]],
    'pinned append current remains stable' => [static fn (): mixed => $pinnedAppend()['current_stable'], true],
    'pinned append next sees writer' => [static fn (): mixed => $pinnedAppend()['next_sees_appended_commit'], true],
    'pinned append reset remains blocked' => [static fn (): mixed => $pinnedAppend()['pin_blocks_reset'], true],
    'pinned append dependency marker' => [static fn (): mixed => in_array('wal-reader-pin-current-next66', $pinnedAppend()['dependencies'], true), true],
    'pinned append writer dependency marker' => [static fn (): mixed => in_array('sqlite-wal-append-after-pinned-checkpoint', $pinnedAppend()['dependencies'], true), true],
    'pinned append keeps checkpoint dependency' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $pinnedAppend()['dependencies'], true), true],
    'pinned append base next frame before append' => [static fn (): mixed => $pinnedAppend()['base']['next_reader_end_frame'], 4],
    'pinned append base checkpoint busy' => [static fn (): mixed => $pinnedAppend()['base']['checkpoint_busy'], true],
    'pinned append page two current image' => [static fn (): mixed => str_contains($pinnedAppend()['current_reader'][0]['image'], 'current-reader'), true],
    'pinned append page two next image' => [static fn (): mixed => str_contains($pinnedAppend()['next_reader'][0]['image'], 'appended-writer'), true],
    'pinned append page three current image' => [static fn (): mixed => str_contains($pinnedAppend()['current_reader'][1]['image'], 'autoload-index'), true],
    'pinned append page three next image' => [static fn (): mixed => str_contains($pinnedAppend()['next_reader'][1]['image'], 'index-appended'), true],
    'pinned append page four current image' => [static fn (): mixed => str_contains($pinnedAppend()['current_reader'][2]['image'], 'meta-before'), true],
    'pinned append page four next image' => [static fn (): mixed => str_contains($pinnedAppend()['next_reader'][2]['image'], 'meta-appended'), true],
    'pinned append current image differs from next page two' => [static fn (): mixed => $pinnedAppend()['current_images'][0] !== $pinnedAppend()['next_images'][0], true],
    'pinned append current image differs from next page three' => [static fn (): mixed => $pinnedAppend()['current_images'][1] !== $pinnedAppend()['next_images'][1], true],
    'pinned append current image differs from next page four' => [static fn (): mixed => $pinnedAppend()['current_images'][2] !== $pinnedAppend()['next_images'][2], true],
    'passive append status' => [static fn (): mixed => $passiveAppend()['status'], 'current-reader-pinned-next-writer-appended'],
    'passive append mode' => [static fn (): mixed => $passiveAppend()['mode'], 'passive'],
    'passive append checkpoint not busy' => [static fn (): mixed => $passiveAppend()['checkpoint_busy'], false],
    'passive append reason' => [static fn (): mixed => $passiveAppend()['checkpoint_reason'], 'reader_limited_passive_checkpoint'],
    'passive append preserves wal' => [static fn (): mixed => $passiveAppend()['wal_action'], 'preserve_wal'],
    'passive append current frame remains pin' => [static fn (): mixed => $passiveAppend()['current_reader_end_frame'], 2],
    'passive append next frame includes writer' => [static fn (): mixed => $passiveAppend()['next_reader_end_frame'], 7],
    'passive append current stable' => [static fn (): mixed => $passiveAppend()['current_stable'], true],
    'passive append next sees writer' => [static fn (): mixed => $passiveAppend()['next_sees_appended_commit'], true],
    'full pinned append has no next slot' => [static fn (): mixed => $fullPinnedAppend()['next_reader_slot'], null],
    'full pinned append read marks unchanged' => [static fn (): mixed => $fullPinnedAppend()['next_read_marks'], [2, 4]],
    'full pinned append next still sees writer' => [static fn (): mixed => $fullPinnedAppend()['next_reader_end_frame'], 7],
    'full pinned append current frame is oldest pin' => [static fn (): mixed => $fullPinnedAppend()['current_reader_end_frame'], 2],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader pin append current next66 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader pin append current next66 rejects empty append'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinAppendCurrentNext($databaseBytes, [2], [0, 2], [], 'restart'));
};

$tests['wal reader pin append current next66 rejects unpinned checkpoint'] = static function (TestRunner $t) use ($wal, $databaseBytes, $appendFrames): void {
    $t->throws(RuntimeException::class, static fn (): mixed => $wal->checkpointReaderPinAppendCurrentNext($databaseBytes, [2], [0, 4], $appendFrames, 'restart'));
};

$tests['wal reader pin append current next66 rejects short image'] = static function (TestRunner $t) use ($wal, $databaseBytes, $invalidImageAppend): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinAppendCurrentNext($databaseBytes, [2], [0, 2], $invalidImageAppend, 'restart'));
};

$tests['wal reader pin append current next66 rejects page zero'] = static function (TestRunner $t) use ($wal, $databaseBytes, $invalidPageAppend): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinAppendCurrentNext($databaseBytes, [2], [0, 2], $invalidPageAppend, 'restart'));
};

$tests['wal reader pin append current next66 rejects negative commit size'] = static function (TestRunner $t) use ($wal, $databaseBytes, $invalidCommitAppend): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinAppendCurrentNext($databaseBytes, [2], [0, 2], $invalidCommitAppend, 'restart'));
};

$tests['wal reader pin append current next66 rejects non integer page list'] = static function (TestRunner $t) use ($wal, $databaseBytes, $appendFrames): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinAppendCurrentNext($databaseBytes, ['2'], [0, 2], $appendFrames, 'restart'));
};

return $tests;
