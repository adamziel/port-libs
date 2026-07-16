<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 12],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 10],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 8],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 6],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 4],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 14],
    ['option_id' => 7, 'option_name' => 'plugin_beta', 'autoload' => 'no', 'weight' => 9],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE option_rank(id, label, weight) AS (
    VALUES (1, 'seed', 15)
    UNION ALL
    SELECT id + 1, 'seed:' || (id + 1), weight - 3
      FROM option_rank
     WHERE id < 5
     LIMIT 4
)
SELECT id,
       label,
       sum(weight) OVER (
           ORDER BY id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS window_weight
  FROM option_rank
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       first_value(weight) OVER (
           ORDER BY weight DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS window_weight
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY window_weight DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summary = static fn (): array => SQLiteCompoundWindowRecursiveLimitCurrentSourceNextPlan::compare($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound window recursive limit current source status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-window-recursive-limit-current-source-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-cte-queue-limit',
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-select-sql-compound-final-limit',
        'sqlite-current-source-next-rowset-boundary',
    ], $plan['dependencies']);
};

$tests['compound window recursive limit current source compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['window_weight', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound window recursive limit current source current limited rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([2, 3, 1, 2, 3], array_column($rows, 'id'));
    $t->same(['seed:2', 'seed:3', 'siteurl', 'home', 'blogname'], array_column($rows, 'label'));
    $t->same([21, 15, 12, 10, 8], array_column($rows, 'window_weight'));
};

$tests['compound window recursive limit current source next limited rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([2, 3, 6, 1, 2], array_column($rows, 'id'));
    $t->same(['seed:2', 'seed:3', 'plugin_alpha', 'siteurl', 'home'], array_column($rows, 'label'));
    $t->same([21, 15, 14, 12, 10], array_column($rows, 'window_weight'));
};

$tests['compound window recursive limit current source recursive queue trace'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('option_rank', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(4, $recursive['currentTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same([1, 2, 3, 4], array_column($recursive['currentRows'], 'id'));
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound window recursive limit current source window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['sum', 'first_value'], array_column($windows, 'function'));
    $t->same(['window_weight', 'window_weight'], array_column($windows, 'alias'));
    $t->same(['ROWS', 'ROWS'], array_column($windows, 'frameUnit'));
    $t->same([0, 0], array_column($windows, 'preceding'));
    $t->same([1, 1], array_column($windows, 'following'));
};

$tests['compound window recursive limit current source limit trace'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['limitTrace'];
    $t->same(7, $trace['current']['preLimitCount']);
    $t->same(8, $trace['next']['preLimitCount']);
    $t->same(['seed'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:4'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['blogname', 'seed:4'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound window recursive limit current source changed signatures and reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->true(str_contains($changed, '"label":"plugin_alpha"'));
    $t->true(str_contains($changed, '"id":6'));
    $t->true(in_array('limited-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('window-before-compound-limit', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-limit', $plan['replanReasons'], true));
};

$tests['compound window recursive limit current source rejects non recursive select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowRecursiveLimitCurrentSourceNextPlan::compare(
        'SELECT option_id AS id FROM wp_options UNION ALL SELECT option_id FROM wp_options LIMIT 2',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound window recursive limit current source rejects missing final limit'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowRecursiveLimitCurrentSourceNextPlan::compare(
        "WITH RECURSIVE option_rank(id, weight) AS (VALUES (1, 5) UNION ALL SELECT id + 1, weight - 1 FROM option_rank WHERE id < 3 LIMIT 3) SELECT id, sum(weight) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS window_weight FROM option_rank UNION ALL SELECT option_id, weight FROM wp_options ORDER BY window_weight",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound window recursive limit current source generated recursive window boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $cteLimit = 2 + ($case % 4);
        $finalLimit = 2 + ($case % 3);
        $offset = $case % 2;
        $tables = [
            'wp_options' => [
                ['option_id' => 10, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 20 + $case],
                ['option_id' => 11, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 18 + $case],
                ['option_id' => 12, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'weight' => 16 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE option_rank(id, label, weight) AS (VALUES (1, 'seed', {$case}) UNION ALL SELECT id + 1, label || ':' || (id + 1), weight + 1 FROM option_rank WHERE id < 8 LIMIT {$cteLimit}) SELECT id, label, sum(weight) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_weight FROM option_rank UNION ALL SELECT option_id AS id, option_name AS label, first_value(weight) OVER (ORDER BY weight DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_weight FROM wp_options WHERE autoload = 'yes' ORDER BY window_weight DESC, id LIMIT {$finalLimit} OFFSET {$offset}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(min($finalLimit, $cteLimit + 2 - $offset), count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['window_weight']));
        $t->true($rows[0]['window_weight'] >= $rows[count($rows) - 1]['window_weight']);
    };
}

return $tests;
