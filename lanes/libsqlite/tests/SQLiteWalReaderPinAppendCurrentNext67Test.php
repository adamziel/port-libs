<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db67 schema before')
    . $page('db67 wp_options siteurl before')
    . $page('db67 wp_options autoload before')
    . $page('db67 wp_postmeta before');
$databasePath = '/tmp/wp-reader-pin-current-next67.sqlite';
$salt1 = 0x67010002;
$salt2 = 0x67030004;

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 67, $salt1, $salt2);
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
    [2, 0, $page('wal67 frame1 siteurl pinned')],
    [3, 4, $page('wal67 frame2 autoload commit')],
    [2, 4, $page('wal67 frame3 siteurl committed')],
]), null, true);

$transactions = [
    [
        'pages' => [
            2 => $page('wal67 frame4 siteurl next import'),
            4 => $page('wal67 frame5 postmeta next import'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ],
    [
        'pages' => [
            3 => $page('wal67 frame6 autoload uncommitted'),
        ],
        'database_page_count' => 4,
        'commit' => false,
    ],
];

$pinned = static fn (): array => SQLiteWalAppendPlan::readerPinAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    [0, 2, null, null],
    'restart',
);

$fullPinned = static fn (): array => SQLiteWalAppendPlan::readerPinAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    [2, 3, 3],
    'restart',
);

$unpinned = static fn (): array => SQLiteWalAppendPlan::readerPinAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    [0, 3, null],
    'truncate',
);

$uncommittedOnly = static fn (): array => SQLiteWalAppendPlan::readerPinAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    [
        [
            'pages' => [2 => $page('wal67 frame4 uncommitted only')],
            'database_page_count' => 4,
            'commit' => false,
        ],
    ],
    [2, 3],
    [0, 2, null],
    'restart',
);

$cases = [
    'pinned status advances next reader' => [static fn (): mixed => $pinned()['status'], 'current-reader-pinned-next-reader-advanced'],
    'pinned reason' => [static fn (): mixed => $pinned()['reason'], 'wal_append_preserves_current_pin_and_assigns_next_reader'],
    'pinned database path' => [static fn (): mixed => $pinned()['database_path'], $databasePath],
    'pinned wal path' => [static fn (): mixed => $pinned()['wal_path'], $databasePath . '-wal'],
    'pinned current frame' => [static fn (): mixed => $pinned()['current_reader_end_frame'], 2],
    'pinned next frame' => [static fn (): mixed => $pinned()['next_reader_end_frame'], 5],
    'pinned next slot is first unused' => [static fn (): mixed => $pinned()['next_reader_slot'], 2],
    'pinned current read marks preserved' => [static fn (): mixed => $pinned()['current_read_marks'], [0, 2, null, null]],
    'pinned next read mark assigned' => [static fn (): mixed => $pinned()['next_read_marks'], [0, 2, 5, null]],
    'pinned release clears old pin' => [static fn (): mixed => $pinned()['release_read_marks'], [0, null, 5, null]],
    'pinned current plan pinned frame' => [static fn (): mixed => $pinned()['current_read_mark_plan']['checkpoint_pinned_frame'], 2],
    'pinned current plan reset blocked' => [static fn (): mixed => $pinned()['current_read_mark_plan']['reset_blocked'], true],
    'pinned next plan still pinned by old reader' => [static fn (): mixed => $pinned()['next_read_mark_plan']['checkpoint_pinned_frame'], 2],
    'pinned release plan no checkpoint pin' => [static fn (): mixed => $pinned()['release_read_mark_plan']['checkpoint_pinned_frame'], null],
    'pinned checkpoint before release busy' => [static fn (): mixed => $pinned()['checkpoint_before_release']['busy'], true],
    'pinned checkpoint before release reason' => [static fn (): mixed => $pinned()['checkpoint_before_release']['reason'], 'reader_blocks_checkpoint_completion'],
    'pinned checkpoint before release preserves wal' => [static fn (): mixed => $pinned()['checkpoint_before_release']['wal_action'], 'preserve_wal'],
    'pinned checkpoint after release ready' => [static fn (): mixed => $pinned()['checkpoint_after_release']['busy'], false],
    'pinned checkpoint after release keeps uncommitted tail' => [static fn (): mixed => $pinned()['checkpoint_after_release']['reason'], 'uncommitted_frames_after_last_commit'],
    'pinned checkpoint after release preserves wal tail' => [static fn (): mixed => $pinned()['checkpoint_after_release']['wal_action'], 'preserve_wal'],
    'pinned checkpoint after release keeps full wal bytes' => [static fn (): mixed => $pinned()['checkpoint_after_release']['wal_bytes_length'], 3248],
    'pinned append starts at frame four' => [static fn (): mixed => $pinned()['append']['start_frame'], 4],
    'pinned append ends at frame six' => [static fn (): mixed => $pinned()['append']['end_frame'], 6],
    'pinned append has three frames' => [static fn (): mixed => $pinned()['append']['appended_frame_count'], 3],
    'pinned append has one committed transaction' => [static fn (): mixed => $pinned()['append']['committed_transaction_count'], 1],
    'pinned append has one uncommitted transaction' => [static fn (): mixed => $pinned()['append']['uncommitted_transaction_count'], 1],
    'pinned current sources' => [static fn (): mixed => $pinned()['current_reader_sources'], ['wal', 'wal', 'database']],
    'pinned next sources' => [static fn (): mixed => $pinned()['next_reader_sources'], ['wal', 'wal', 'wal']],
    'pinned current frame indexes' => [static fn (): mixed => $pinned()['current_reader_frame_indexes'], [1, 2, null]],
    'pinned next frame indexes' => [static fn (): mixed => $pinned()['next_reader_frame_indexes'], [4, 2, 5]],
    'pinned current errors empty' => [static fn (): mixed => $pinned()['current_reader_errors'], []],
    'pinned next errors empty' => [static fn (): mixed => $pinned()['next_reader_errors'], []],
    'pinned current page two old image' => [static fn (): mixed => str_contains($pinned()['current_reader'][0]['image'], 'pinned'), true],
    'pinned next page two import image' => [static fn (): mixed => str_contains($pinned()['next_reader'][0]['image'], 'next import'), true],
    'pinned current page four database image' => [static fn (): mixed => str_contains($pinned()['current_reader'][2]['image'], 'postmeta before'), true],
    'pinned next page four wal image' => [static fn (): mixed => str_contains($pinned()['next_reader'][2]['image'], 'postmeta next'), true],
    'pinned current snapshot stable' => [static fn (): mixed => $pinned()['current_snapshot_stable'], true],
    'pinned next snapshot advances' => [static fn (): mixed => $pinned()['next_snapshot_advances'], true],
    'pinned blocks checkpoint' => [static fn (): mixed => $pinned()['current_pin_blocks_checkpoint'], true],
    'pinned release still cannot reset uncommitted tail' => [static fn (): mixed => $pinned()['release_allows_checkpoint_reset'], false],
    'pinned hidden frames include append and uncommitted tail' => [static fn (): mixed => $pinned()['frames_hidden_from_current'], [3, 4, 5, 6]],
    'pinned next visible frames omit uncommitted tail' => [static fn (): mixed => $pinned()['frames_visible_to_next'], [1, 2, 3, 4, 5]],
    'pinned dependency marker' => [static fn (): mixed => in_array('sqlite-wal-reader-pin-append-current-next67', $pinned()['dependencies'], true), true],
    'pinned append dependency marker' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $pinned()['dependencies'], true), true],
    'pinned readmark dependency marker' => [static fn (): mixed => in_array('sqlite-wal-readmark-handoff', $pinned()['dependencies'], true), true],
    'full pinned has no next slot' => [static fn (): mixed => $fullPinned()['next_reader_slot'], null],
    'full pinned next marks unchanged' => [static fn (): mixed => $fullPinned()['next_read_marks'], [2, 3, 3]],
    'full pinned release clears only original blocking pin' => [static fn (): mixed => $fullPinned()['release_read_marks'], [null, 3, 3]],
    'unpinned status' => [static fn (): mixed => $unpinned()['status'], 'no-current-reader-pin'],
    'unpinned current frame latest base' => [static fn (): mixed => $unpinned()['current_reader_end_frame'], 3],
    'unpinned next frame appended commit' => [static fn (): mixed => $unpinned()['next_reader_end_frame'], 5],
    'unpinned after append old mark becomes busy' => [static fn (): mixed => $unpinned()['checkpoint_before_release']['busy'], true],
    'unpinned after append preserves wal for stale reader' => [static fn (): mixed => $unpinned()['checkpoint_before_release']['wal_action'], 'preserve_wal'],
    'unpinned release still blocked by old latest reader' => [static fn (): mixed => $unpinned()['release_allows_checkpoint_reset'], false],
    'uncommitted-only reason' => [static fn (): mixed => $uncommittedOnly()['reason'], 'wal_append_has_no_committed_next_reader_frame'],
    'uncommitted-only next remains base commit' => [static fn (): mixed => $uncommittedOnly()['next_reader_end_frame'], 3],
    'uncommitted-only next does not advance images' => [static fn (): mixed => $uncommittedOnly()['next_snapshot_advances'], true],
    'uncommitted-only append has no commits' => [static fn (): mixed => $uncommittedOnly()['append']['committed_transaction_count'], 0],
    'uncommitted-only append has one uncommitted transaction' => [static fn (): mixed => $uncommittedOnly()['append']['uncommitted_transaction_count'], 1],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader pin append current next67 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader pin append current next67 rejects empty pages'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerPinAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [], [0, 2]));
};

$tests['wal reader pin append current next67 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerPinAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, ['2'], [0, 2]));
};

$tests['wal reader pin append current next67 rejects unsupported mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerPinAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [2], [0, 2], 'passive'));
};

$tests['wal reader pin append current next67 rejects negative read mark'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerPinAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [2], [-1]));
};

return $tests;
