<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'parent_id' => null, 'priority' => 90],
        ['option_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'parent_id' => 1, 'priority' => 88],
        ['option_id' => 3, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'parent_id' => 2, 'priority' => 70],
        ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'parent_id' => 1, 'priority' => 60],
        ['option_id' => 5, 'option_name' => 'widget_text', 'autoload' => 'yes', 'parent_id' => 4, 'priority' => 50],
        ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'parent_id' => 1, 'priority' => 40],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 7, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'parent_id' => 1, 'priority' => 95],
        ['option_id' => 8, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'parent_id' => 7, 'priority' => 85],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE ranked(id, name, priority, depth) AS (
    VALUES (1, 'siteurl', 90, 0)
    UNION ALL
    SELECT option_id, option_name, wp_options.priority, depth + 1
      FROM wp_options JOIN ranked ON parent_id = id
     WHERE autoload = 'yes'
     ORDER BY priority DESC, name
     LIMIT 5
)
SELECT id, name, priority, 'recursive' AS source FROM ranked
UNION ALL
SELECT option_id AS id, option_name AS name, priority, 'autoload' AS source
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY priority DESC, name
 LIMIT 6 OFFSET 1
SQL;

$summary = static fn (): array => SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan::compareRecursiveOrderLimit($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound recursive order limit current source next146 status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-recursive-order-limit-current-source-next146-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-priority-queue-order',
        'sqlite-recursive-cte-queue-limit-before-compound',
        'sqlite-compound-final-order-limit-after-current-source',
        'sqlite-current-source-next-boundary',
    ], $plan['dependencies']);
};

$tests['compound recursive order limit current source next146 compound tail metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['id', 'name', 'priority', 'source'], $compound['leftColumns']);
    $t->same(6, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->same(['priority', 'name'], array_column($compound['orderBy'], 'column'));
    $t->same(['DESC'], array_values(array_filter(array_column($compound['orderBy'], 'direction'))));
};

$tests['compound recursive order limit current source next146 current final boundary'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['siteurl', 'active_plugins', 'active_plugins', 'plugin_cache', 'plugin_cache', 'theme_mods'], array_column($rows, 'name'));
    $t->same(['autoload', 'recursive', 'autoload', 'recursive', 'autoload', 'recursive'], array_column($rows, 'source'));
    $t->same([90, 88, 88, 70, 70, 60], array_column($rows, 'priority'));
};

$tests['compound recursive order limit current source next146 next final boundary'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['plugin_alpha', 'siteurl', 'siteurl', 'active_plugins', 'active_plugins', 'plugin_beta'], array_column($rows, 'name'));
    $t->same(['autoload', 'recursive', 'autoload', 'recursive', 'autoload', 'recursive'], array_column($rows, 'source'));
    $t->same([95, 90, 90, 88, 88, 85], array_column($rows, 'priority'));
};

$tests['compound recursive order limit current source next146 recursive visit order is priority limited'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('ranked', $recursive['name']);
    $t->same(['id', 'name', 'priority', 'depth'], $recursive['columns']);
    $t->same(['siteurl', 'active_plugins', 'plugin_cache', 'theme_mods', 'widget_text'], $recursive['currentVisitOrder']);
    $t->same(['siteurl', 'plugin_alpha', 'active_plugins', 'plugin_beta', 'plugin_cache'], $recursive['nextVisitOrder']);
    $t->same(5, $recursive['currentTraceCount']);
    $t->same(5, $recursive['nextTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['nextLimitRemaining']);
};

$tests['compound recursive order limit current source next146 queue snapshots show priority reorder'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same(['active_plugins', 'theme_mods'], $recursive['currentQueueAfter'][0]);
    $t->same(['plugin_alpha', 'active_plugins', 'theme_mods'], $recursive['nextQueueAfter'][0]);
    $t->same(['active_plugins', 'plugin_beta', 'theme_mods'], $recursive['nextQueueAfter'][1]);
};

$tests['compound recursive order limit current source next146 current next boundary delta'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['boundary'];
    $t->same(['siteurl', 'active_plugins', 'active_plugins', 'plugin_cache', 'plugin_cache', 'theme_mods'], $boundary['currentLabels']);
    $t->same(['plugin_alpha', 'siteurl', 'siteurl', 'active_plugins', 'active_plugins', 'plugin_beta'], $boundary['nextLabels']);
    $t->true(count($boundary['entered']) > 0);
    $t->true(count($boundary['left']) > 0);
};

$tests['compound recursive order limit current source next146 changed signatures and reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->true(str_contains($changed, 'plugin_alpha'));
    $t->true(str_contains($changed, 'plugin_beta'));
    $t->true(in_array('recursive-queue-order-limit-before-compound-tail', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-limit-boundary-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-priority-queue-visit-order-changed', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-order-after-current-source', $plan['replanReasons'], true));
};

$tests['compound recursive order limit current source next146 rejects non recursive compound'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan::compareRecursiveOrderLimit(
        "SELECT option_id AS id, option_name AS name, priority, 'autoload' AS source FROM wp_options UNION ALL SELECT option_id, option_name, priority, 'copy' FROM wp_options ORDER BY priority LIMIT 3",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound recursive order limit current source next146 rejects missing compound tail limit'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan::compareRecursiveOrderLimit(
        "WITH RECURSIVE ranked(id, name, priority, depth) AS (VALUES (1, 'siteurl', 90, 0) UNION ALL SELECT option_id, option_name, priority, depth + 1 FROM wp_options JOIN ranked ON parent_id = id ORDER BY priority DESC LIMIT 3) SELECT id, name, priority, 'recursive' AS source FROM ranked UNION ALL SELECT option_id, option_name, priority, 'autoload' FROM wp_options ORDER BY priority",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound recursive order limit current source next146 rejects untraceable recursive select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan::compareRecursiveOrderLimit(
        "WITH RECURSIVE ranked(id, name, priority, depth) AS (VALUES (1, 'siteurl', 90, 0) UNION ALL SELECT option_id, option_name, priority, depth + 1 FROM wp_options JOIN ranked ON parent_id = id ORDER BY priority DESC LIMIT 3) SELECT name, id, priority, 'recursive' AS source FROM ranked UNION ALL SELECT option_name, option_id, priority, 'autoload' FROM wp_options ORDER BY priority LIMIT 3",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 56) as $case) {
    $tests['compound recursive order limit current source next146 generated priority boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $extraPriority = 80 + $case;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'parent_id' => null, 'priority' => 90],
                ['option_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'parent_id' => 1, 'priority' => 70],
                ['option_id' => 3, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'parent_id' => 1, 'priority' => $extraPriority],
                ['option_id' => 4, 'option_name' => 'theme_' . $case, 'autoload' => 'yes', 'parent_id' => 2, 'priority' => 60],
            ],
        ];
        $sql = "WITH RECURSIVE ranked(id, name, priority, depth) AS (VALUES (1, 'siteurl', 90, 0) UNION ALL SELECT option_id, option_name, wp_options.priority, depth + 1 FROM wp_options JOIN ranked ON parent_id = id WHERE autoload = 'yes' ORDER BY priority DESC, name LIMIT 3) SELECT id, name, priority, 'recursive' AS source FROM ranked UNION ALL SELECT option_id AS id, option_name AS name, priority, 'autoload' AS source FROM wp_options WHERE autoload = 'yes' ORDER BY priority DESC, name LIMIT 4 OFFSET 1";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->true(in_array($rows[0]['name'] ?? null, ['siteurl', 'plugin_' . $case], true));
        $t->true(in_array('plugin_' . $case, array_column($rows, 'name'), true));
        $t->same(4, count($rows));
    };
}

return $tests;
