<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
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
    ['option_id' => 7, 'option_name' => 'plugin_beta', 'autoload' => 'no', 'weight' => 9],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE option_queue(id, label, score) AS (
    VALUES (1, 'seed', 20)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 4
      FROM option_queue
     WHERE id < 7
     LIMIT 5
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS win_value
  FROM option_queue
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       lag(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS win_value
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY win_value DESC, id
 LIMIT 6 OFFSET 2
SQL;

$summary = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowRecursiveLimit($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound select window recursive limit window-recursive-limit status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-window-recursive-limit-current-source-window-recursive-limit-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-queue-limit-before-compound-window-recursive-limit',
        'sqlite-window-arm-values-before-compound-limit-window-recursive-limit',
        'sqlite-compound-final-limit-current-source-boundary-window-recursive-limit',
    ], $plan['dependencies']);
    $t->true(str_contains($plan['dependency_closure'], 'no new support component needed'));
};

$tests['compound select window recursive limit window-recursive-limit compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['win_value', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(2, $compound['offset']);
};

$tests['compound select window recursive limit window-recursive-limit current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([3, 5, 5, 4, 3, 2], array_column($rows, 'id'));
    $t->same(['blogname', 'theme_mods', 'seed:2:3:4:5', 'seed:2:3:4', 'seed:2:3', 'seed:2'], array_column($rows, 'label'));
    $t->same([10, 8, 5, 4, 3, 2], array_column($rows, 'win_value'));
};

$tests['compound select window recursive limit window-recursive-limit next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([2, 3, 5, 5, 4, 3], array_column($rows, 'id'));
    $t->same(['home', 'blogname', 'theme_mods', 'seed:2:3:4:5', 'seed:2:3:4', 'seed:2:3'], array_column($rows, 'label'));
    $t->same([12, 10, 8, 5, 4, 3], array_column($rows, 'win_value'));
};

$tests['compound select window recursive limit window-recursive-limit recursive queue limit exhausted'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('option_queue', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(5, $recursive['currentTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same([1, 2, 3, 4, 5], array_column($recursive['currentRows'], 'id'));
};

$tests['compound select window recursive limit window-recursive-limit window terms'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['row_number', 'lag'], $windows['functions']);
    $t->same(['win_value', 'win_value'], array_column($windows['current'], 'alias'));
    $t->same([0, 3], array_column($windows['current'], 'argumentCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit window-recursive-limit limit trace'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['limitTrace'];
    $t->same(9, $trace['current']['preLimitCount']);
    $t->same(10, $trace['next']['preLimitCount']);
    $t->same(['siteurl', 'home'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl', 'plugin_alpha'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['seed:2', 'seed'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit window-recursive-limit boundary delta'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['boundary'];
    $t->same('blogname', $boundary['currentFirst']['label']);
    $t->same('home', $boundary['nextFirst']['label']);
    $t->same('seed:2', $boundary['currentLast']['label']);
    $t->same('seed:2:3', $boundary['nextLast']['label']);
    $t->true(str_contains(implode("\n", $boundary['gainedRows']), '"label":"home"'));
    $t->true(str_contains(implode("\n", $boundary['lostRows']), '"label":"seed:2"'));
};

$tests['compound select window recursive limit window-recursive-limit source signatures'] = static function (TestRunner $t) use ($summary): void {
    $signature = $summary()['sourceSignature'];
    $t->same(64, strlen($signature['current']['digest']));
    $t->same(64, strlen($signature['next']['digest']));
    $t->same(5, $signature['current']['recursiveRowCount']);
    $t->same(5, $signature['next']['recursiveRowCount']);
    $t->same(2, $signature['current']['windowCount']);
    $t->same(2, $signature['next']['windowCount']);
    $t->same(6, $signature['current']['finalRowCount']);
    $t->same(6, $signature['next']['finalRowCount']);
    $t->same(false, $signature['currentMatchesNext']);
};

$tests['compound select window recursive limit window-recursive-limit replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->true(in_array('limited-compound-rowset-changed', $reasons, true));
    $t->true(in_array('prelimit-compound-rowset-changed', $reasons, true));
    $t->true(in_array('window-before-compound-limit', $reasons, true));
    $t->true(in_array('compound-final-limit', $reasons, true));
    $t->true(in_array('recursive-limit-exhausted-before-compound', $reasons, true));
};

$tests['compound select window recursive limit window-recursive-limit rejects simple select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowRecursiveLimit(
        'SELECT option_id AS id FROM wp_options UNION ALL SELECT option_id FROM wp_options LIMIT 2',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound select window recursive limit window-recursive-limit rejects missing final limit'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowRecursiveLimit(
        "WITH RECURSIVE option_queue(id, label, score) AS (VALUES (1, 'seed', 20) UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 4 FROM option_queue WHERE id < 7 LIMIT 5) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS win_value FROM option_queue UNION ALL SELECT option_id AS id, option_name AS label, lag(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS win_value FROM wp_options WHERE autoload = 'yes' ORDER BY win_value DESC, id",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound select window recursive limit window-recursive-limit generated source boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 3 + ($case % 4);
        $finalLimit = 2 + ($case % 5);
        $offset = $case % 3;
        $tables = [
            'wp_options' => [
                ['option_id' => 20, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 40 + $case],
                ['option_id' => 21, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 38 + $case],
                ['option_id' => 22, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'weight' => 36 + $case],
                ['option_id' => 23, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 34 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE option_queue(id, label, score) AS (VALUES (1, 'seed', " . (18 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 2 FROM option_queue WHERE id < 9 LIMIT {$recursiveLimit}) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS win_value FROM option_queue UNION ALL SELECT option_id AS id, option_name AS label, lag(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS win_value FROM wp_options WHERE autoload = 'yes' ORDER BY win_value DESC, id LIMIT {$finalLimit} OFFSET {$offset}";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same(min($finalLimit, $recursiveLimit + 3 - $offset), count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['win_value']));
        $t->true($rows[0]['win_value'] >= $rows[count($rows) - 1]['win_value']);
    };
}

return $tests;
