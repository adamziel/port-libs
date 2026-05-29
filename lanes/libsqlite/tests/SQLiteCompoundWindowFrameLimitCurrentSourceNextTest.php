<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowFrameLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 9],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 8],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 7],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 6],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 5],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'no', 'weight' => 4],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 7, 'option_name' => 'plugin_alpha', 'autoload' => 'no', 'weight' => 10],
    ['option_id' => 8, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'weight' => 11],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
SELECT option_id AS id,
       option_name AS label,
       last_value(option_name) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_tail,
       sum(weight) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'yes'
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       first_value(option_name) OVER (
           ORDER BY option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_tail,
       sum(weight) OVER (
           ORDER BY option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'no'
 ORDER BY frame_weight DESC, id ASC
 LIMIT 5 OFFSET 1
SQL;

$summary = static fn (): array => SQLiteCompoundWindowFrameLimitCurrentSourceNextPlan::compareWindowFrameLimit($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound window frame limit current-source status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-window-frame-limit-current-source-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-compound-tail-limit',
        'sqlite-select-sql-window-current-row-frame',
        'sqlite-select-sql-current-source-next-rowset',
    ], $plan['dependencies']);
};

$tests['compound window frame limit current-source compound shape'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['frame_weight', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound window frame limit current-source current limited rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([2, 4, 5, 3, 6], array_column($rows, 'id'));
    $t->same(['blogname', 'active_plugins', 'rewrite_rules', 'blogname', 'theme_mods'], array_column($rows, 'frame_tail'));
    $t->same([15, 11, 9, 7, 4], array_column($rows, 'frame_weight'));
};

$tests['compound window frame limit current-source next limited rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([1, 2, 6, 4, 7], array_column($rows, 'id'));
    $t->same(['home', 'blogname', 'theme_mods', 'active_plugins', 'plugin_alpha'], array_column($rows, 'frame_tail'));
    $t->same([24, 15, 14, 11, 10], array_column($rows, 'frame_weight'));
};

$tests['compound window frame limit current-source window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['last_value', 'sum', 'first_value', 'sum'], array_column($windows, 'function'));
    $t->same(['ROWS', 'ROWS', 'ROWS', 'ROWS'], array_column($windows, 'frameUnit'));
    $t->same([0, 0, 0, 0], array_column($windows, 'preceding'));
    $t->same([1, 2, 1, 1], array_column($windows, 'following'));
    $t->same(['NO OTHERS', 'NO OTHERS', 'NO OTHERS', 'NO OTHERS'], array_column($windows, 'exclude'));
};

$tests['compound window frame limit current-source limit boundary changes with next source'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['limitBoundary'];
    $t->same(5, $boundary['currentCount']);
    $t->same(5, $boundary['nextCount']);
    $t->same(6, $boundary['currentLast']['id']);
    $t->same(7, $boundary['nextLast']['id']);
};

$tests['compound window frame limit current-source changed signatures name plugin rows'] = static function (TestRunner $t) use ($summary): void {
    $changed = implode("\n", $summary()['changedSignatures']);
    $t->true(str_contains($changed, 'plugin_alpha'));
    $t->true(str_contains($changed, '"id":7'));
    $t->true(str_contains($changed, '"frame_weight":24'));
};

$tests['compound window frame limit current-source replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->true(in_array('limited-compound-rowset-changed', $reasons, true));
    $t->true(in_array('compound-window-frame-source', $reasons, true));
    $t->true(in_array('compound-tail-limit', $reasons, true));
};

foreach (range(1, 42) as $offset) {
    $tests['compound window frame limit current-source generated offset ' . $offset] = static function (TestRunner $t) use ($offset, $currentTables): void {
        $limit = 3 + ($offset % 4);
        $start = $offset % 3;
        $sql = "SELECT option_id AS id, option_name AS label, last_value(option_name) OVER (ORDER BY weight DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_tail, sum(weight) OVER (ORDER BY weight DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_weight FROM wp_options WHERE autoload = 'yes' UNION ALL SELECT option_id AS id, option_name AS label, first_value(option_name) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_tail, sum(weight) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_weight FROM wp_options WHERE autoload = 'no' ORDER BY frame_weight DESC, id LIMIT {$limit} OFFSET {$start}";
        $rows = SQLiteSelectSql::execute($sql, $currentTables);

        $t->same(min($limit, 6 - $start), count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['frame_tail'], $rows[0]['frame_weight']));
        $t->true($rows[0]['frame_weight'] >= $rows[count($rows) - 1]['frame_weight']);
    };
}

$tests['compound window frame limit current-source rejects non compound select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowFrameLimitCurrentSourceNextPlan::compareWindowFrameLimit(
        'SELECT option_id AS id FROM wp_options LIMIT 1',
        $currentTables,
        $currentTables,
    ));
};

return $tests;
