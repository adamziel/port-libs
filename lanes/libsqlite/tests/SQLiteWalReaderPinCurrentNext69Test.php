<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db69 page1 schema before')
    . $page('db69 page2 option database')
    . $page('db69 page3 autoload database')
    . $page('db69 page4 transient database');
$databasePath = '/tmp/wp-reader-pin-current-next69.sqlite';
$salt1 = 0x69010002;
$salt2 = 0x69030004;

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 69, $salt1, $salt2);
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
    [2, 0, $page('wal69 frame1 old siteurl')],
    [3, 4, $page('wal69 frame2 old autoload commit')],
    [2, 4, $page('wal69 frame3 current siteurl commit')],
]), null, true);

$transactions = [
    [
        'pages' => [
            2 => $page('wal69 frame4 next siteurl commit'),
            4 => $page('wal69 frame5 transient next commit'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ],
];

$databasePinned = static fn (): array => SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    [0, null, 3],
    0,
    'restart',
);

$walPinned = static fn (): array => SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    [null, 2, null, 3],
    1,
    'truncate',
);

$fullSlots = static fn (): array => SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    [0, 2, 3],
    1,
    'restart',
);

$uncommittedOnly = static fn (): array => SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    [
        [
            'pages' => [
                2 => $page('wal69 frame4 uncommitted only'),
            ],
            'database_page_count' => 4,
            'commit' => false,
        ],
    ],
    [2, 3],
    [0, null, 3],
    0,
    'restart',
);

$cases = [
    'database pin status advances next reader' => [static fn (): mixed => $databasePinned()['status'], 'current-reader-slot-pinned-next-reader-advanced'],
    'database pin reason' => [static fn (): mixed => $databasePinned()['reason'], 'database_reader_pin_preserved_across_wal_append'],
    'database pin current slot' => [static fn (): mixed => $databasePinned()['current_reader_slot'], 0],
    'database pin current frame zero' => [static fn (): mixed => $databasePinned()['current_reader_end_frame'], 0],
    'database pin next frame committed append' => [static fn (): mixed => $databasePinned()['next_reader_end_frame'], 5],
    'database pin chooses unused next slot' => [static fn (): mixed => $databasePinned()['next_reader_slot'], 1],
    'database pin read marks preserved' => [static fn (): mixed => $databasePinned()['current_read_marks'], [0, null, 3]],
    'database pin next read marks assign committed frame' => [static fn (): mixed => $databasePinned()['next_read_marks'], [0, 5, 3]],
    'database pin release clears current slot only' => [static fn (): mixed => $databasePinned()['release_read_marks'], [null, 5, 3]],
    'database pin current sources stay database' => [static fn (): mixed => $databasePinned()['current_reader_sources'], ['database', 'database', 'database']],
    'database pin next sources use wal and database' => [static fn (): mixed => $databasePinned()['next_reader_sources'], ['wal', 'wal', 'wal']],
    'database pin current frame indexes empty' => [static fn (): mixed => $databasePinned()['current_reader_frame_indexes'], [null, null, null]],
    'database pin next frame indexes' => [static fn (): mixed => $databasePinned()['next_reader_frame_indexes'], [4, 2, 5]],
    'database pin current errors empty' => [static fn (): mixed => $databasePinned()['current_reader_errors'], []],
    'database pin next errors empty' => [static fn (): mixed => $databasePinned()['next_reader_errors'], []],
    'database pin page two current image' => [static fn (): mixed => str_contains($databasePinned()['current_reader'][0]['image'], 'option database'), true],
    'database pin page two next image' => [static fn (): mixed => str_contains($databasePinned()['next_reader'][0]['image'], 'next siteurl'), true],
    'database pin page four next image' => [static fn (): mixed => str_contains($databasePinned()['next_reader'][2]['image'], 'transient next'), true],
    'database pin snapshot stable' => [static fn (): mixed => $databasePinned()['current_snapshot_stable'], true],
    'database pin next snapshot advances' => [static fn (): mixed => $databasePinned()['next_snapshot_advances'], true],
    'database pin blocks checkpoint reset' => [static fn (): mixed => $databasePinned()['current_slot_blocks_checkpoint_reset'], true],
    'database pin release cannot reset due other stale reader' => [static fn (): mixed => $databasePinned()['release_allows_checkpoint_reset'], false],
    'database pin checkpoint busy reason' => [static fn (): mixed => $databasePinned()['checkpoint_with_current_pin']['reason'], 'reader_blocks_checkpoint_completion'],
    'database pin checkpoint preserves wal' => [static fn (): mixed => $databasePinned()['checkpoint_with_current_pin']['wal_action'], 'preserve_wal'],
    'database pin release checkpoint still busy on stale slot' => [static fn (): mixed => $databasePinned()['checkpoint_after_release']['busy'], true],
    'database pin release checkpoint reader frame' => [static fn (): mixed => $databasePinned()['checkpoint_after_release']['reader_end_frame'], 3],
    'database pin hidden frames include whole wal' => [static fn (): mixed => $databasePinned()['frames_hidden_from_current'], [1, 2, 3, 4, 5]],
    'database pin visible frames include committed append' => [static fn (): mixed => $databasePinned()['frames_visible_to_next'], [1, 2, 3, 4, 5]],
    'database pin append starts at four' => [static fn (): mixed => $databasePinned()['append']['start_frame'], 4],
    'database pin append ends at five' => [static fn (): mixed => $databasePinned()['append']['end_frame'], 5],
    'database pin append has one committed transaction' => [static fn (): mixed => $databasePinned()['append']['committed_transaction_count'], 1],
    'database pin dependency marker' => [static fn (): mixed => in_array('sqlite-wal-reader-slot-pin-current-next69', $databasePinned()['dependencies'], true), true],
    'database pin readmark dependency marker' => [static fn (): mixed => in_array('sqlite-wal-readmark-handoff', $databasePinned()['dependencies'], true), true],
    'wal pin status advances next reader' => [static fn (): mixed => $walPinned()['status'], 'current-reader-slot-pinned-next-reader-advanced'],
    'wal pin reason' => [static fn (): mixed => $walPinned()['reason'], 'wal_reader_slot_pin_preserved_across_wal_append'],
    'wal pin current frame' => [static fn (): mixed => $walPinned()['current_reader_end_frame'], 2],
    'wal pin next frame' => [static fn (): mixed => $walPinned()['next_reader_end_frame'], 5],
    'wal pin next read marks' => [static fn (): mixed => $walPinned()['next_read_marks'], [5, 2, null, 3]],
    'wal pin release read marks' => [static fn (): mixed => $walPinned()['release_read_marks'], [5, null, null, 3]],
    'wal pin current sources' => [static fn (): mixed => $walPinned()['current_reader_sources'], ['wal', 'wal', 'database']],
    'wal pin next sources' => [static fn (): mixed => $walPinned()['next_reader_sources'], ['wal', 'wal', 'wal']],
    'wal pin current frame indexes' => [static fn (): mixed => $walPinned()['current_reader_frame_indexes'], [1, 2, null]],
    'wal pin next frame indexes' => [static fn (): mixed => $walPinned()['next_reader_frame_indexes'], [4, 2, 5]],
    'wal pin blocks truncate reset' => [static fn (): mixed => $walPinned()['current_slot_blocks_checkpoint_reset'], true],
    'wal pin release still blocked by slot three' => [static fn (): mixed => $walPinned()['release_allows_checkpoint_reset'], false],
    'wal pin checkpoint mode' => [static fn (): mixed => $walPinned()['checkpoint_with_current_pin']['mode'], 'truncate'],
    'full slots has no next slot' => [static fn (): mixed => $fullSlots()['next_reader_slot'], null],
    'full slots next read marks unchanged' => [static fn (): mixed => $fullSlots()['next_read_marks'], [0, 2, 3]],
    'full slots next frame still advances' => [static fn (): mixed => $fullSlots()['next_reader_end_frame'], 5],
    'uncommitted only status' => [static fn (): mixed => $uncommittedOnly()['status'], 'current-reader-slot-pinned-no-committed-next'],
    'uncommitted only next frame absent' => [static fn (): mixed => $uncommittedOnly()['next_reader_end_frame'], 3],
    'uncommitted only committed count zero' => [static fn (): mixed => $uncommittedOnly()['append']['committed_transaction_count'], 0],
    'uncommitted only reason preserves database pin' => [static fn (): mixed => $uncommittedOnly()['reason'], 'database_reader_pin_preserved_across_wal_append'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader pin current next69 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader pin current next69 rejects empty page list'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [], [0], 0));
};

$tests['wal reader pin current next69 rejects missing slot'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [2], [0], 2));
};

$tests['wal reader pin current next69 rejects inactive slot'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [2], [null], 0));
};

$tests['wal reader pin current next69 rejects reader frame beyond wal'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [2], [9], 0));
};

$tests['wal reader pin current next69 rejects bad mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [2], [0], 0, 'passive'));
};

$tests['wal reader pin current next69 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, ['2'], [0], 0));
};

return $tests;
