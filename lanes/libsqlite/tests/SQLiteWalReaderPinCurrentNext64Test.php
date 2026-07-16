<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db-page-1-schema-before') . $page('db-page-2-options-before') . $page('db-page-3-index-before') . $page('db-page-4-meta-before');
$salt1 = 0x64112233;
$salt2 = 0x64556677;

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 64, $salt1, $salt2);
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
    [2, 0, $page('wal64-frame-1-siteurl-for-current-reader')],
    [3, 4, $page('wal64-frame-2-autoload-index-commit')],
    [2, 0, $page('wal64-frame-3-siteurl-after-current')],
    [4, 0, $page('wal64-frame-4-network-meta-after-current')],
    [2, 4, $page('wal64-frame-5-siteurl-latest-commit')],
]), null, true);

$pinnedRestart = static fn (): array => $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, [2, 3, 4], [0, 2, null, null], 'restart');
$pinnedPassive = static fn (): array => $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, [2, 3, 4], [0, 2, null, null], 'passive');
$unpinnedRestart = static fn (): array => $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, [2, 3, 4], [0, 5, null, null], 'restart');
$unpinnedTruncate = static fn (): array => $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, [2, 3, 4], [0, 5, null, null], 'truncate');
$fullPinned = static fn (): array => $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, [2, 3, 4], [2, 5, 5], 'restart');
$invalidMark = static fn (): array => $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, [2, 3, 4], [0, 99, null], 'restart');

$cases = [
    'pinned restart status releases current' => [static fn (): mixed => $pinnedRestart()['status'], 'current-reader-released-next-reader-ready'],
    'pinned restart mode preserved' => [static fn (): mixed => $pinnedRestart()['mode'], 'restart'],
    'pinned restart original checkpoint is busy' => [static fn (): mixed => $pinnedRestart()['checkpoint_busy'], true],
    'pinned restart checkpoint reason' => [static fn (): mixed => $pinnedRestart()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'pinned restart original wal is preserved' => [static fn (): mixed => $pinnedRestart()['wal_action'], 'preserve_wal'],
    'pinned restart current reader frame' => [static fn (): mixed => $pinnedRestart()['current_reader_end_frame'], 2],
    'pinned restart next reader frame' => [static fn (): mixed => $pinnedRestart()['next_reader_end_frame'], 5],
    'pinned restart chooses unused next slot' => [static fn (): mixed => $pinnedRestart()['next_reader_slot'], 2],
    'pinned restart next read mark is latest' => [static fn (): mixed => $pinnedRestart()['next_read_marks'], [0, 2, 5, null]],
    'pinned restart released read marks clear current pin' => [static fn (): mixed => $pinnedRestart()['released_read_marks'], [0, null, 5, null]],
    'pinned restart current pin released flag' => [static fn (): mixed => $pinnedRestart()['current_pin_released'], true],
    'pinned restart next reader survives release' => [static fn (): mixed => $pinnedRestart()['next_reader_survives_release'], true],
    'pinned restart retry can reset' => [static fn (): mixed => $pinnedRestart()['retry_can_reset'], true],
    'pinned restart retry action restarts wal' => [static fn (): mixed => $pinnedRestart()['retry_checkpoint']['wal_action'], 'restart_wal'],
    'pinned restart retry reason' => [static fn (): mixed => $pinnedRestart()['retry_checkpoint']['reason'], 'restart_checkpoint_can_reset_wal'],
    'pinned restart retry is not busy' => [static fn (): mixed => $pinnedRestart()['retry_checkpoint']['busy'], false],
    'pinned restart retry wal is header only' => [static fn (): mixed => $pinnedRestart()['retry_checkpoint']['wal_bytes_length'], 32],
    'pinned restart current sources stay wal' => [static fn (): mixed => $pinnedRestart()['current_sources'], ['wal', 'wal', 'database']],
    'pinned restart next sources stay wal' => [static fn (): mixed => $pinnedRestart()['next_sources'], ['wal', 'wal', 'wal']],
    'pinned restart current frame indexes' => [static fn (): mixed => $pinnedRestart()['current_frame_indexes'], [1, 2, null]],
    'pinned restart next frame indexes' => [static fn (): mixed => $pinnedRestart()['next_frame_indexes'], [5, 2, 4]],
    'pinned restart current stable' => [static fn (): mixed => $pinnedRestart()['current_stable'], true],
    'pinned restart next matches latest snapshot' => [static fn (): mixed => $pinnedRestart()['next_matches_latest_snapshot'], true],
    'pinned restart dependency marker' => [static fn (): mixed => in_array('wal-reader-pin-current-next64', $pinnedRestart()['dependencies'], true), true],
    'pinned restart readmark dependency marker' => [static fn (): mixed => in_array('sqlite-wal-readmark-handoff', $pinnedRestart()['dependencies'], true), true],
    'pinned restart keeps existing checkpoint dependency marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $pinnedRestart()['dependencies'], true), true],
    'pinned restart base is retained' => [static fn (): mixed => $pinnedRestart()['base']['pin_blocks_reset'], true],
    'pinned restart latest page two image' => [static fn (): mixed => str_contains($pinnedRestart()['base']['next_after'][0]['image'], 'latest-commit'), true],
    'pinned restart current page two image' => [static fn (): mixed => str_contains($pinnedRestart()['base']['current_after'][0]['image'], 'current-reader'), true],
    'pinned restart page four current is database' => [static fn (): mixed => str_contains($pinnedRestart()['base']['current_after'][2]['image'], 'meta-before'), true],
    'pinned restart page four next is wal' => [static fn (): mixed => str_contains($pinnedRestart()['base']['next_after'][2]['image'], 'network-meta'), true],
    'pinned passive status releases current' => [static fn (): mixed => $pinnedPassive()['status'], 'current-reader-released-next-reader-ready'],
    'pinned passive is not busy' => [static fn (): mixed => $pinnedPassive()['checkpoint_busy'], false],
    'pinned passive reason' => [static fn (): mixed => $pinnedPassive()['checkpoint_reason'], 'reader_limited_passive_checkpoint'],
    'pinned passive keeps wal action' => [static fn (): mixed => $pinnedPassive()['wal_action'], 'preserve_wal'],
    'pinned passive retry action still preserves wal' => [static fn (): mixed => $pinnedPassive()['retry_checkpoint']['wal_action'], 'preserve_wal'],
    'pinned passive retry cannot reset' => [static fn (): mixed => $pinnedPassive()['retry_can_reset'], false],
    'pinned passive next mark survives' => [static fn (): mixed => $pinnedPassive()['next_reader_survives_release'], true],
    'unpinned restart status' => [static fn (): mixed => $unpinnedRestart()['status'], 'no-current-reader-pin'],
    'unpinned restart checkpoint not busy' => [static fn (): mixed => $unpinnedRestart()['checkpoint_busy'], false],
    'unpinned restart current frame is latest' => [static fn (): mixed => $unpinnedRestart()['current_reader_end_frame'], 5],
    'unpinned restart next frame is zero after restart' => [static fn (): mixed => $unpinnedRestart()['next_reader_end_frame'], 0],
    'unpinned restart writes next database mark' => [static fn (): mixed => $unpinnedRestart()['next_read_marks'], [0, 5, 0, null]],
    'unpinned restart retry can reset' => [static fn (): mixed => $unpinnedRestart()['retry_can_reset'], true],
    'unpinned restart current sources are database after checkpoint' => [static fn (): mixed => $unpinnedRestart()['current_sources'], ['database', 'database', 'database']],
    'unpinned restart next sources are database' => [static fn (): mixed => $unpinnedRestart()['next_sources'], ['database', 'database', 'database']],
    'unpinned restart latest image moved to database' => [static fn (): mixed => str_contains($unpinnedRestart()['base']['next_after'][0]['image'], 'latest-commit'), true],
    'unpinned truncate status' => [static fn (): mixed => $unpinnedTruncate()['status'], 'no-current-reader-pin'],
    'unpinned truncate action' => [static fn (): mixed => $unpinnedTruncate()['wal_action'], 'truncate_wal'],
    'unpinned truncate retry wal empty' => [static fn (): mixed => $unpinnedTruncate()['retry_checkpoint']['wal_bytes_length'], 0],
    'unpinned truncate next frame zero' => [static fn (): mixed => $unpinnedTruncate()['next_reader_end_frame'], 0],
    'unpinned truncate next mark is database' => [static fn (): mixed => $unpinnedTruncate()['next_read_marks'], [0, 5, 0, null]],
    'full pinned has no next slot' => [static fn (): mixed => $fullPinned()['next_reader_slot'], null],
    'full pinned does not survive next handoff' => [static fn (): mixed => $fullPinned()['next_reader_survives_release'], false],
    'full pinned release still clears current pin' => [static fn (): mixed => $fullPinned()['current_pin_released'], true],
    'full pinned released marks' => [static fn (): mixed => $fullPinned()['released_read_marks'], [null, 5, 5]],
    'invalid mark status is unpinned' => [static fn (): mixed => $invalidMark()['status'], 'no-current-reader-pin'],
    'invalid mark slot uses unused reader slot first' => [static fn (): mixed => $invalidMark()['next_reader_slot'], 2],
    'invalid mark is preserved while unused slot starts next reader' => [static fn (): mixed => $invalidMark()['next_read_marks'], [0, 99, 0]],
    'invalid mark retry can reset' => [static fn (): mixed => $invalidMark()['retry_can_reset'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader pin current next64 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader pin current next64 rejects empty page list'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, [], [0, 2], 'restart'));
};

$tests['wal reader pin current next64 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, ['2'], [0, 2], 'restart'));
};

$tests['wal reader pin current next64 rejects negative read mark'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, [2], [-1], 'restart'));
};

$tests['wal reader pin current next64 rejects unsupported mode'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, [2], [0, 2], 'unknown'));
};

return $tests;
