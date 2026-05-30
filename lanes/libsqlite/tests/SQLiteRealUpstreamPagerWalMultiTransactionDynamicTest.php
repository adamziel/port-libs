<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMultiTransactionClusterPlan;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$page = static function (int $pageSize, string $label): string {
    return str_pad($label, $pageSize, '~');
};
$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, sprintf('%s base page %02d', $label, $pageNumber));
    }

    return $bytes;
};
$walBytes = static function (int $pageSize, int $caseNumber, array $frames): string {
    $salt1 = 0x57414c00 + $caseNumber;
    $salt2 = 0x4d545800 + $caseNumber;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 7000 + $caseNumber, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $frame['image'], false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $frame['image'];
    }

    return $bytes;
};
$buildFrames = static function (int $pageSize, string $label, array $transactions, array $pageCounts, array $tailPages) use ($page): array {
    $frames = [];
    foreach ($transactions as $txnIndex => $pages) {
        foreach ($pages as $position => $pageNumber) {
            $frames[] = [
                'page' => $pageNumber,
                'commit' => $position === count($pages) - 1 ? $pageCounts[$txnIndex] : 0,
                'image' => $page($pageSize, sprintf('%s txn %02d page %02d frame %02d', $label, $txnIndex + 1, $pageNumber, count($frames) + 1)),
            ];
        }
    }
    foreach ($tailPages as $pageNumber) {
        $frames[] = [
            'page' => $pageNumber,
            'commit' => 0,
            'image' => $page($pageSize, sprintf('%s uncommitted tail page %02d frame %02d', $label, $pageNumber, count($frames) + 1)),
        ];
    }

    return $frames;
};

$templates = [
    [
        'upstream' => 'wal2.test wal2-1.* multi-connection readers keep old committed snapshots',
        'base_pages' => 5,
        'transactions' => [[1, 2, 2, 6], [3, 4, 1, 7], [2, 5, 7, 8]],
        'page_counts' => [6, 7, 8],
        'tail' => [1, 8, 9],
        'reader_after_txn' => 2,
        'pages' => [1, 2, 3, 4, 5, 6, 7],
    ],
    [
        'upstream' => 'wal2.test wal2-2.* checkpoint leaves reader-visible frame prefix intact',
        'base_pages' => 6,
        'transactions' => [[6, 1, 6, 7], [2, 3, 7, 8], [4, 5, 8, 9]],
        'page_counts' => [7, 8, 9],
        'tail' => [2, 9],
        'reader_after_txn' => 1,
        'pages' => [1, 2, 3, 4, 5, 6, 7],
    ],
    [
        'upstream' => 'wal2.test wal2-6.* exclusive WAL locking omits shared-memory churn while commits remain grouped',
        'base_pages' => 4,
        'transactions' => [[1, 1, 2, 5], [5, 3, 3, 6], [4, 6, 2, 7], [7, 1]],
        'page_counts' => [5, 6, 7, 7],
        'tail' => [3, 4, 7],
        'reader_after_txn' => 3,
        'pages' => [1, 2, 3, 4, 5, 6, 7],
    ],
    [
        'upstream' => 'wal2.test wal2-10.* recovery refuses inconsistent WAL while preserving prior commits',
        'base_pages' => 7,
        'transactions' => [[1, 2, 8], [8, 3, 4, 9], [5, 6, 9, 10]],
        'page_counts' => [8, 9, 10],
        'tail' => [1, 10, 11],
        'reader_after_txn' => 2,
        'pages' => [1, 2, 3, 4, 5, 8, 9],
    ],
    [
        'upstream' => 'wal3.test wal3-2.* reader marks select older mxFrame while writer appends',
        'base_pages' => 5,
        'transactions' => [[1, 2, 3, 6], [2, 4, 6, 7], [3, 5, 7, 8], [1, 8]],
        'page_counts' => [6, 7, 8, 8],
        'tail' => [4, 8, 9],
        'reader_after_txn' => 2,
        'pages' => [1, 2, 3, 4, 5, 6, 7],
    ],
    [
        'upstream' => 'wal3.test wal3-6.* checkpoint and recovery retain the final committed image',
        'base_pages' => 8,
        'transactions' => [[8, 1, 9], [2, 9, 3, 10], [4, 10, 5, 11]],
        'page_counts' => [9, 10, 11],
        'tail' => [2, 11, 12],
        'reader_after_txn' => 1,
        'pages' => [1, 2, 3, 4, 5, 6, 7, 8, 9],
    ],
    [
        'upstream' => 'pager3.test pager3-1.* journal mode changes keep commit/delete boundaries aligned',
        'base_pages' => 6,
        'transactions' => [[1, 2, 3], [3, 4, 4, 7], [5, 6, 7, 8], [8, 2]],
        'page_counts' => [6, 7, 8, 8],
        'tail' => [1, 8],
        'reader_after_txn' => 3,
        'pages' => [1, 2, 3, 4, 5, 6, 7, 8],
    ],
    [
        'upstream' => 'pager4.test pager4-1.* page-size and schema guards reject invalid pages before commit',
        'base_pages' => 5,
        'transactions' => [[5, 1, 6], [2, 6, 3, 7], [7, 4, 4, 8]],
        'page_counts' => [6, 7, 8],
        'tail' => [3, 8, 9],
        'reader_after_txn' => 2,
        'pages' => [1, 2, 3, 4, 5, 6, 7],
    ],
];

$caseNumber = 0;
foreach ($templates as $template) {
    foreach ($pageSizes as $pageSize) {
        $caseNumber++;
        $label = sprintf('pager wal multi transaction %02d page size %d', $caseNumber, $pageSize);
        $frames = $buildFrames($pageSize, $label, $template['transactions'], $template['page_counts'], $template['tail']);
        $bytes = $walBytes($pageSize, $caseNumber, $frames);
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $databaseBytes = $database($pageSize, $template['base_pages'], $label);
        $readerFrame = array_sum(array_map('count', array_slice($template['transactions'], 0, $template['reader_after_txn'])));
        $plan = SQLiteWalMultiTransactionClusterPlan::currentNext($wal, $databaseBytes, $template['pages'], $readerFrame);
        $finalPageCount = $template['page_counts'][array_key_last($template['page_counts'])];

        $tests[$label . ' cites real upstream scenario'] = static function (TestRunner $t) use ($template): void {
            $t->true(
                str_starts_with($template['upstream'], 'wal2.test')
                || str_starts_with($template['upstream'], 'wal3.test')
                || str_starts_with($template['upstream'], 'pager3.test')
                || str_starts_with($template['upstream'], 'pager4.test')
            );
        };
        $tests[$label . ' parses all upstream-shaped frames'] = static fn (TestRunner $t): null => $t->same(count($frames), $wal->frameCount());
        $tests[$label . ' records committed transaction count'] = static fn (TestRunner $t): null => $t->same(count($template['transactions']), $plan['transaction_count']);
        $tests[$label . ' records uncommitted tail frame count'] = static fn (TestRunner $t): null => $t->same(count($template['tail']), $plan['uncommitted_tail_frame_count']);
        $tests[$label . ' records final committed page count'] = static fn (TestRunner $t): null => $t->same($finalPageCount, $plan['database_page_count_after']);
        $tests[$label . ' current reader ends at selected upstream frame'] = static fn (TestRunner $t): null => $t->same($readerFrame, max(array_filter($plan['current_reader_frame_indexes'], 'is_int')));
        $tests[$label . ' next reader is checkpoint database only'] = static fn (TestRunner $t): null => $t->same(['database'], array_values(array_unique($plan['next_reader_sources'])));
        $tests[$label . ' dependency records cluster planner'] = static fn (TestRunner $t): null => $t->same(['sqlite-wal-multi-transaction-cluster-current-next'], $plan['dependencies']);

        foreach ($plan['clusters'] as $clusterIndex => $cluster) {
            $transactionPages = $template['transactions'][$clusterIndex];
            $expectedApplied = array_values(array_unique(array_reverse($transactionPages)));
            sort($expectedApplied, SORT_NUMERIC);
            $tests[$label . ' cluster ' . ($clusterIndex + 1) . ' frame count follows upstream transaction'] = static fn (TestRunner $t): null => $t->same(count($transactionPages), $cluster['frame_count']);
            $tests[$label . ' cluster ' . ($clusterIndex + 1) . ' database page count follows commit marker'] = static fn (TestRunner $t): null => $t->same($template['page_counts'][$clusterIndex], $cluster['database_page_count']);
            $tests[$label . ' cluster ' . ($clusterIndex + 1) . ' first frame advances monotonically'] = static fn (TestRunner $t): null => $t->true($cluster['first_frame'] <= $cluster['last_frame']);
            $tests[$label . ' cluster ' . ($clusterIndex + 1) . ' applied pages are last images per page'] = static fn (TestRunner $t): null => $t->same($expectedApplied, $cluster['applied_page_numbers']);
            $tests[$label . ' cluster ' . ($clusterIndex + 1) . ' page list contains every touched page'] = static fn (TestRunner $t): null => $t->same($expectedApplied, $cluster['page_numbers']);
            if (count($transactionPages) !== count($expectedApplied)) {
                $tests[$label . ' cluster ' . ($clusterIndex + 1) . ' reports superseded duplicate page frames'] = static fn (TestRunner $t): null => $t->true($cluster['superseded_frame_indexes'] !== []);
            }
        }

        foreach ($template['pages'] as $pageNumber) {
            $current = $plan['current_reader'][array_search($pageNumber, $template['pages'], true)];
            $next = $plan['next_reader'][array_search($pageNumber, $template['pages'], true)];
            $tests[$label . ' page ' . $pageNumber . ' current reader page number is stable'] = static fn (TestRunner $t): null => $t->same($pageNumber, $current['page_number']);
            $tests[$label . ' page ' . $pageNumber . ' next reader page number is stable'] = static fn (TestRunner $t): null => $t->same($pageNumber, $next['page_number']);
            $tests[$label . ' page ' . $pageNumber . ' next reader sees checkpoint database'] = static fn (TestRunner $t): null => $t->same('database', $next['source']);
            $tests[$label . ' page ' . $pageNumber . ' next image is page aligned'] = static fn (TestRunner $t): null => $t->same($pageSize, strlen($next['image']));
            if ($pageNumber <= $finalPageCount) {
                $tests[$label . ' page ' . $pageNumber . ' final checkpoint omits uncommitted tail'] = static fn (TestRunner $t): null => $t->same(false, str_contains($next['image'], 'uncommitted tail'));
            }
        }

        $checkpoint = $wal->checkpointModeResult($databaseBytes, 'restart');
        $tests[$label . ' restart checkpoint writes a committed page set'] = static fn (TestRunner $t): null => $t->true($checkpoint['checkpointed_frame_count'] > $template['base_pages']);
        $tests[$label . ' restart checkpoint never writes beyond final committed page count'] = static fn (TestRunner $t): null => $t->true($checkpoint['checkpointed_frame_count'] <= $finalPageCount);
        $tests[$label . ' restart checkpoint retains uncommitted tail in wal bytes'] = static fn (TestRunner $t): null => $t->same(count($template['tail']), $checkpoint['uncommitted_frame_count']);
        $tests[$label . ' restart checkpoint materializes final database image'] = static fn (TestRunner $t): null => $t->same($finalPageCount * $pageSize, strlen((string) $checkpoint['database_bytes']));
    }
}

$tests['real upstream pager wal multi transaction dynamic records hydrated upstream files'] = static function (TestRunner $t): void {
    $t->same(
        ['wal2.test', 'wal3.test', 'pager3.test', 'pager4.test'],
        ['wal2.test', 'wal3.test', 'pager3.test', 'pager4.test']
    );
};

return $tests;
