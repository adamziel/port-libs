<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundCteWindowOrderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20, 'priority' => 3],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 15, 'priority' => 2],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'no', 'bytes' => 40, 'priority' => 5],
    ['option_id' => 4, 'option_name' => 'plugin_alpha', 'autoload' => 'no', 'bytes' => 10, 'priority' => 4],
    ['option_id' => 5, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'bytes' => 8, 'priority' => 1],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'plugin_gamma', 'autoload' => 'yes', 'bytes' => 50, 'priority' => 8],
    ['option_id' => 7, 'option_name' => 'transient_cleanup', 'autoload' => 'no', 'bytes' => 35, 'priority' => 6],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH ranked AS MATERIALIZED (
    SELECT option_id, option_name, autoload, bytes, priority
      FROM wp_options
     WHERE bytes >= 8
),
yes_rows AS (
    SELECT option_id, option_name, bytes, priority
      FROM ranked
     WHERE autoload = 'yes'
),
no_rows AS (
    SELECT option_id, option_name, bytes, priority
      FROM ranked
     WHERE autoload = 'no'
)
SELECT option_id AS id,
       option_name AS name,
       row_number() OVER (
           ORDER BY priority DESC, option_id ASC
       ) AS source_rank,
       sum(bytes) OVER (
           ORDER BY priority DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_bytes
  FROM yes_rows
UNION ALL
SELECT option_id AS id,
       option_name AS name,
       row_number() OVER (
           ORDER BY priority DESC, option_id ASC
       ) AS source_rank,
       sum(bytes) OVER (
           ORDER BY priority DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_bytes
  FROM no_rows
 ORDER BY source_rank ASC, frame_bytes DESC, name ASC
 LIMIT 6
SQL;

$summary = static fn (): array => SQLiteCompoundCteWindowOrderCurrentSourceNextPlan::compareCteWindowOrder($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound cte window order current source status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-cte-window-order-current-source-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-with-materialized-cte',
        'sqlite-select-sql-compound-cte-arms',
        'sqlite-select-sql-window-order-from-cte',
        'sqlite-select-sql-compound-final-order',
    ], $plan['dependencies']);
};

$tests['compound cte window order current source compound shape'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['source_rank', 'frame_bytes', 'name'], $compound['orderColumns']);
    $t->same(['ASC', 'DESC', 'ASC'], $compound['orderDirections']);
    $t->same(6, $compound['limit']);
    $t->same(0, $compound['offset']);
};

$tests['compound cte window order current source cte metadata'] = static function (TestRunner $t) use ($summary): void {
    $cte = $summary()['cte'];
    $t->same(['ranked', 'yes_rows', 'no_rows'], $cte['current']);
    $t->same(['ranked', 'yes_rows', 'no_rows'], $cte['next']);
    $t->same(['ranked'], $cte['materialized']);
};

$tests['compound cte window order current source current ordered rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([3, 1, 2, 4, 5], array_column($rows, 'id'));
    $t->same(['theme_mods', 'siteurl', 'home', 'plugin_alpha', 'plugin_beta'], array_column($rows, 'name'));
    $t->same([1, 1, 2, 2, 3], array_column($rows, 'source_rank'));
    $t->same([50, 35, 23, 10, 8], array_column($rows, 'frame_bytes'));
};

$tests['compound cte window order current source next ordered rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([7, 6, 3, 1, 2, 4], array_column($rows, 'id'));
    $t->same(['transient_cleanup', 'plugin_gamma', 'theme_mods', 'siteurl', 'home', 'plugin_alpha'], array_column($rows, 'name'));
    $t->same([1, 1, 2, 2, 3, 3], array_column($rows, 'source_rank'));
    $t->same([75, 70, 50, 35, 23, 10], array_column($rows, 'frame_bytes'));
};

$tests['compound cte window order current source window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['row_number', 'sum', 'row_number', 'sum'], array_column($windows, 'function'));
    $t->same(['source_rank', 'frame_bytes', 'source_rank', 'frame_bytes'], $summary()['windows']['orderedAliases']);
    $t->same([2, 2, 2, 2], array_column($windows, 'orderCount'));
    $t->same([null, 'ROWS', null, 'ROWS'], array_column($windows, 'frameUnit'));
    $t->same([null, 0, null, 0], array_column($windows, 'preceding'));
    $t->same([null, 1, null, 1], array_column($windows, 'following'));
};

$tests['compound cte window order current source boundary shifts'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['orderBoundary'];
    $t->same(3, $boundary['currentFirst']['id']);
    $t->same(7, $boundary['nextFirst']['id']);
    $t->same(5, $boundary['currentLast']['id']);
    $t->same(4, $boundary['nextLast']['id']);
    $t->same(5, $boundary['currentCount']);
    $t->same(6, $boundary['nextCount']);
};

$tests['compound cte window order current source changed signatures name next rows'] = static function (TestRunner $t) use ($summary): void {
    $changed = implode("\n", $summary()['changedSignatures']);
    $t->true(str_contains($changed, 'plugin_gamma'));
    $t->true(str_contains($changed, 'transient_cleanup'));
    $t->true(str_contains($changed, '"frame_bytes":75'));
};

$tests['compound cte window order current source replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->true(in_array('compound-cte-rowset-changed', $reasons, true));
    $t->true(in_array('cte-materialized-source', $reasons, true));
    $t->true(in_array('window-order-source', $reasons, true));
    $t->true(in_array('compound-final-order', $reasons, true));
};

foreach (range(1, 48) as $variant) {
    $tests['compound cte window order current source generated ordered cte variant ' . $variant] = static function (TestRunner $t) use ($variant, $currentTables): void {
        $minimumBytes = 6 + ($variant % 8);
        $limit = 2 + ($variant % 4);
        $direction = $variant % 2 === 0 ? 'DESC' : 'ASC';
        $sql = "WITH ranked AS MATERIALIZED (SELECT option_id, option_name, autoload, bytes, priority FROM wp_options WHERE bytes >= {$minimumBytes}), yes_rows AS (SELECT option_id, option_name, bytes, priority FROM ranked WHERE autoload = 'yes'), no_rows AS (SELECT option_id, option_name, bytes, priority FROM ranked WHERE autoload = 'no') SELECT option_id AS id, option_name AS name, row_number() OVER (ORDER BY priority {$direction}, option_id ASC) AS source_rank, sum(bytes) OVER (ORDER BY priority {$direction}, option_id ASC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_bytes FROM yes_rows UNION ALL SELECT option_id AS id, option_name AS name, row_number() OVER (ORDER BY priority {$direction}, option_id ASC) AS source_rank, sum(bytes) OVER (ORDER BY priority {$direction}, option_id ASC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_bytes FROM no_rows ORDER BY source_rank ASC, frame_bytes DESC, name ASC LIMIT {$limit}";
        $rows = SQLiteSelectSql::execute($sql, $currentTables);

        $t->same(min($limit, count($rows)), count($rows));
        $t->true(count($rows) >= 2);
        $t->true(isset($rows[0]['id'], $rows[0]['name'], $rows[0]['source_rank'], $rows[0]['frame_bytes']));
        $t->true($rows[0]['source_rank'] <= $rows[count($rows) - 1]['source_rank']);
    };
}

$tests['compound cte window order current source rejects non compound select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundCteWindowOrderCurrentSourceNextPlan::compareCteWindowOrder(
        'WITH ranked AS MATERIALIZED (SELECT option_id FROM wp_options) SELECT option_id AS id FROM ranked ORDER BY id',
        $currentTables,
        $currentTables,
    ));
};

return $tests;
