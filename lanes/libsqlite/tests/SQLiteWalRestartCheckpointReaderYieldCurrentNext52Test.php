<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db page 1 wp_options schema base')
    . $page('db page 2 active_plugins base')
    . $page('db page 3 autoload index base')
    . $page('db page 4 transient cache base');
$salt1 = 0x52525252;
$salt2 = 0x25252525;

$makeWal = static function (array $frames, int $checkpointSequence = 52) use ($pageSize, $salt1, $salt2): string {
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

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted) use ($pageSize): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack(
        'V*',
        3007000,
        $backfill,
        104,
        $pageSizeField,
        5,
        4,
        0x01010101,
        0x02020202,
        0x11111111,
        0x22222222,
        0x33333333,
        0x44444444
    );
    $marks = array_pad(array_map(static fn (?int $mark): int => $mark ?? 0xffffffff, array_values($readMarks)), SQLiteShmIndex::READER_COUNT, 0xffffffff);
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$walBytes = $makeWal([
    [1, 0, $page('frame 1 schema draft before restart')],
    [2, 0, $page('frame 2 active plugins pinned reader')],
    [3, 4, $page('frame 3 autoload index committed batch')],
    [2, 0, $page('frame 4 active plugins later draft')],
    [4, 4, $page('frame 5 transient cache committed batch')],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$pinnedShm = SQLiteShmIndex::parse($makeShm([0, 3, 5, null, null], [false, true, true, false, false], 1, 3));

$restartYield = static fn (): array => $wal->restartCheckpointReaderYieldCurrentNext(
    $databaseBytes,
    $pinnedShm,
    [1, 2, 3, 4],
    [1],
    'restart'
);
$truncateYield = static fn (): array => $wal->restartCheckpointReaderYieldCurrentNext(
    $databaseBytes,
    $pinnedShm,
    [1, 2, 3, 4],
    [1],
    'truncate'
);
$stillPinned = static fn (): array => $wal->restartCheckpointReaderYieldCurrentNext(
    $databaseBytes,
    $pinnedShm,
    [2, 4],
    [],
    'restart'
);

$cases = [
    'restart status ready after reader yield' => [static fn (): mixed => $restartYield()['status'], 'yielded-reset-ready'],
    'restart mode preserved' => [static fn (): mixed => $restartYield()['mode'], 'restart'],
    'restart yielded slots sorted' => [static fn (): mixed => $restartYield()['yielded_slots'], [1]],
    'restart first checkpoint busy' => [static fn (): mixed => $restartYield()['first_checkpoint']['busy'], true],
    'restart first checkpoint reason' => [static fn (): mixed => $restartYield()['first_checkpoint']['reason'], 'reader_blocks_checkpoint_completion'],
    'restart first checkpoint preserves wal' => [static fn (): mixed => $restartYield()['first_checkpoint']['wal_action'], 'preserve_wal'],
    'restart yielded checkpoint not busy' => [static fn (): mixed => $restartYield()['yielded_checkpoint']['busy'], false],
    'restart yielded checkpoint reason' => [static fn (): mixed => $restartYield()['yielded_checkpoint']['reason'], 'restart_checkpoint_can_reset_wal'],
    'restart yielded checkpoint action' => [static fn (): mixed => $restartYield()['yielded_checkpoint']['wal_action'], 'restart_wal'],
    'restart yielded checkpoint wal header only' => [static fn (): mixed => $restartYield()['yielded_checkpoint']['wal_bytes_length'], 32],
    'restart yielded checkpoint sequence increments' => [static fn (): mixed => $restartYield()['yielded_checkpoint']['wal_header']['checkpoint_sequence'], 53],
    'restart current reader end frame pinned' => [static fn (): mixed => $restartYield()['current_reader_end_frame'], 3],
    'restart next reader end frame reset' => [static fn (): mixed => $restartYield()['next_reader_end_frame'], 0],
    'restart current reader sources' => [static fn (): mixed => $restartYield()['current_reader_sources'], ['wal', 'wal', 'wal', 'database']],
    'restart next reader sources' => [static fn (): mixed => $restartYield()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'restart current frame indexes' => [static fn (): mixed => $restartYield()['current_reader_frame_indexes'], [1, 2, 3, null]],
    'restart next frame indexes reset' => [static fn (): mixed => $restartYield()['next_reader_frame_indexes'], [null, null, null, null]],
    'restart current keeps pinned page two' => [static fn (): mixed => str_contains($restartYield()['current_reader'][1]['image'], 'pinned reader'), true],
    'restart next sees committed page two' => [static fn (): mixed => str_contains($restartYield()['next_reader'][1]['image'], 'later draft'), true],
    'restart next page three checkpointed' => [static fn (): mixed => str_contains($restartYield()['next_reader'][2]['image'], 'autoload index committed'), true],
    'restart next page four checkpointed' => [static fn (): mixed => str_contains($restartYield()['next_reader'][3]['image'], 'transient cache committed'), true],
    'restart current snapshot kept' => [static fn (): mixed => $restartYield()['current_reader_kept_snapshot'], true],
    'restart next reader uses database' => [static fn (): mixed => $restartYield()['next_reader_uses_database'], true],
    'restart next reader uses restarted wal header' => [static fn (): mixed => $restartYield()['next_reader_uses_restarted_wal'], true],
    'restart yield unblocked reset' => [static fn (): mixed => $restartYield()['reader_yield_unblocked_reset'], true],
    'restart yield count' => [static fn (): mixed => $restartYield()['yield_count'], 8],
    'restart before mark one pins' => [static fn (): mixed => $restartYield()['read_marks_before'][1]['pins_checkpoint'], true],
    'restart after mark one unused' => [static fn (): mixed => $restartYield()['read_marks_after'][1]['reason'], 'unused_slot'],
    'restart after mark two pins latest commit' => [static fn (): mixed => $restartYield()['read_marks_after'][2]['reason'], 'pins_latest_commit'],
    'restart dependency marker' => [static fn (): mixed => in_array('wal-restart-checkpoint-reader-yield-current-next52', $restartYield()['dependencies'], true), true],
    'restart dependency checkpoint marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $restartYield()['dependencies'], true), true],
    'truncate status ready after reader yield' => [static fn (): mixed => $truncateYield()['status'], 'yielded-reset-ready'],
    'truncate yielded action' => [static fn (): mixed => $truncateYield()['yielded_checkpoint']['wal_action'], 'truncate_wal'],
    'truncate yielded wal removed' => [static fn (): mixed => $truncateYield()['yielded_checkpoint']['wal_bytes_length'], 0],
    'truncate next reader end frame zero' => [static fn (): mixed => $truncateYield()['next_reader_end_frame'], 0],
    'truncate next sources database' => [static fn (): mixed => $truncateYield()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'truncate restarted wal flag false' => [static fn (): mixed => $truncateYield()['next_reader_uses_restarted_wal'], false],
    'truncate reader yield unblocked reset' => [static fn (): mixed => $truncateYield()['reader_yield_unblocked_reset'], true],
    'truncate current page two remains pinned' => [static fn (): mixed => str_contains($truncateYield()['current_reader'][1]['image'], 'pinned reader'), true],
    'truncate next page two includes committed draft' => [static fn (): mixed => str_contains($truncateYield()['next_reader'][1]['image'], 'later draft'), true],
    'still pinned status' => [static fn (): mixed => $stillPinned()['status'], 'still-pinned'],
    'still pinned first busy' => [static fn (): mixed => $stillPinned()['first_checkpoint']['busy'], true],
    'still pinned yielded checkpoint busy' => [static fn (): mixed => $stillPinned()['yielded_checkpoint']['busy'], true],
    'still pinned yielded slots empty' => [static fn (): mixed => $stillPinned()['yielded_slots'], []],
    'still pinned wal preserved' => [static fn (): mixed => $stillPinned()['yielded_checkpoint']['wal_action'], 'preserve_wal'],
    'still pinned does not unblock reset' => [static fn (): mixed => $stillPinned()['reader_yield_unblocked_reset'], false],
    'still pinned next source page two wal' => [static fn (): mixed => $stillPinned()['next_reader_sources'][0], 'wal'],
    'still pinned next frame page two' => [static fn (): mixed => $stillPinned()['next_reader_frame_indexes'][0], 4],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal restart checkpoint reader yield current next52 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal restart checkpoint reader yield current next52 rejects empty pages'] = static function (TestRunner $t) use ($wal, $databaseBytes, $pinnedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->restartCheckpointReaderYieldCurrentNext($databaseBytes, $pinnedShm, [], [1]));
};

$tests['wal restart checkpoint reader yield current next52 rejects invalid mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $pinnedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->restartCheckpointReaderYieldCurrentNext($databaseBytes, $pinnedShm, [1], [1], 'passive'));
};

$tests['wal restart checkpoint reader yield current next52 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes, $pinnedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->restartCheckpointReaderYieldCurrentNext($databaseBytes, $pinnedShm, ['2'], [1]));
};

$tests['wal restart checkpoint reader yield current next52 rejects invalid yielded slot'] = static function (TestRunner $t) use ($wal, $databaseBytes, $pinnedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->restartCheckpointReaderYieldCurrentNext($databaseBytes, $pinnedShm, [1], [5]));
};

return $tests;
