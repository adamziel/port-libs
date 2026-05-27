<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db-page-1-schema') . $page('db-page-2-options-old') . $page('db-page-3-index-old');
$salt1 = 0x31415926;
$salt2 = 0x27182818;

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 31, $salt1, $salt2);
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
    [2, 0, $page('wal-frame-1-siteurl-before-pin')],
    [3, 3, $page('wal-frame-2-autoload-index-commit')],
    [2, 0, $page('wal-frame-3-siteurl-after-pin')],
    [2, 3, $page('wal-frame-4-siteurl-current-commit')],
]), null, true);

$pinnedRestart = static fn (): array => $wal->checkpointReaderPinCurrentNext($databaseBytes, [2, 3], [0, 2, 4, null], 'restart');
$pinnedPassive = static fn (): array => $wal->checkpointReaderPinCurrentNext($databaseBytes, [2, 3], [0, 2, 4, null], 'passive');
$unpinnedRestart = static fn (): array => $wal->checkpointReaderPinCurrentNext($databaseBytes, [2, 3], [0, 4, null, null], 'restart');
$unpinnedTruncate = static fn (): array => $wal->checkpointReaderPinCurrentNext($databaseBytes, [2, 3], [0, 4, null, null], 'truncate');

$cases = [
    'pinned restart mode preserved' => [static fn (): mixed => $pinnedRestart()['mode'], 'restart'],
    'pinned restart reason reset blocked' => [static fn (): mixed => $pinnedRestart()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'pinned restart is busy' => [static fn (): mixed => $pinnedRestart()['checkpoint_busy'], true],
    'pinned restart preserves wal' => [static fn (): mixed => $pinnedRestart()['wal_action'], 'preserve_wal'],
    'pinned restart current end frame' => [static fn (): mixed => $pinnedRestart()['current_reader_end_frame'], 2],
    'pinned restart next end frame' => [static fn (): mixed => $pinnedRestart()['next_reader_end_frame'], 4],
    'pinned restart current stable' => [static fn (): mixed => $pinnedRestart()['current_stable'], true],
    'pinned restart next matches latest' => [static fn (): mixed => $pinnedRestart()['next_matches_latest_snapshot'], true],
    'pinned restart pin blocks reset' => [static fn (): mixed => $pinnedRestart()['pin_blocks_reset'], true],
    'pinned restart read mark pins frame two' => [static fn (): mixed => $pinnedRestart()['read_mark_plan']['checkpoint_pinned_frame'], 2],
    'pinned restart reusable slots include database reader' => [static fn (): mixed => $pinnedRestart()['read_mark_plan']['reusable_slots'], [0, 1, 3]],
    'pinned restart current page two source' => [static fn (): mixed => $pinnedRestart()['current_sources'][0], 'wal'],
    'pinned restart current page two frame' => [static fn (): mixed => $pinnedRestart()['current_frame_indexes'][0], 1],
    'pinned restart current page three frame' => [static fn (): mixed => $pinnedRestart()['current_frame_indexes'][1], 2],
    'pinned restart next page two frame' => [static fn (): mixed => $pinnedRestart()['next_frame_indexes'][0], 4],
    'pinned restart next page three frame' => [static fn (): mixed => $pinnedRestart()['next_frame_indexes'][1], 2],
    'pinned restart current page two image is old' => [static fn (): mixed => str_contains($pinnedRestart()['current_after'][0]['image'], 'before-pin'), true],
    'pinned restart next page two image is current' => [static fn (): mixed => str_contains($pinnedRestart()['next_after'][0]['image'], 'current-commit'), true],
    'pinned restart current page three image retained' => [static fn (): mixed => str_contains($pinnedRestart()['current_after'][1]['image'], 'autoload-index-commit'), true],
    'pinned restart dependencies include current next' => [static fn (): mixed => in_array('wal-reader-current-next-pin', $pinnedRestart()['dependencies'], true), true],
    'pinned passive reason reader limited' => [static fn (): mixed => $pinnedPassive()['checkpoint_reason'], 'reader_limited_passive_checkpoint'],
    'pinned passive is not busy' => [static fn (): mixed => $pinnedPassive()['checkpoint_busy'], false],
    'pinned passive preserves wal' => [static fn (): mixed => $pinnedPassive()['wal_action'], 'preserve_wal'],
    'pinned passive current remains stable' => [static fn (): mixed => $pinnedPassive()['current_stable'], true],
    'pinned passive next remains latest' => [static fn (): mixed => $pinnedPassive()['next_matches_latest_snapshot'], true],
    'pinned passive current page two frame' => [static fn (): mixed => $pinnedPassive()['current_frame_indexes'][0], 1],
    'pinned passive next page two frame' => [static fn (): mixed => $pinnedPassive()['next_frame_indexes'][0], 4],
    'unpinned restart can reset' => [static fn (): mixed => $unpinnedRestart()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'unpinned restart is not busy' => [static fn (): mixed => $unpinnedRestart()['checkpoint_busy'], false],
    'unpinned restart rewrites wal header' => [static fn (): mixed => $unpinnedRestart()['wal_action'], 'restart_wal'],
    'unpinned restart current end frame latest' => [static fn (): mixed => $unpinnedRestart()['current_reader_end_frame'], 4],
    'unpinned restart next end frame header only' => [static fn (): mixed => $unpinnedRestart()['next_reader_end_frame'], 0],
    'unpinned restart current reads database after reset' => [static fn (): mixed => $unpinnedRestart()['current_sources'], ['database', 'database']],
    'unpinned restart next reads database after reset' => [static fn (): mixed => $unpinnedRestart()['next_sources'], ['database', 'database']],
    'unpinned restart current stable after checkpoint' => [static fn (): mixed => $unpinnedRestart()['current_stable'], true],
    'unpinned restart next matches latest' => [static fn (): mixed => $unpinnedRestart()['next_matches_latest_snapshot'], true],
    'unpinned restart pin does not block' => [static fn (): mixed => $unpinnedRestart()['pin_blocks_reset'], false],
    'unpinned restart no pinned frame' => [static fn (): mixed => $unpinnedRestart()['read_mark_plan']['checkpoint_pinned_frame'], null],
    'unpinned restart recommended frame latest commit' => [static fn (): mixed => $unpinnedRestart()['read_mark_plan']['recommended_reader_frame'], 4],
    'unpinned restart page two database has current commit' => [static fn (): mixed => str_contains($unpinnedRestart()['next_after'][0]['image'], 'current-commit'), true],
    'unpinned truncate removes wal' => [static fn (): mixed => $unpinnedTruncate()['wal_action'], 'truncate_wal'],
    'unpinned truncate next end frame zero' => [static fn (): mixed => $unpinnedTruncate()['next_reader_end_frame'], 0],
    'unpinned truncate current stable' => [static fn (): mixed => $unpinnedTruncate()['current_stable'], true],
    'unpinned truncate next matches latest' => [static fn (): mixed => $unpinnedTruncate()['next_matches_latest_snapshot'], true],
    'unpinned truncate page three from database' => [static fn (): mixed => $unpinnedTruncate()['next_sources'][1], 'database'],
    'unpinned truncate page three image committed' => [static fn (): mixed => str_contains($unpinnedTruncate()['next_after'][1]['image'], 'autoload-index-commit'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint reader pin current next31 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint reader pin current next31 rejects empty page list'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinCurrentNext($databaseBytes, [], [0, 2], 'restart'));
};

$tests['wal checkpoint reader pin current next31 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinCurrentNext($databaseBytes, ['2'], [0, 2], 'restart'));
};

$tests['wal checkpoint reader pin current next31 rejects negative read mark'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinCurrentNext($databaseBytes, [2], [-1], 'restart'));
};

return $tests;
