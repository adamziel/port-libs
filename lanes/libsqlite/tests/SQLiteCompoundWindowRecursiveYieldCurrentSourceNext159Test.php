<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowRecursiveYieldCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 12],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 10],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 8],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 6],
    ['option_id' => 5, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 5],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 14],
    ['option_id' => 7, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'weight' => 7],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE option_queue(id, label, score) AS (
    VALUES (1, 'seed', 20)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 3
      FROM option_queue
     WHERE id < 8
     LIMIT 6
)
SELECT id,
       label,
       ntile(3) OVER (ORDER BY score DESC, id) AS win_value
  FROM option_queue
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       percent_rank() OVER (ORDER BY weight DESC, option_id) AS win_value
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY win_value DESC, id
 LIMIT 2, 6
SQL;

$summary = static fn (): array => SQLiteCompoundWindowRecursiveYieldCurrentSourceNextPlan::compareRecursiveWindowYieldSources($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound window recursive yield next159 status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-window-recursive-yield-current-source-next159-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-limit-before-window-yield-next159',
        'sqlite-compound-window-ntile-percent-rank-yield-next159',
        'sqlite-compound-comma-limit-current-next-yield-boundary-next159',
    ], $plan['dependencies']);
    $t->true(str_contains($plan['dependency_closure'], 'no new support component needed'));
};

$tests['compound window recursive yield next159 compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['win_value', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(2, $compound['offset']);
    $t->true($compound['commaLimit']);
};

$tests['compound window recursive yield next159 current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([3, 4, 1, 2, 5, 3], array_column($rows, 'id'));
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed', 'seed:2', 'theme_mods', 'blogname'], array_column($rows, 'label'));
    $t->same([2, 2, 1, 1, 1.0, 0.6666666666666666], array_column($rows, 'win_value'));
};

$tests['compound window recursive yield next159 next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([3, 4, 1, 2, 5, 7], array_column($rows, 'id'));
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed', 'seed:2', 'theme_mods', 'plugin_beta'], array_column($rows, 'label'));
    $t->same([2, 2, 1, 1, 1.0, 0.8], array_column($rows, 'win_value'));
};

$tests['compound window recursive yield next159 prelimit next captures shifted table windows'] = static function (TestRunner $t) use ($summary): void {
    $preLimit = $summary()['nextPreLimitRows'];
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3'], array_slice(array_column($preLimit, 'label'), 0, 3));
    $t->same([3, 3, 2], array_slice(array_column($preLimit, 'win_value'), 0, 3));
    $t->true(in_array('plugin_beta', array_column($preLimit, 'label'), true));
};

$tests['compound window recursive yield next159 recursive queue limit exhausted before window'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('option_queue', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(6, $recursive['currentTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same([1, 2, 3, 4, 5, 6], array_column($recursive['currentRows'], 'id'));
};

$tests['compound window recursive yield next159 window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['ntile', 'percent_rank'], $windows['functions']);
    $t->same(['win_value', 'win_value'], array_column($windows['current'], 'alias'));
    $t->same([1, 0], array_column($windows['current'], 'argumentCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound window recursive yield next159 yield slots retain prelimit positions'] = static function (TestRunner $t) use ($summary): void {
    $slots = $summary()['yieldSlots']['current'];
    $t->same(2, $slots['offset']);
    $t->same(6, $slots['limit']);
    $t->same(10, $slots['preLimitCount']);
    $t->same([2, 3, 4, 5, 6, 7], array_column($slots['slots'], 'preLimitIndex'));
    $t->same(['recursive', 'recursive', 'recursive', 'recursive', 'table', 'table'], array_column($slots['slots'], 'sourceClass'));
};

$tests['compound window recursive yield next159 current next source classes'] = static function (TestRunner $t) use ($summary): void {
    $classes = $summary()['sourceClasses'];
    $t->same(['recursive' => 4, 'table' => 2], $classes['current']);
    $t->same(['recursive' => 4, 'table' => 2], $classes['next']);
};

$tests['compound window recursive yield next159 boundary delta stable after shifted prelimit'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['boundary'];
    $t->same('seed:2:3', $boundary['currentFirst']['label']);
    $t->same('seed:2:3', $boundary['nextFirst']['label']);
    $t->true(str_contains(implode("\n", $boundary['gainedRows']), '"label":"plugin_beta"'));
    $t->true(str_contains(implode("\n", $boundary['lostRows']), '"label":"blogname"'));
};

$tests['compound window recursive yield next159 replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->true(in_array('recursive-limit-before-window-yield', $reasons, true));
    $t->true(in_array('compound-comma-limit-yield-boundary', $reasons, true));
    $t->true(in_array('prelimit-window-rowset-changed', $reasons, true));
    $t->true(in_array('recursive-limit-exhausted-before-window', $reasons, true));
};

$tests['compound window recursive yield next159 rejects missing comma limit'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowRecursiveYieldCurrentSourceNextPlan::compareRecursiveWindowYieldSources(
        "WITH RECURSIVE option_queue(id, label, score) AS (VALUES (1, 'seed', 20) UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 3 FROM option_queue WHERE id < 8 LIMIT 6) SELECT id, label, ntile(3) OVER (ORDER BY score DESC, id) AS win_value FROM option_queue UNION ALL SELECT option_id AS id, option_name AS label, percent_rank() OVER (ORDER BY weight DESC, option_id) AS win_value FROM wp_options WHERE autoload = 'yes' ORDER BY win_value DESC, id LIMIT 6 OFFSET 2",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound window recursive yield next159 rejects missing percent rank'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowRecursiveYieldCurrentSourceNextPlan::compareRecursiveWindowYieldSources(
        "WITH RECURSIVE option_queue(id, label, score) AS (VALUES (1, 'seed', 20) UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 3 FROM option_queue WHERE id < 8 LIMIT 6) SELECT id, label, ntile(3) OVER (ORDER BY score DESC, id) AS win_value FROM option_queue UNION ALL SELECT option_id AS id, option_name AS label, lag(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS win_value FROM wp_options WHERE autoload = 'yes' ORDER BY win_value DESC, id LIMIT 2, 6",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound window recursive yield next159 generated yield boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 4 + ($case % 4);
        $offset = $case % 3;
        $limit = 3 + ($case % 4);
        $tables = [
            'wp_options' => [
                ['option_id' => 20, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 50 + $case],
                ['option_id' => 21, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 48 + $case],
                ['option_id' => 22, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'weight' => 46 + $case],
                ['option_id' => 23, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 44 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE option_queue(id, label, score) AS (VALUES (1, 'seed', " . (20 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 3 FROM option_queue WHERE id < 9 LIMIT {$recursiveLimit}) SELECT id, label, ntile(3) OVER (ORDER BY score DESC, id) AS win_value FROM option_queue UNION ALL SELECT option_id AS id, option_name AS label, percent_rank() OVER (ORDER BY weight DESC, option_id) AS win_value FROM wp_options WHERE autoload = 'yes' ORDER BY win_value DESC, id LIMIT {$offset}, {$limit}";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same(min($limit, $recursiveLimit + 3 - $offset), count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['win_value']));
        $t->true($rows[0]['win_value'] >= $rows[count($rows) - 1]['win_value']);
        $t->true(in_array('seed', array_column($rows, 'label'), true) || $offset > 0);
    };
}

return $tests;
