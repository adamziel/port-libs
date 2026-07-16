<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db68-page-1-schema-before') . $page('db68-page-2-option-before') . $page('db68-page-3-index-before') . $page('db68-page-4-meta-before');
$salt1 = 0x68112233;
$salt2 = 0x68556677;

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 68, $salt1, $salt2);
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
    [2, 0, $page('wal68-frame-1-siteurl-current-reader')],
    [3, 4, $page('wal68-frame-2-autoload-index-commit')],
    [2, 0, $page('wal68-frame-3-siteurl-next-reader')],
    [4, 0, $page('wal68-frame-4-plugin-draft')],
    [3, 0, $page('wal68-frame-5-index-next-reader')],
    [4, 4, $page('wal68-frame-6-plugin-next-commit')],
]), null, true);

$restart = static fn (): array => $wal->checkpointReaderPinSlotHandoffCurrentNext($databaseBytes, [2, 3, 4], [0, 2, null, null], null, 'restart');
$truncate = static fn (): array => $wal->checkpointReaderPinSlotHandoffCurrentNext($databaseBytes, [2, 3, 4], [0, 2, null, null], 2, 'truncate');
$invalidSlot = static fn (): array => $wal->checkpointReaderPinSlotHandoffCurrentNext($databaseBytes, [2, 3, 4], [0, 2, 99, null], 2, 'restart');
$extendedSlot = static fn (): array => $wal->checkpointReaderPinSlotHandoffCurrentNext($databaseBytes, [2, 3], [0, 2], 4, 'restart');

$cases = [
    'restart status' => [static fn (): mixed => $restart()['status'], 'current-reader-pinned-next-reader-active'],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart current frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart next frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 6],
    'restart next slot' => [static fn (): mixed => $restart()['next_reader_slot'], 2],
    'restart current marks' => [static fn (): mixed => $restart()['current_read_marks'], [0, 2, null, null]],
    'restart next marks' => [static fn (): mixed => $restart()['next_read_marks'], [0, 2, 6, null]],
    'restart released marks' => [static fn (): mixed => $restart()['released_read_marks'], [0, null, 6, null]],
    'restart current pin frames' => [static fn (): mixed => $restart()['current_pin_frames'], [2]],
    'restart next slot reusable' => [static fn (): mixed => $restart()['next_reader_slot_reusable_before'], true],
    'restart checkpoint with next is busy' => [static fn (): mixed => $restart()['checkpoint_with_next']['busy'], true],
    'restart checkpoint reason' => [static fn (): mixed => $restart()['checkpoint_with_next']['reason'], 'reader_blocks_checkpoint_completion'],
    'restart checkpoint preserves wal' => [static fn (): mixed => $restart()['checkpoint_with_next']['wal_action'], 'preserve_wal'],
    'restart released checkpoint not busy' => [static fn (): mixed => $restart()['released_checkpoint']['busy'], false],
    'restart released checkpoint can reset' => [static fn (): mixed => $restart()['released_checkpoint']['can_reset'], true],
    'restart released checkpoint action' => [static fn (): mixed => $restart()['released_checkpoint']['wal_action'], 'restart_wal'],
    'restart current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database']],
    'restart next sources' => [static fn (): mixed => $restart()['next_sources'], ['wal', 'wal', 'wal']],
    'restart current frame indexes' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, null]],
    'restart next frame indexes' => [static fn (): mixed => $restart()['next_frame_indexes'], [3, 5, 6]],
    'restart current no errors' => [static fn (): mixed => $restart()['current_errors'], []],
    'restart next no errors' => [static fn (): mixed => $restart()['next_errors'], []],
    'restart current stable' => [static fn (): mixed => $restart()['current_stable'], true],
    'restart next latest' => [static fn (): mixed => $restart()['next_matches_latest_snapshot'], true],
    'restart next does not pin checkpoint' => [static fn (): mixed => $restart()['next_reader_does_not_pin_checkpoint'], true],
    'restart release unblocks reset' => [static fn (): mixed => $restart()['release_unblocks_reset'], true],
    'restart dependency marker' => [static fn (): mixed => in_array('wal-reader-pin-current-next68', $restart()['dependencies'], true), true],
    'restart readmark dependency' => [static fn (): mixed => in_array('sqlite-wal-readmark-current-next-handoff', $restart()['dependencies'], true), true],
    'restart keeps base dependency' => [static fn (): mixed => in_array('wal-reader-current-next-pin', $restart()['dependencies'], true), true],
    'restart page two current image' => [static fn (): mixed => str_contains($restart()['current_reader'][0]['image'], 'current-reader'), true],
    'restart page two next image' => [static fn (): mixed => str_contains($restart()['next_reader'][0]['image'], 'next-reader'), true],
    'restart page three current image' => [static fn (): mixed => str_contains($restart()['current_reader'][1]['image'], 'autoload-index'), true],
    'restart page three next image' => [static fn (): mixed => str_contains($restart()['next_reader'][1]['image'], 'index-next-reader'), true],
    'restart page four current image' => [static fn (): mixed => str_contains($restart()['current_reader'][2]['image'], 'meta-before'), true],
    'restart page four next image' => [static fn (): mixed => str_contains($restart()['next_reader'][2]['image'], 'plugin-next-commit'), true],
    'restart current differs next page two' => [static fn (): mixed => $restart()['current_images'][0] !== $restart()['next_images'][0], true],
    'restart current differs next page three' => [static fn (): mixed => $restart()['current_images'][1] !== $restart()['next_images'][1], true],
    'restart current differs next page four' => [static fn (): mixed => $restart()['current_images'][2] !== $restart()['next_images'][2], true],
    'restart base pin blocks reset' => [static fn (): mixed => $restart()['base']['pin_blocks_reset'], true],
    'restart base next frame before handoff' => [static fn (): mixed => $restart()['base']['next_reader_end_frame'], 6],
    'truncate status' => [static fn (): mixed => $truncate()['status'], 'current-reader-pinned-next-reader-active'],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate checkpoint with next busy' => [static fn (): mixed => $truncate()['checkpoint_with_next']['busy'], true],
    'truncate checkpoint preserves wal' => [static fn (): mixed => $truncate()['checkpoint_with_next']['wal_action'], 'preserve_wal'],
    'truncate released checkpoint action' => [static fn (): mixed => $truncate()['released_checkpoint']['wal_action'], 'truncate_wal'],
    'truncate release unblocks reset' => [static fn (): mixed => $truncate()['release_unblocks_reset'], true],
    'invalid slot reused' => [static fn (): mixed => $invalidSlot()['next_reader_slot'], 2],
    'invalid slot marked reusable' => [static fn (): mixed => $invalidSlot()['next_reader_slot_reusable_before'], true],
    'invalid slot next marks' => [static fn (): mixed => $invalidSlot()['next_read_marks'], [0, 2, 6, null]],
    'invalid slot release unblocks reset' => [static fn (): mixed => $invalidSlot()['release_unblocks_reset'], true],
    'extended slot chosen' => [static fn (): mixed => $extendedSlot()['next_reader_slot'], 4],
    'extended slot pads marks' => [static fn (): mixed => $extendedSlot()['next_read_marks'], [0, 2, null, null, 6]],
    'extended slot release preserves next' => [static fn (): mixed => $extendedSlot()['released_read_marks'], [0, null, null, null, 6]],
    'extended slot next sources' => [static fn (): mixed => $extendedSlot()['next_sources'], ['wal', 'wal']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader pin current next68 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader pin current next68 rejects unpinned checkpoint'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(RuntimeException::class, static fn (): mixed => $wal->checkpointReaderPinSlotHandoffCurrentNext($databaseBytes, [2], [0, 6, null]));
};

$tests['wal reader pin current next68 rejects active latest slot overwrite'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinSlotHandoffCurrentNext($databaseBytes, [2], [0, 2, 6], 2));
};

$tests['wal reader pin current next68 rejects negative slot'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinSlotHandoffCurrentNext($databaseBytes, [2], [0, 2, null], -1));
};

$tests['wal reader pin current next68 rejects out of range slot'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinSlotHandoffCurrentNext($databaseBytes, [2], [0, 2, null], 5));
};

$tests['wal reader pin current next68 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderPinSlotHandoffCurrentNext($databaseBytes, ['2'], [0, 2, null]));
};

return $tests;
