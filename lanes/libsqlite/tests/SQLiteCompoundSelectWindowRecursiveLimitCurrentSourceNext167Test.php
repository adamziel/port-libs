<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions167 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 26],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 20],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 10],
];
$nextOptions167 = [
    ...$currentOptions167,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 37],
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 18],
];
$currentTables167 = ['wp_options' => $currentOptions167];
$nextTables167 = ['wp_options' => $nextOptions167];

$sql167 = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 34)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 3
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       lag(weight, 1, weight) OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       lead(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id,
       label,
       metric
  FROM (
        SELECT id,
               label,
               lag(weight, 1, weight) OVER (ORDER BY id) AS metric
          FROM q
        UNION ALL
        SELECT option_id AS id,
               option_name AS label,
               lead(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS metric
          FROM wp_options
         WHERE autoload = 'yes'
  )
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summary167 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext167($sql167, $currentTables167, $nextTables167);
$tests = [];

$tests['compound select window recursive limit next167 status dependencies'] = static function (TestRunner $t) use ($summary167): void {
    $plan = $summary167();
    $t->same('compound-select-window-recursive-limit-current-source-next167-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-offset-next167',
        'sqlite-select-sql-compound-intersect-window-next167',
        'sqlite-select-sql-derived-intersect-tail-limit-next167',
        'sqlite-current-source-next167',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit next167 compound metadata'] = static function (TestRunner $t) use ($summary167): void {
    $compound = $summary167()['compound'];
    $t->same(['UNION ALL', 'INTERSECT'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['hasIntersect']);
};

$tests['compound select window recursive limit next167 current rows'] = static function (TestRunner $t) use ($summary167): void {
    $rows = $summary167()['currentRows'];
    $t->same([3, 3, 4, 1, 5], array_column($rows, 'id'));
    $t->same(['seed:2:3', 'theme_mods', 'seed:2:3:4', 'siteurl', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same([31, 30, 28, 26, 25], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit next167 next rows'] = static function (TestRunner $t) use ($summary167): void {
    $rows = $summary167()['nextRows'];
    $t->same([2, 3, 5, 4, 1], array_column($rows, 'id'));
    $t->same(['seed:2', 'seed:2:3', 'plugin_alpha', 'seed:2:3:4', 'siteurl'], array_column($rows, 'label'));
    $t->same([31, 31, 30, 28, 26], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit next167 prelimit captures intersect source shift'] = static function (TestRunner $t) use ($summary167): void {
    $plan = $summary167();
    $t->same(['seed:2', 'seed:2:3', 'theme_mods'], array_slice(array_column($plan['currentPreLimitRows'], 'label'), 0, 3));
    $t->same(['rewrite_rules', 'seed:2', 'seed:2:3'], array_slice(array_column($plan['nextPreLimitRows'], 'label'), 0, 3));
    $t->true(in_array('plugin_alpha', array_column($plan['nextPreLimitRows'], 'label'), true));
};

$tests['compound select window recursive limit next167 recursive trace'] = static function (TestRunner $t) use ($summary167): void {
    $recursive = $summary167()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit next167 window metadata'] = static function (TestRunner $t) use ($summary167): void {
    $windows = $summary167()['windows'];
    $t->same(['lag', 'lead'], $windows['functions']);
    $t->same(['metric', 'metric'], array_column($windows['current'], 'alias'));
    $t->same([3, 3], array_column($windows['current'], 'argumentCount'));
    $t->same([1, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit next167 limit trace'] = static function (TestRunner $t) use ($summary167): void {
    $trace = $summary167()['limitTrace'];
    $t->same(9, $trace['current']['preLimitCount']);
    $t->same(11, $trace['next']['preLimitCount']);
    $t->same(['seed:2'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['rewrite_rules'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6', 'home', 'seed:2:3:4:5:6:7'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6', 'home', 'seed:2:3:4:5:6:7', 'theme_mods'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit next167 source classes'] = static function (TestRunner $t) use ($summary167): void {
    $classes = $summary167()['sourceClasses'];
    $t->same(['recursive' => 3, 'table' => 2], $classes['current']);
    $t->same(['recursive' => 3, 'table' => 2], $classes['next']);
};

$tests['compound select window recursive limit next167 boundary delta'] = static function (TestRunner $t) use ($summary167): void {
    $boundary = $summary167()['boundary'];
    $t->same('seed:2:3', $boundary['currentFirst']['label']);
    $t->same('seed:2', $boundary['nextFirst']['label']);
    $t->same('seed:2:3:4:5', $boundary['currentLast']['label']);
    $t->same('siteurl', $boundary['nextLast']['label']);
    $t->contains('"label":"plugin_alpha"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"theme_mods"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit next167 replan reasons'] = static function (TestRunner $t) use ($summary167): void {
    $plan = $summary167();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->contains('"label":"theme_mods"', $changed);
    $t->true(in_array('limited-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-skipped-anchor', $plan['replanReasons'], true));
    $t->true(in_array('compound-intersect-after-window', $plan['replanReasons'], true));
    $t->true(in_array('mixed-window-functions-before-intersect', $plan['replanReasons'], true));
    $t->true(in_array('compound-tail-limit-offset', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit next167 rejects missing intersect'] = static function (TestRunner $t) use ($currentTables167): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext167(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 34) UNION ALL SELECT id + 1, label, weight - 3 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, lag(weight, 1, weight) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(weight, 1, weight) OVER (ORDER BY weight DESC) FROM wp_options ORDER BY metric LIMIT 3 OFFSET 1",
        $currentTables167,
        $currentTables167,
    ));
};

$tests['compound select window recursive limit next167 rejects missing mixed windows'] = static function (TestRunner $t) use ($currentTables167): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext167(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 34) UNION ALL SELECT id + 1, label, weight - 3 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, lag(weight, 1, weight) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lag(weight, 1, weight) OVER (ORDER BY weight DESC) FROM wp_options INTERSECT SELECT option_id, option_name, weight FROM wp_options ORDER BY metric LIMIT 3 OFFSET 1",
        $currentTables167,
        $currentTables167,
    ));
};

$tests['compound select window recursive limit next167 rejects missing final offset'] = static function (TestRunner $t) use ($currentTables167): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext167(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 34) UNION ALL SELECT id + 1, label, weight - 3 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, lag(weight, 1, weight) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(weight, 1, weight) OVER (ORDER BY weight DESC) FROM wp_options INTERSECT SELECT option_id, option_name, weight FROM wp_options ORDER BY metric LIMIT 3",
        $currentTables167,
        $currentTables167,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound select window recursive limit next167 generated intersect boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 4 + ($case % 4);
        $finalLimit = 3 + ($case % 4);
        $tables = [
            'wp_options' => [
                ['option_id' => 10, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 40 + $case],
                ['option_id' => 11, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 36 + $case],
                ['option_id' => 12, 'option_name' => 'theme_mods_' . $case, 'autoload' => 'yes', 'weight' => 18 + $case],
                ['option_id' => 13, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'weight' => 12 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed_{$case}', " . (34 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 3 FROM q WHERE id < 9 LIMIT {$recursiveLimit} OFFSET 1) SELECT id, label, lag(weight, 1, weight) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, lead(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, lag(weight, 1, weight) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, lead(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['metric']));
        $t->true($rows[0]['metric'] >= $rows[count($rows) - 1]['metric']);
        $t->same(false, in_array('seed_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
