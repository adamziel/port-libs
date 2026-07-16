<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$settings = '{"plugins":[{"site":"main","slug":"seo","priority":9,"enabled":true},{"site":"main","slug":"cache","priority":5,"enabled":true},{"site":"main","slug":"forms","priority":5,"enabled":false},{"site":"network","slug":"mu","priority":7,"enabled":true},{"site":"network","slug":"media","priority":3,"enabled":false},{"site":"network","slug":"spam","priority":3,"enabled":true}]}';
$constraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'],
    ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
];
$orderBy = [
    ['column' => 'path', 'direction' => 'ASC'],
    ['column' => 'atom', 'direction' => 'DESC'],
];
$partitionBy = ['path'];

$pairs = static fn (): array => SQLiteJsonTablePlan::rankedAdjacentRows('json_tree', $constraints, $orderBy, $partitionBy, 3);
$rows = static fn (): array => array_column($pairs(), 'current');
$column = static fn (string $column): array => array_column($rows(), $column);

$tests = [
    'json table window rank current next32 returns one pair per priority row' => static fn (TestRunner $t) => $t->same(6, count($pairs())),
    'json table window rank current next32 preserves ordered priority atoms' => static fn (TestRunner $t) => $t->same([9, 5, 5, 7, 3, 3], $column('atom')),
    'json table window rank current next32 preserves fullkeys' => static fn (TestRunner $t) => $t->same(['$.plugins[0].priority', '$.plugins[1].priority', '$.plugins[2].priority', '$.plugins[3].priority', '$.plugins[4].priority', '$.plugins[5].priority'], $column('fullkey')),
    'json table window rank current next32 preserves partition paths' => static fn (TestRunner $t) => $t->same(['$.plugins[0]', '$.plugins[1]', '$.plugins[2]', '$.plugins[3]', '$.plugins[4]', '$.plugins[5]'], $column('path')),
    'json table window rank current next32 single-row path partitions rank each first' => static fn (TestRunner $t) => $t->same([1, 1, 1, 1, 1, 1], $column('window_rank')),
    'json table window rank current next32 single-row path partitions dense rank each first' => static fn (TestRunner $t) => $t->same([1, 1, 1, 1, 1, 1], $column('window_dense_rank')),
    'json table window rank current next32 single-row partitions have no next row' => static fn (TestRunner $t) => $t->same([null, null, null, null, null, null], array_column($pairs(), 'next')),
    'json table window rank current next32 single-row partitions have no next index' => static fn (TestRunner $t) => $t->same([null, null, null, null, null, null], array_column($pairs(), 'nextIndex')),
    'json table window rank current next32 single-row partitions report no same peer' => static fn (TestRunner $t) => $t->same([false, false, false, false, false, false], array_column($pairs(), 'samePeer')),
    'json table window rank current next32 single-row partitions report no same partition next' => static fn (TestRunner $t) => $t->same([false, false, false, false, false, false], array_column($pairs(), 'samePartition')),
    'json table window rank current next32 current indexes are stable' => static fn (TestRunner $t) => $t->same([0, 1, 2, 3, 4, 5], array_column($pairs(), 'currentIndex')),
    'json table window rank current next32 partition keys are json encoded values' => static fn (TestRunner $t) => $t->same(['["$.plugins[0]"]', '["$.plugins[1]"]', '["$.plugins[2]"]', '["$.plugins[3]"]', '["$.plugins[4]"]', '["$.plugins[5]"]'], array_column($pairs(), 'partitionKey')),
];

$allPriorityConstraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'],
    ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
];
$globalPairs = static fn (): array => SQLiteJsonTablePlan::rankedAdjacentRows('json_tree', $allPriorityConstraints, [
    ['column' => 'atom', 'direction' => 'DESC'],
], [], 2);
$globalRows = static fn (): array => array_column($globalPairs(), 'current');
$globalColumn = static fn (string $column): array => array_column($globalRows(), $column);

$tests += [
    'json table window rank current next32 global order preserves priorities' => static fn (TestRunner $t) => $t->same([9, 7, 5, 5, 3, 3], $globalColumn('atom')),
    'json table window rank current next32 global rank handles ties' => static fn (TestRunner $t) => $t->same([1, 2, 3, 3, 5, 5], $globalColumn('window_rank')),
    'json table window rank current next32 global dense rank handles ties' => static fn (TestRunner $t) => $t->same([1, 2, 3, 3, 4, 4], $globalColumn('window_dense_rank')),
    'json table window rank current next32 global row numbers are sequential' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4, 5, 6], $globalColumn('window_row_number')),
    'json table window rank current next32 global cume dist keeps peer end' => static fn (TestRunner $t) => $t->same([1 / 6, 2 / 6, 4 / 6, 4 / 6, 1.0, 1.0], $globalColumn('window_cume_dist')),
    'json table window rank current next32 global percent rank keeps peer rank' => static fn (TestRunner $t) => $t->same([0.0, 0.2, 0.4, 0.4, 0.8, 0.8], $globalColumn('window_percent_rank')),
    'json table window rank current next32 global ntile uses larger first bucket' => static fn (TestRunner $t) => $t->same([1, 1, 1, 2, 2, 2], $globalColumn('window_ntile')),
    'json table window rank current next32 global lag follows sorted atoms' => static fn (TestRunner $t) => $t->same([null, 9, 7, 5, 5, 3], $globalColumn('window_lag')),
    'json table window rank current next32 global lead follows sorted atoms' => static fn (TestRunner $t) => $t->same([7, 5, 5, 3, 3, null], $globalColumn('window_lead')),
    'json table window rank current next32 global first value is maximum priority' => static fn (TestRunner $t) => $t->same([9, 9, 9, 9, 9, 9], $globalColumn('window_first_value')),
    'json table window rank current next32 global last value is minimum priority' => static fn (TestRunner $t) => $t->same([3, 3, 3, 3, 3, 3], $globalColumn('window_last_value')),
    'json table window rank current next32 global next ranks expose tie transitions' => static fn (TestRunner $t) => $t->same([2, 3, 3, 5, 5, null], array_column($globalPairs(), 'nextRank')),
    'json table window rank current next32 global same peer detects first tie' => static fn (TestRunner $t) => $t->same([false, false, true, false, true, false], array_column($globalPairs(), 'samePeer')),
    'json table window rank current next32 global same partition links until eof' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, false], array_column($globalPairs(), 'samePartition')),
    'json table window rank current next32 global terminal pair has null next' => static fn (TestRunner $t) => $t->same(null, $globalPairs()[5]['next']),
    'json table window rank current next32 global current rank summary is exposed' => static fn (TestRunner $t) => $t->same([1, 2, 3, 3, 5, 5], array_column($globalPairs(), 'currentRank')),
    'json table window rank current next32 global next indexes are stable' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4, 5, null], array_column($globalPairs(), 'nextIndex')),
    'json table window rank current next32 global first next atom is seven' => static fn (TestRunner $t) => $t->same(7, $globalPairs()[0]['next']['atom']),
    'json table window rank current next32 global tied priority points to peer fullkey' => static fn (TestRunner $t) => $t->same('$.plugins[2].priority', $globalPairs()[2]['next']['fullkey']),
    'json table window rank current next32 global peer row keeps equal rank' => static fn (TestRunner $t) => $t->same(3, $globalPairs()[2]['next']['window_rank']),
    'json table window rank current next32 global nonpeer row advances rank' => static fn (TestRunner $t) => $t->same(5, $globalPairs()[3]['next']['window_rank']),
    'json table window rank current next32 global partition key marks whole result' => static fn (TestRunner $t) => $t->same(['__all__'], array_values(array_unique(array_column($globalPairs(), 'partitionKey')))),
];

$siteRows = [
    ['site' => 'main', 'slug' => 'seo', 'priority' => 9],
    ['site' => 'main', 'slug' => 'cache', 'priority' => 5],
    ['site' => 'main', 'slug' => 'forms', 'priority' => 5],
    ['site' => 'network', 'slug' => 'mu', 'priority' => 7],
    ['site' => 'network', 'slug' => 'media', 'priority' => 3],
    ['site' => 'network', 'slug' => 'spam', 'priority' => 3],
];
$siteSettings = json_encode(['plugins' => $siteRows], JSON_THROW_ON_ERROR);
$sitePriorityRows = static function () use ($siteSettings, $siteRows): array {
    $rows = SQLiteJsonTablePlan::filteredRows('json_tree', [
        ['column' => 'json', 'operator' => '=', 'value' => $siteSettings],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'],
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ]);

    return array_map(static function (array $row) use ($siteRows): array {
        preg_match('/^\$\.plugins\[(\d+)\]\.priority$/', (string) $row['fullkey'], $match);
        $source = $siteRows[(int) $match[1]];

        return $row + ['site' => $source['site'], 'slug' => $source['slug']];
    }, $rows);
};
$sitePairs = static function () use ($sitePriorityRows): array {
    $rows = $sitePriorityRows();
    usort($rows, static fn (array $left, array $right): int => [$left['site'], -$left['atom'], $left['slug']] <=> [$right['site'], -$right['atom'], $right['slug']]);
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[$row['site']][] = $index;
    }
    foreach ($partitions as $indexes) {
        $rank = 1;
        $dense = 1;
        foreach ($indexes as $position => $index) {
            if ($position > 0 && $rows[$indexes[$position - 1]]['atom'] !== $rows[$index]['atom']) {
                $rank = $position + 1;
                $dense++;
            }
            $rows[$index]['window_rank'] = $rank;
            $rows[$index]['window_dense_rank'] = $dense;
        }
    }

    $pairs = [];
    foreach ($rows as $index => $row) {
        $next = $rows[$index + 1] ?? null;
        $same = $next !== null && $row['site'] === $next['site'];
        $pairs[] = [
            'current' => $row,
            'next' => $same ? $next : null,
            'currentRank' => $row['window_rank'],
            'nextRank' => $same ? $next['window_rank'] : null,
            'samePeer' => $same && $row['window_rank'] === $next['window_rank'],
            'samePartition' => $same,
        ];
    }

    return $pairs;
};

$tests += [
    'json table window rank current next32 site rows keep slugs' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms', 'mu', 'media', 'spam'], array_column(array_column($sitePairs(), 'current'), 'slug')),
    'json table window rank current next32 site rows keep partitions' => static fn (TestRunner $t) => $t->same(['main', 'main', 'main', 'network', 'network', 'network'], array_column(array_column($sitePairs(), 'current'), 'site')),
    'json table window rank current next32 site partition ranks restart' => static fn (TestRunner $t) => $t->same([1, 2, 2, 1, 2, 2], array_column($sitePairs(), 'currentRank')),
    'json table window rank current next32 site next ranks stop at boundary' => static fn (TestRunner $t) => $t->same([2, 2, null, 2, 2, null], array_column($sitePairs(), 'nextRank')),
    'json table window rank current next32 site same peer only within ties' => static fn (TestRunner $t) => $t->same([false, true, false, false, true, false], array_column($sitePairs(), 'samePeer')),
    'json table window rank current next32 site same partition stops before network' => static fn (TestRunner $t) => $t->same([true, true, false, true, true, false], array_column($sitePairs(), 'samePartition')),
    'json table window rank current next32 site terminal main row has no next' => static fn (TestRunner $t) => $t->same(null, $sitePairs()[2]['next']),
    'json table window rank current next32 site terminal network row has no next' => static fn (TestRunner $t) => $t->same(null, $sitePairs()[5]['next']),
    'json table window rank current next32 site peer exposes next slug' => static fn (TestRunner $t) => $t->same('forms', $sitePairs()[1]['next']['slug']),
    'json table window rank current next32 site network peer exposes next slug' => static fn (TestRunner $t) => $t->same('spam', $sitePairs()[4]['next']['slug']),
];

$tests += [
    'json table window rank current next32 rejects missing order by' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::rankedAdjacentRows('json_tree', $constraints, [])),
    'json table window rank current next32 rejects zero ntile buckets' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::rankedAdjacentRows('json_tree', $constraints, $orderBy, [], 0)),
    'json table window rank current next32 rejects missing value column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::rankedAdjacentRows('json_tree', $constraints, $orderBy, [], 1, 'missing')),
    'json table window rank current next32 empty filtered json source returns empty pairs' => static fn (TestRunner $t) => $t->same([], SQLiteJsonTablePlan::rankedAdjacentRows('json_tree', [['column' => 'json', 'operator' => '=', 'value' => '{"plugins":[]}'], ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'], ['column' => 'key', 'operator' => '=', 'value' => 'priority']], [['column' => 'id']])),
    'json table window rank current next32 sql null json source returns empty pairs' => static fn (TestRunner $t) => $t->same([], SQLiteJsonTablePlan::rankedAdjacentRows('json_tree', [['column' => 'json', 'operator' => '=', 'value' => null]], [['column' => 'id']])),
    'json table window rank current next32 respects hidden limit before ranking order' => static fn (TestRunner $t) => $t->same([9, 5, 5], array_column(array_column(SQLiteJsonTablePlan::rankedAdjacentRows('json_tree', array_merge($allPriorityConstraints, [['column' => 'limit', 'operator' => '=', 'value' => 3]]), [['column' => 'atom', 'direction' => 'DESC']]), 'current'), 'atom')),
    'json table window rank current next32 respects offset before ranking' => static fn (TestRunner $t) => $t->same([7, 5, 5, 3, 3], array_column(array_column(SQLiteJsonTablePlan::rankedAdjacentRows('json_tree', array_merge($allPriorityConstraints, [['column' => 'offset', 'operator' => '=', 'value' => 1]]), [['column' => 'atom', 'direction' => 'DESC']]), 'current'), 'atom')),
    'json table window rank current next32 accepts json each arrays' => static fn (TestRunner $t) => $t->same([4, 4, 2, 1], array_column(array_column(SQLiteJsonTablePlan::rankedAdjacentRows('json_each', [['column' => 'json', 'operator' => '=', 'value' => '[4,4,2,1]']], [['column' => 'atom', 'direction' => 'DESC']]), 'current'), 'atom')),
    'json table window rank current next32 json each tie ranks' => static fn (TestRunner $t) => $t->same([1, 1, 3, 4], array_column(array_column(SQLiteJsonTablePlan::rankedAdjacentRows('json_each', [['column' => 'json', 'operator' => '=', 'value' => '[4,4,2,1]']], [['column' => 'atom', 'direction' => 'DESC']]), 'current'), 'window_rank')),
    'json table window rank current next32 json each tie peer flag' => static fn (TestRunner $t) => $t->same([true, false, false, false], array_column(SQLiteJsonTablePlan::rankedAdjacentRows('json_each', [['column' => 'json', 'operator' => '=', 'value' => '[4,4,2,1]']], [['column' => 'atom', 'direction' => 'DESC']]), 'samePeer')),
];

return $tests;
