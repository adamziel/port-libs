<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x12345678;
$salt2 = 0x9abcdef0;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): SQLiteWal {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 70, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return SQLiteWal::parse($bytes, null, true);
};

$makeShm = static function (
    array $readMarks,
    array $readLocks,
    int $mxFrame,
    int $backfill,
    bool $matchingSalt = true,
    bool $matchingHeaderCopy = true
) use ($pageSize, $salt1, $salt2): SQLiteShmIndex {
    $pageSizeField = (1 << 24) | $pageSize;
    $saltA = $matchingSalt ? $salt1 : 0x01020304;
    $saltB = $matchingSalt ? $salt2 : 0x05060708;
    $header = pack(
        'V*',
        3007000,
        $backfill,
        70,
        $pageSizeField,
        $mxFrame,
        4,
        0x10101010,
        0x20202020,
        $saltA,
        $saltB,
        0x30303030,
        0x40404040
    );
    $headerCopy = $matchingHeaderCopy ? $header : substr_replace($header, pack('V', 71), 8, 4);
    $marks = array_map(static fn (?int $frame): int => $frame ?? 0xffffffff, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $backfill, 0);

    return SQLiteShmIndex::parse($header . $headerCopy . $checkpoint);
};

$wal = $makeWal([
    [2, 0, $page('frame-1-option-draft')],
    [3, 3, $page('frame-2-index-commit')],
    [2, 0, $page('frame-3-option-edit')],
    [4, 4, $page('frame-4-settings-commit')],
    [2, 0, $page('frame-5-uncommitted-tail')],
]);

$matching = $makeShm([0, 2, 4, 5, 99], [false, true, true, true, false], 99, 1)->recoverReadMarksFromWal($wal);
$staleCopy = $makeShm([0, 2, 4, null, null], [false, true, true, false, false], 4, 0, true, false)->recoverReadMarksFromWal($wal);
$saltMismatch = $makeShm([0, 2, 4, null, null], [false, true, true, false, false], 4, 0, false)->recoverReadMarksFromWal($wal);
$unlocked = $makeShm([0, 2, 4, null, null], [false, false, false, false, false], 4, 0)->recoverReadMarksFromWal($wal);
$uncommittedOnlyWal = $makeWal([
    [2, 0, $page('frame-1-option-draft')],
    [3, 0, $page('frame-2-index-draft')],
]);
$uncommittedOnly = $makeShm([0, 1, 2, null, null], [false, true, true, false, false], 2, 0)->recoverReadMarksFromWal($uncommittedOnlyWal);

$cases = [
    'matching status preserves locked readers' => [$matching['status'], 'recovered-with-readers'],
    'matching reason names recovery' => [$matching['reason'], 'read_marks_recovered_from_matching_wal'],
    'matching wal mx frame includes uncommitted tail' => [$matching['wal_mx_frame'], 5],
    'matching last commit ignores uncommitted tail' => [$matching['last_commit_frame'], 4],
    'matching headers match' => [$matching['headers_match'], true],
    'matching salt matches wal' => [$matching['salt_matches_wal'], true],
    'matching backfill is clamped to last commit' => [$matching['backfilled_frame_count'], 1],
    'matching next marks preserve locked frame two and four' => [$matching['next_read_marks'], [null, 2, 4, null, null]],
    'matching preserved slots' => [$matching['preserved_slots'], [1, 2]],
    'matching discards database uncommitted and beyond wal marks' => [$matching['discarded_slots'], [0, 3, 4]],
    'matching current reader frames' => [$matching['current_reader_frames'], [2, 4]],
    'matching checkpoint pinned by oldest reader' => [$matching['next_checkpoint_plan']['checkpoint_pinned_frame'], 2],
    'matching checkpoint cannot finish' => [$matching['next_checkpoint_plan']['checkpoint_can_finish'], false],
    'matching reset blocked' => [$matching['next_checkpoint_plan']['reset_blocked'], true],
    'matching reusable slots include stale and empty slots' => [$matching['next_checkpoint_plan']['reusable_slots'], [0, 1, 3, 4]],
    'matching next reader slot chooses first reusable' => [$matching['next_reader_slot'], 0],
    'matching next reader frame advances to last commit' => [$matching['next_reader_frame'], 4],
    'matching dependency includes recovery' => [in_array('wal-shm-readmark-recovery', $matching['dependencies'], true), true],
    'matching read mark zero is unused after rebuild' => [$matching['next_checkpoint_plan']['read_marks'][0]['reason'], 'unused_slot'],
    'matching read mark one pins older snapshot' => [$matching['next_checkpoint_plan']['read_marks'][1]['reason'], 'reader_pins_older_snapshot'],
    'matching read mark two pins latest commit' => [$matching['next_checkpoint_plan']['read_marks'][2]['reason'], 'pins_latest_commit'],
    'stale copy status rebuilds' => [$staleCopy['status'], 'rebuilt'],
    'stale copy reason names header copy' => [$staleCopy['reason'], 'stale_shm_header_copy_rebuilt_from_wal'],
    'stale copy reports header mismatch' => [$staleCopy['headers_match'], false],
    'stale copy keeps salt match' => [$staleCopy['salt_matches_wal'], true],
    'stale copy drops all reader frames' => [$staleCopy['next_read_marks'], [0, null, null, null, null]],
    'stale copy preserved slots empty' => [$staleCopy['preserved_slots'], []],
    'stale copy discarded locked slots' => [$staleCopy['discarded_slots'], [0, 1, 2]],
    'stale copy checkpoint can finish' => [$staleCopy['next_checkpoint_plan']['checkpoint_can_finish'], true],
    'stale copy reset not blocked' => [$staleCopy['next_checkpoint_plan']['reset_blocked'], false],
    'stale copy next reader slot zero' => [$staleCopy['next_reader_slot'], 0],
    'stale copy next reader frame four' => [$staleCopy['next_reader_frame'], 4],
    'salt mismatch status rebuilds' => [$saltMismatch['status'], 'rebuilt'],
    'salt mismatch reason names wal salt' => [$saltMismatch['reason'], 'shm_salt_mismatch_rebuilt_from_wal'],
    'salt mismatch headers still match' => [$saltMismatch['headers_match'], true],
    'salt mismatch reports false' => [$saltMismatch['salt_matches_wal'], false],
    'salt mismatch drops all reader frames' => [$saltMismatch['next_read_marks'], [0, null, null, null, null]],
    'salt mismatch discarded slots' => [$saltMismatch['discarded_slots'], [0, 1, 2]],
    'salt mismatch checkpoint can finish' => [$saltMismatch['next_checkpoint_plan']['checkpoint_can_finish'], true],
    'salt mismatch next reader frame last commit' => [$saltMismatch['next_reader_frame'], 4],
    'unlocked status rebuilds' => [$unlocked['status'], 'rebuilt'],
    'unlocked reason names no locked marks' => [$unlocked['reason'], 'no_locked_read_marks_to_preserve'],
    'unlocked marks reset to database reader' => [$unlocked['next_read_marks'], [0, null, null, null, null]],
    'unlocked preserved slots empty' => [$unlocked['preserved_slots'], []],
    'unlocked discarded database and mark slots' => [$unlocked['discarded_slots'], [0, 1, 2]],
    'unlocked checkpoint pinned null' => [$unlocked['next_checkpoint_plan']['checkpoint_pinned_frame'], null],
    'unlocked reusable slots include recycled database mark' => [$unlocked['next_checkpoint_plan']['reusable_slots'], [0, 1, 2, 3, 4]],
    'uncommitted-only status rebuilds' => [$uncommittedOnly['status'], 'rebuilt'],
    'uncommitted-only reason' => [$uncommittedOnly['reason'], 'wal_has_no_committed_frames'],
    'uncommitted-only last commit null' => [$uncommittedOnly['last_commit_frame'], null],
    'uncommitted-only wal mx frame' => [$uncommittedOnly['wal_mx_frame'], 2],
    'uncommitted-only backfill zero' => [$uncommittedOnly['backfilled_frame_count'], 0],
    'uncommitted-only read marks reset' => [$uncommittedOnly['next_read_marks'], [0, null, null, null, null]],
    'uncommitted-only discarded locked slots' => [$uncommittedOnly['discarded_slots'], [0, 1, 2]],
    'uncommitted-only next reader frame zero' => [$uncommittedOnly['next_reader_frame'], 0],
    'uncommitted-only checkpoint can finish' => [$uncommittedOnly['next_checkpoint_plan']['checkpoint_can_finish'], true],
    'uncommitted-only dependency keeps wal salt' => [in_array('sqlite-wal-frame-salt', $uncommittedOnly['dependencies'], true), true],
];

$tests = [];
foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal shm readmark recovery current next70 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual);
    };
}

return $tests;
