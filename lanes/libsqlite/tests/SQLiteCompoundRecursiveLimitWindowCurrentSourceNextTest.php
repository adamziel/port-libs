<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 20],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 18],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 16],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 14],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 12],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 19],
    ['option_id' => 7, 'option_name' => 'plugin_queue', 'autoload' => 'no', 'weight' => 15],
];
$currentEdges = [
    ['src' => 1, 'dst' => 2, 'weight' => 18],
    ['src' => 2, 'dst' => 3, 'weight' => 16],
    ['src' => 3, 'dst' => 4, 'weight' => 14],
    ['src' => 4, 'dst' => 5, 'weight' => 12],
];
$nextEdges = [
    ['src' => 1, 'dst' => 6, 'weight' => 19],
    ['src' => 6, 'dst' => 2, 'weight' => 18],
    ['src' => 2, 'dst' => 3, 'weight' => 16],
    ['src' => 3, 'dst' => 7, 'weight' => 15],
    ['src' => 7, 'dst' => 4, 'weight' => 14],
    ['src' => 4, 'dst' => 5, 'weight' => 12],
];

$currentTables = ['wp_options' => $currentOptions, 'wp_option_edges' => $currentEdges];
$nextTables = ['wp_options' => $nextOptions, 'wp_option_edges' => $nextEdges];

$sql = <<<'SQL'
WITH RECURSIVE wanted(id, label, depth, weight) AS MATERIALIZED (
    VALUES (1, 'siteurl', 0, 20)
    UNION ALL
    SELECT wp_option_edges.dst, wp_options.option_name, wanted.depth + 1, wp_option_edges.weight
      FROM wanted
      JOIN wp_option_edges ON wp_option_edges.src = wanted.id
      JOIN wp_options ON wp_options.option_id = wp_option_edges.dst
     WHERE wanted.depth < 8
     ORDER BY 4 DESC
     LIMIT 5
)
SELECT id,
       label,
       depth,
       last_value(label) OVER (
           ORDER BY weight DESC, id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_tail,
       sum(weight) OVER (
           ORDER BY weight DESC, id ASC
           ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
       ) AS frame_weight
  FROM wanted
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       99 AS depth,
       first_value(option_name) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS frame_tail,
       sum(weight) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'no'
 ORDER BY frame_weight DESC, id ASC
 LIMIT 6 OFFSET 1
SQL;

$summary = static fn (): array => SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan::compare($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound recursive limit window current-source next status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-recursive-limit-window-current-source-next-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-queue-limit',
        'sqlite-select-sql-compound-tail-limit',
        'sqlite-select-sql-window-current-following-frame',
        'sqlite-select-sql-current-source-next-rowset',
    ], $plan['dependencies']);
};

$tests['compound recursive limit window current-source next compound shape'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['frame_weight', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound recursive limit window current-source next current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([2, 3, 4, 4, 5, 5], array_column($rows, 'id'));
    $t->same(['blogname', 'active_plugins', 'rewrite_rules', 'active_plugins', 'rewrite_rules', 'rewrite_rules'], array_column($rows, 'frame_tail'));
    $t->same([48, 42, 26, 26, 12, 12], array_column($rows, 'frame_weight'));
};

$tests['compound recursive limit window current-source next next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([6, 2, 3, 7, 4, 7], array_column($rows, 'id'));
    $t->same(['home', 'blogname', 'plugin_queue', 'plugin_queue', 'active_plugins', 'plugin_queue'], array_column($rows, 'frame_tail'));
    $t->same([53, 49, 31, 29, 26, 15], array_column($rows, 'frame_weight'));
};

$tests['compound recursive limit window current-source next recursive limit trace'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('wanted', $recursive['name']);
    $t->same(['id', 'label', 'depth', 'weight'], $recursive['columns']);
    $t->same([1, 2, 3, 4, 5], array_column($recursive['currentRows'], 'id'));
    $t->same([1, 6, 2, 3, 7], array_column($recursive['nextRows'], 'id'));
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['nextLimitRemaining']);
};

$tests['compound recursive limit window current-source next window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['last_value', 'sum', 'first_value', 'sum'], array_column($windows, 'function'));
    $t->same(['ROWS', 'ROWS', 'ROWS', 'ROWS'], array_column($windows, 'frameUnit'));
    $t->same([0, 0, 0, 0], array_column($windows, 'preceding'));
    $t->same([1, 2, 0, 1], array_column($windows, 'following'));
};

$tests['compound recursive limit window current-source next limit boundary'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['limitBoundary'];
    $t->same(6, $boundary['currentCount']);
    $t->same(6, $boundary['nextCount']);
    $t->same(2, $boundary['currentFirst']['id']);
    $t->same(6, $boundary['nextFirst']['id']);
    $t->same(5, $boundary['currentLast']['id']);
    $t->same(7, $boundary['nextLast']['id']);
};

$tests['compound recursive limit window current-source next changed signatures'] = static function (TestRunner $t) use ($summary): void {
    $changed = implode("\n", $summary()['changedSignatures']);
    $t->true(str_contains($changed, 'theme_mods'));
    $t->true(str_contains($changed, 'plugin_queue'));
    $t->true(str_contains($changed, '"frame_weight":53'));
};

$tests['compound recursive limit window current-source next replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->true(in_array('recursive-limited-compound-rowset-changed', $reasons, true));
    $t->true(in_array('compound-window-current-following-source', $reasons, true));
    $t->true(in_array('compound-tail-limit', $reasons, true));
};

foreach (range(1, 42) as $case) {
    $tests['compound recursive limit window current-source next generated limit case ' . $case] = static function (TestRunner $t) use ($case, $nextTables): void {
        $recursiveLimit = 3 + ($case % 4);
        $tailLimit = 2 + ($case % 5);
        $offset = $case % 2;
        $sql = "WITH RECURSIVE wanted(id, label, depth, weight) AS (VALUES (1, 'siteurl', 0, 20) UNION ALL SELECT wp_option_edges.dst, wp_options.option_name, wanted.depth + 1, wp_option_edges.weight FROM wanted JOIN wp_option_edges ON wp_option_edges.src = wanted.id JOIN wp_options ON wp_options.option_id = wp_option_edges.dst WHERE wanted.depth < 8 ORDER BY 4 DESC LIMIT {$recursiveLimit}) SELECT id, label, depth, last_value(label) OVER (ORDER BY weight DESC, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_tail, sum(weight) OVER (ORDER BY weight DESC, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_weight FROM wanted UNION ALL SELECT option_id AS id, option_name AS label, 99 AS depth, first_value(option_name) OVER (ORDER BY weight DESC, option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS frame_tail, sum(weight) OVER (ORDER BY weight DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_weight FROM wp_options WHERE autoload = 'no' ORDER BY frame_weight DESC, id ASC LIMIT {$tailLimit} OFFSET {$offset}";
        $rows = SQLiteSelectSql::execute($sql, $nextTables);

        $t->true(count($rows) <= $tailLimit);
        $t->true(isset($rows[0]['id'], $rows[0]['frame_tail'], $rows[0]['frame_weight']));
        $t->true($rows[0]['frame_weight'] >= $rows[count($rows) - 1]['frame_weight']);
    };
}

$tests['compound recursive limit window current-source next rejects non recursive select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan::compare(
        'SELECT option_id AS id FROM wp_options UNION ALL SELECT option_id AS id FROM wp_options LIMIT 1',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound recursive limit window current-source next rejects non compound select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan::compare(
        "WITH RECURSIVE wanted(id, label, depth, weight) AS (VALUES (1, 'siteurl', 0, 20)) SELECT id, label, depth FROM wanted",
        $currentTables,
        $currentTables,
    ));
};

return $tests;
