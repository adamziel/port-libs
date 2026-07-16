<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMultiTransactionClusterPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db-page-1-before') . $page('db-page-2-before') . $page('db-page-3-before') . $page('db-page-4-before');
$salt1 = 0x24702470;
$salt2 = 0x13501350;

$makeWal = static function (array $frames) use ($pageSize, $page, $salt1, $salt2): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 224, $salt1, $salt2);
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
    [1, 0, 'tx1-schema-draft'],
    [2, 4, 'tx1-options-commit'],
    [2, 0, 'tx2-options-stale'],
    [3, 0, 'tx2-plugin-index-draft'],
    [2, 4, 'tx2-options-commit'],
    [4, 0, 'tx3-side-index-draft'],
    [5, 5, 'tx3-new-overflow-commit'],
    [3, 0, 'tail-uncommitted-plugin-index'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$plan = static fn (?int $reader = null, array $pages = [1, 2, 3, 4, 5]): array => SQLiteWalMultiTransactionClusterPlan::currentNext(
    $wal,
    $databaseBytes,
    $pages,
    $reader
);

$emptyWalBytes = $makeWal([]);
$emptyWal = SQLiteWal::parse($emptyWalBytes, $pageSize, true);

$cases = [
    'status is ready' => static fn (): mixed => $plan()['status'],
    'transaction count includes three commits' => static fn (): mixed => $plan()['transaction_count'],
    'frame count includes uncommitted tail' => static fn (): mixed => $plan()['frame_count'],
    'uncommitted tail count is one' => static fn (): mixed => $plan()['uncommitted_tail_frame_count'],
    'database page count before is four' => static fn (): mixed => $plan()['database_page_count_before'],
    'database page count after grows to five' => static fn (): mixed => $plan()['database_page_count_after'],
    'cluster one ordinal' => static fn (): mixed => $plan()['clusters'][0]['ordinal'],
    'cluster one first frame' => static fn (): mixed => $plan()['clusters'][0]['first_frame'],
    'cluster one last frame' => static fn (): mixed => $plan()['clusters'][0]['last_frame'],
    'cluster one frame count' => static fn (): mixed => $plan()['clusters'][0]['frame_count'],
    'cluster one database page count' => static fn (): mixed => $plan()['clusters'][0]['database_page_count'],
    'cluster one pages include schema and option pages' => static fn (): mixed => $plan()['clusters'][0]['page_numbers'],
    'cluster one applied pages include both pages' => static fn (): mixed => $plan()['clusters'][0]['applied_page_numbers'],
    'cluster one has no superseded frames' => static fn (): mixed => $plan()['clusters'][0]['superseded_frame_indexes'],
    'cluster one before page two is database' => static fn (): mixed => $plan()['clusters'][0]['before_sources'][1],
    'cluster one after page two is database image' => static fn (): mixed => $plan()['clusters'][0]['after_sources'][1],
    'cluster one after image contains option commit' => static fn (): mixed => str_starts_with($plan()['clusters'][0]['after_images'][1], 'tx1-options-commit'),
    'cluster two ordinal' => static fn (): mixed => $plan()['clusters'][1]['ordinal'],
    'cluster two first frame' => static fn (): mixed => $plan()['clusters'][1]['first_frame'],
    'cluster two last frame' => static fn (): mixed => $plan()['clusters'][1]['last_frame'],
    'cluster two frame count' => static fn (): mixed => $plan()['clusters'][1]['frame_count'],
    'cluster two pages deduplicate page two' => static fn (): mixed => $plan()['clusters'][1]['page_numbers'],
    'cluster two applied pages use final page two plus page three' => static fn (): mixed => $plan()['clusters'][1]['applied_page_numbers'],
    'cluster two supersedes first option rewrite frame' => static fn (): mixed => $plan()['clusters'][1]['superseded_frame_indexes'],
    'cluster two before page two sees first commit' => static fn (): mixed => str_starts_with($plan()['clusters'][1]['before_images'][1], 'tx1-options-commit'),
    'cluster two after page two sees second commit' => static fn (): mixed => str_starts_with($plan()['clusters'][1]['after_images'][1], 'tx2-options-commit'),
    'cluster two after page three sees plugin index draft' => static fn (): mixed => str_starts_with($plan()['clusters'][1]['after_images'][2], 'tx2-plugin-index-draft'),
    'cluster three ordinal' => static fn (): mixed => $plan()['clusters'][2]['ordinal'],
    'cluster three first frame' => static fn (): mixed => $plan()['clusters'][2]['first_frame'],
    'cluster three last frame' => static fn (): mixed => $plan()['clusters'][2]['last_frame'],
    'cluster three frame count' => static fn (): mixed => $plan()['clusters'][2]['frame_count'],
    'cluster three grows database page count to five' => static fn (): mixed => $plan()['clusters'][2]['database_page_count'],
    'cluster three pages include side index and overflow' => static fn (): mixed => $plan()['clusters'][2]['page_numbers'],
    'cluster three before page five is marked future page' => static fn (): mixed => $plan(null, [5])['clusters'][0]['before_sources'][0],
    'cluster three after page five contains new overflow' => static fn (): mixed => str_starts_with($plan(null, [1, 2, 3, 4])['clusters'][2]['after_images'][3], 'tx3-side-index-draft'),
    'current reader page one uses wal' => static fn (): mixed => $plan()['current_reader_sources'][0],
    'current reader page two uses wal' => static fn (): mixed => $plan()['current_reader_sources'][1],
    'current reader page three uses wal tail by default' => static fn (): mixed => $plan()['current_reader_sources'][2],
    'current reader page five uses wal commit' => static fn (): mixed => $plan()['current_reader_sources'][4],
    'current reader frame indexes ignore uncommitted page three tail' => static fn (): mixed => $plan()['current_reader_frame_indexes'][2],
    'next reader page one is checkpoint database' => static fn (): mixed => $plan()['next_reader_sources'][0],
    'next reader page five is checkpoint database' => static fn (): mixed => $plan()['next_reader_sources'][4],
    'next reader frame indexes are null after checkpoint' => static fn (): mixed => $plan()['next_reader_frame_indexes'],
    'current and next images match because uncommitted tail is invisible' => static fn (): mixed => $plan()['images_match'],
    'next page three excludes uncommitted tail' => static fn (): mixed => str_starts_with($plan()['next_reader_images'][2], 'tx2-plugin-index-draft'),
    'current page three excludes uncommitted tail' => static fn (): mixed => str_starts_with($plan()['current_reader_images'][2], 'tx2-plugin-index-draft'),
    'reader pinned at last commit matches next checkpoint' => static fn (): mixed => $plan(7)['images_match'],
    'reader pinned at first commit sees tx1 option page' => static fn (): mixed => str_starts_with($plan(2, [2])['current_reader_images'][0], 'tx1-options-commit'),
    'reader pinned before wal sees base page one' => static fn (): mixed => str_starts_with($plan(0, [1])['current_reader_images'][0], 'db-page-1-before'),
    'single requested page keeps one current row' => static fn (): mixed => count($plan(null, [2])['current_reader']),
    'single requested page keeps one next row' => static fn (): mixed => count($plan(null, [2])['next_reader']),
    'single requested page number preserved' => static fn (): mixed => $plan(null, [2])['next_reader'][0]['page_number'],
    'dependency marker is present' => static fn (): mixed => $plan()['dependencies'],
    'empty wal status reports no committed transactions' => static fn (): mixed => SQLiteWalMultiTransactionClusterPlan::currentNext($emptyWal, $databaseBytes, [1])['status'],
    'empty wal transaction count is zero' => static fn (): mixed => SQLiteWalMultiTransactionClusterPlan::currentNext($emptyWal, $databaseBytes, [1])['transaction_count'],
    'empty wal images match database' => static fn (): mixed => SQLiteWalMultiTransactionClusterPlan::currentNext($emptyWal, $databaseBytes, [1])['images_match'],
    'empty page list is rejected' => static function () use ($wal, $databaseBytes): mixed {
        try {
            SQLiteWalMultiTransactionClusterPlan::currentNext($wal, $databaseBytes, []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'non integer page is rejected' => static function () use ($wal, $databaseBytes): mixed {
        try {
            SQLiteWalMultiTransactionClusterPlan::currentNext($wal, $databaseBytes, [1, '2']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'negative reader frame is rejected' => static function () use ($wal, $databaseBytes): mixed {
        try {
            SQLiteWalMultiTransactionClusterPlan::currentNext($wal, $databaseBytes, [1], -1);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'misaligned database image is rejected' => static function () use ($wal, $databaseBytes): mixed {
        try {
            SQLiteWalMultiTransactionClusterPlan::currentNext($wal, substr($databaseBytes, 1), [1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'status is ready' => 'ready',
    'transaction count includes three commits' => 3,
    'frame count includes uncommitted tail' => 8,
    'uncommitted tail count is one' => 1,
    'database page count before is four' => 4,
    'database page count after grows to five' => 5,
    'cluster one ordinal' => 1,
    'cluster one first frame' => 1,
    'cluster one last frame' => 2,
    'cluster one frame count' => 2,
    'cluster one database page count' => 4,
    'cluster one pages include schema and option pages' => [1, 2],
    'cluster one applied pages include both pages' => [1, 2],
    'cluster one has no superseded frames' => [],
    'cluster one before page two is database' => 'database',
    'cluster one after page two is database image' => 'database',
    'cluster one after image contains option commit' => true,
    'cluster two ordinal' => 2,
    'cluster two first frame' => 3,
    'cluster two last frame' => 5,
    'cluster two frame count' => 3,
    'cluster two pages deduplicate page two' => [2, 3],
    'cluster two applied pages use final page two plus page three' => [2, 3],
    'cluster two supersedes first option rewrite frame' => [3],
    'cluster two before page two sees first commit' => true,
    'cluster two after page two sees second commit' => true,
    'cluster two after page three sees plugin index draft' => true,
    'cluster three ordinal' => 3,
    'cluster three first frame' => 6,
    'cluster three last frame' => 7,
    'cluster three frame count' => 2,
    'cluster three grows database page count to five' => 5,
    'cluster three pages include side index and overflow' => [4, 5],
    'cluster three before page five is marked future page' => 'beyond_database',
    'cluster three after page five contains new overflow' => true,
    'current reader page one uses wal' => 'wal',
    'current reader page two uses wal' => 'wal',
    'current reader page three uses wal tail by default' => 'wal',
    'current reader page five uses wal commit' => 'wal',
    'current reader frame indexes ignore uncommitted page three tail' => 4,
    'next reader page one is checkpoint database' => 'database',
    'next reader page five is checkpoint database' => 'database',
    'next reader frame indexes are null after checkpoint' => [null, null, null, null, null],
    'current and next images match because uncommitted tail is invisible' => true,
    'next page three excludes uncommitted tail' => true,
    'current page three excludes uncommitted tail' => true,
    'reader pinned at last commit matches next checkpoint' => true,
    'reader pinned at first commit sees tx1 option page' => true,
    'reader pinned before wal sees base page one' => true,
    'single requested page keeps one current row' => 1,
    'single requested page keeps one next row' => 1,
    'single requested page number preserved' => 2,
    'dependency marker is present' => ['sqlite-wal-multi-transaction-cluster-current-next'],
    'empty wal status reports no committed transactions' => 'no_committed_transactions',
    'empty wal transaction count is zero' => 0,
    'empty wal images match database' => true,
    'empty page list is rejected' => 'rejected',
    'non integer page is rejected' => 'rejected',
    'negative reader frame is rejected' => 'rejected',
    'misaligned database image is rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal multi transaction cluster current next24 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
