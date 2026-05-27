<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteTempStoreSorterBTreePlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'autoload_theme', 'autoload' => 'yes', 'bucket' => 'theme', 'priority' => 30, 'payload' => 'a'],
    ['option_id' => 2, 'option_name' => 'autoload_theme_copy', 'autoload' => 'yes', 'bucket' => 'theme', 'priority' => 20, 'payload' => 'b'],
    ['option_id' => 3, 'option_name' => 'cache_plugins', 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 40, 'payload' => 'c'],
    ['option_id' => 4, 'option_name' => 'cache_plugins_old', 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 10, 'payload' => 'd'],
    ['option_id' => 5, 'option_name' => 'network_rules', 'autoload' => 'yes', 'bucket' => 'network', 'priority' => 50, 'payload' => 'e'],
    ['option_id' => 6, 'option_name' => 'orphaned_rule', 'autoload' => null, 'bucket' => null, 'priority' => 60, 'payload' => 'f'],
    ['option_id' => 7, 'option_name' => 'binary_a', 'autoload' => 'no', 'bucket' => new SQLiteBlobValue('AB'), 'priority' => 70, 'payload' => 'g'],
    ['option_id' => 8, 'option_name' => 'binary_b', 'autoload' => 'no', 'bucket' => new SQLiteBlobValue('AB'), 'priority' => 65, 'payload' => 'h'],
];

return [
    'yields distinct sorter rows after order with offset and limit' => static function (TestRunner $t) use ($rows): void {
        $plan = SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows(
            $rows,
            [
                ['column' => 'priority', 'direction' => 'DESC'],
                ['column' => 'option_name', 'collation' => 'NOCASE'],
            ],
            ['bucket'],
            limit: 3,
            offset: 1,
            pageSize: 2048,
            memoryThresholdBytes: 96,
        );
        $summary = $plan->toArray();

        $t->true($plan->spilledToTempBTree);
        $t->same([7, 8, 6, 5, 3, 1, 2, 4], array_column($plan->sortedRows, 'option_id'));
        $t->same([6, 5, 3], array_column($plan->yieldedRows, 'option_id'));
        $t->same([null, 'network', 'plugin'], array_map(static fn (array $row): mixed => $row['bucket'], $plan->yieldedRows));
        $t->same(['bucket'], $plan->distinctColumns);
        $t->same(3, $plan->limit);
        $t->same(1, $plan->offset);
        $t->same(4, $plan->distinctRowsSeen);
        $t->same(1, $plan->duplicateRowsSkipped);
        $t->same(3, $summary['yielded_rows']);
        $t->same(['bucket'], $summary['distinct_columns']);
        $t->same(3, $summary['limit']);
        $t->same(1, $summary['offset']);
        $t->same(4, $summary['distinct_rows_seen']);
        $t->same(1, $summary['duplicate_rows_skipped']);
        $t->same([2], $summary['temp_page_numbers']);
        $t->same(2048, strlen($plan->pageImages[2]));

        $header = SQLiteBTreePageHeader::parsePage($plan->pageImages[2], 2048);
        $cells = SQLiteIndexCell::parsePageCells($plan->pageImages[2], $header, 2048);
        $records = array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, $cells);

        $t->same('index-leaf', $header->pageType);
        $t->same(8, $header->cellCount);
        $t->same(70, $records[0][0]);
        $t->same('binary_a', $records[0][1]);
        $t->same(65, $records[1][0]);
        $t->same('binary_b', $records[1][1]);
    },
    'uses distinct keys without spilling when the sorter stays in memory' => static function (TestRunner $t) use ($rows): void {
        $plan = SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows(
            $rows,
            [['column' => 'option_name']],
            ['autoload', 'bucket'],
            limit: null,
            offset: 0,
            memoryThresholdBytes: 8192,
        );

        $t->true(!$plan->spilledToTempBTree);
        $t->same([], $plan->pageImages);
        $t->same([], $plan->runs);
        $t->same([1, 7, 3, 5, 6], array_column($plan->yieldedRows, 'option_id'));
        $t->same(5, $plan->distinctRowsSeen);
        $t->same(3, $plan->duplicateRowsSkipped);
        $t->same(null, $plan->limit);
        $t->same(0, $plan->offset);
    },
    'stops yielding after zero limit but still validates sorted distinct input' => static function (TestRunner $t) use ($rows): void {
        $plan = SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows(
            $rows,
            [['column' => 'priority', 'direction' => 'DESC']],
            ['bucket'],
            limit: 0,
            pageSize: 2048,
        );

        $t->same([], $plan->yieldedRows);
        $t->same(0, $plan->distinctRowsSeen);
        $t->same(0, $plan->duplicateRowsSkipped);
        $t->same([7, 8, 6], array_slice(array_column($plan->sortedRows, 'option_id'), 0, 3));
    },
    'rejects malformed sorter distinct limit yield requests' => static function (TestRunner $t) use ($rows): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows($rows, [['column' => 'priority']], [], 1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows($rows, [['column' => 'priority']], ['bucket'], 1, -1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows($rows, [['column' => 'priority']], ['bucket'], -1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows($rows, [['column' => 'priority']], [''], 1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows($rows, [['column' => 'priority']], ['missing'], 1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows([['option_name' => 'bad', 'bucket' => ['nested']]], [['column' => 'option_name']], ['bucket'], 1));
    },
];
