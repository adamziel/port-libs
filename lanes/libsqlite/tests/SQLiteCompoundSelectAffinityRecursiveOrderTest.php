<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectAffinityRecursiveOrderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'parent_id' => 0, 'sort_key' => '10', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'parent_id' => 1, 'sort_key' => 2, 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_alpha', 'parent_id' => 1, 'sort_key' => '1', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'theme_child', 'parent_id' => 2, 'sort_key' => 3, 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'string_child', 'parent_id' => 2, 'sort_key' => '0', 'autoload' => 'no'],
    ['option_id' => 50, 'option_name' => 'direct_numeric', 'parent_id' => -1, 'sort_key' => 1.25, 'autoload' => 'no'],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'plugin_beta', 'parent_id' => 1, 'sort_key' => 1.5, 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_beta_child', 'parent_id' => 6, 'sort_key' => '2', 'autoload' => 'no'],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE walk(id, name, sort_key, depth) AS (
    SELECT option_id, option_name, sort_key, 0
      FROM wp_options
     WHERE parent_id = 0
    UNION ALL
    SELECT child.option_id, child.option_name, child.sort_key, walk.depth + 1
      FROM wp_options AS child
      JOIN walk ON child.parent_id = walk.id
     WHERE walk.depth < 3
     ORDER BY 3 ASC, 1 ASC
     LIMIT 8
)
SELECT name, sort_key, depth, 'walk' AS source
  FROM walk
UNION ALL
SELECT option_name AS name, sort_key, 0 AS depth, 'direct' AS source
  FROM wp_options
 WHERE parent_id = -1
 ORDER BY sort_key ASC, name ASC
 LIMIT 6
SQL;

$summary = static fn (): array => SQLiteCompoundSelectAffinityRecursiveOrderCurrentSourceNextPlan::compareRecursiveAffinityOrder($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound select affinity recursive order status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-affinity-recursive-order-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-queue-order-storage-class',
        'sqlite-compound-select-final-order-after-recursive-source',
        'sqlite-current-source-next-recursive-boundary',
    ], $plan['dependencies']);
};

$tests['compound select affinity recursive order compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['sort_key', 'name'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
};

$tests['compound select affinity recursive order recursive current queue uses storage class order'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same(['siteurl', 'home', 'theme_child', 'string_child', 'plugin_alpha'], $recursive['currentVisitedNames']);
    $t->same(['string:10', 'numeric:2', 'numeric:3', 'string:0', 'string:1'], $recursive['currentSortClasses']);
    $t->same([['home', 'plugin_alpha'], ['theme_child', 'string_child'], [], [], []], $recursive['currentAcceptedNextNames']);
};

$tests['compound select affinity recursive order recursive next queue uses storage class order'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same(['siteurl', 'plugin_beta', 'home', 'theme_child', 'string_child', 'plugin_alpha', 'plugin_beta_child'], $recursive['nextVisitedNames']);
    $t->same(['string:10', 'numeric:1.5', 'numeric:2', 'numeric:3', 'string:0', 'string:1', 'string:2'], $recursive['nextSortClasses']);
    $t->same([['home', 'plugin_alpha', 'plugin_beta'], ['plugin_beta_child'], ['theme_child', 'string_child']], array_slice($recursive['nextAcceptedNextNames'], 0, 3));
};

$tests['compound select affinity recursive order final compound order current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['direct_numeric', 'home', 'theme_child', 'string_child', 'plugin_alpha', 'siteurl'], array_column($rows, 'name'));
    $t->same([1.25, 2, 3, '0', '1', '10'], array_column($rows, 'sort_key'));
    $t->same(['direct', 'walk', 'walk', 'walk', 'walk', 'walk'], array_column($rows, 'source'));
};

$tests['compound select affinity recursive order final compound order next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['direct_numeric', 'plugin_beta', 'home', 'theme_child', 'string_child', 'plugin_alpha'], array_column($rows, 'name'));
    $t->same([1.25, 1.5, 2, 3, '0', '1'], array_column($rows, 'sort_key'));
    $t->same(['direct', 'walk', 'walk', 'walk', 'walk', 'walk'], array_column($rows, 'source'));
};

$tests['compound select affinity recursive order changed signatures and replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->true(str_contains($changed, 'plugin_beta'));
    $t->true(str_contains($changed, '"sort_key":1.5'));
    $t->true(in_array('compound-final-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-queue-order-boundary-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-affinity-class-boundary-changed', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-order-after-recursive-source', $plan['replanReasons'], true));
};

$tests['compound select affinity recursive order rejects non recursive select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectAffinityRecursiveOrderCurrentSourceNextPlan::compareRecursiveAffinityOrder(
        'SELECT option_name AS name, sort_key FROM wp_options UNION ALL SELECT option_name AS name, sort_key FROM wp_options ORDER BY sort_key',
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 36) as $case) {
    $tests['compound select affinity recursive order generated storage queue ' . $case] = static function (TestRunner $t) use ($case): void {
        $numeric = 2 + ($case % 5);
        $text = (string) (1 + ($case % 3));
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'root_' . $case, 'parent_id' => 0, 'sort_key' => '9', 'autoload' => 'yes'],
                ['option_id' => 2, 'option_name' => 'numeric_' . $case, 'parent_id' => 1, 'sort_key' => $numeric, 'autoload' => 'yes'],
                ['option_id' => 3, 'option_name' => 'text_' . $case, 'parent_id' => 1, 'sort_key' => $text, 'autoload' => 'no'],
                ['option_id' => 4, 'option_name' => 'numeric_child_' . $case, 'parent_id' => 2, 'sort_key' => $numeric + 1, 'autoload' => 'no'],
            ],
        ];
        $sql = "WITH RECURSIVE walk(id, name, sort_key, depth) AS (SELECT option_id, option_name, sort_key, 0 FROM wp_options WHERE parent_id = 0 UNION ALL SELECT child.option_id, child.option_name, child.sort_key, walk.depth + 1 FROM wp_options AS child JOIN walk ON child.parent_id = walk.id WHERE walk.depth < 2 ORDER BY 3 ASC, 1 ASC LIMIT 5) SELECT name, sort_key, depth FROM walk UNION ALL SELECT option_name AS name, sort_key, 0 AS depth FROM wp_options WHERE parent_id = -1 ORDER BY sort_key ASC, name ASC LIMIT 5";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same('numeric_' . $case, $rows[0]['name']);
        $t->same('numeric_child_' . $case, $rows[1]['name']);
        $t->same('text_' . $case, $rows[2]['name']);
        $t->same('root_' . $case, $rows[3]['name']);
    };
}

return $tests;
