<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions173 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 60],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 55],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 45],
    ['option_id' => 4, 'option_name' => 'obsolete_cache', 'autoload' => 'no', 'weight' => 30],
];
$nextOptions173 = [
    ...$currentOptions173,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 68],
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 42],
];
$currentTables173 = ['wp_options' => $currentOptions173];
$nextTables173 = ['wp_options' => $nextOptions173];

$sql173 = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 70)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 4
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY weight DESC, id) AS metric
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY weight DESC) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT id,
       label,
       metric
  FROM (
        SELECT option_id AS id,
               option_name AS label,
               dense_rank() OVER (ORDER BY weight DESC) AS metric
          FROM wp_options
         WHERE option_name LIKE 'theme%'
        UNION ALL
        SELECT id,
               label,
               row_number() OVER (ORDER BY weight DESC, id) AS metric
          FROM q
         WHERE id = 2
  )
 ORDER BY metric, id
 LIMIT 5 OFFSET 1
SQL;

$summary173 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext173($sql173, $currentTables173, $nextTables173);
$tests = [];

$tests['compound select window recursive limit next173 status dependencies'] = static function (TestRunner $t) use ($summary173): void {
    $plan = $summary173();
    $t->same('compound-select-window-recursive-limit-current-source-next173-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-offset-next173',
        'sqlite-select-sql-compound-union-distinct-except-next173',
        'sqlite-select-sql-window-row-number-dense-rank-next173',
        'sqlite-current-source-next173',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit next173 compound metadata'] = static function (TestRunner $t) use ($summary173): void {
    $compound = $summary173()['compound'];
    $t->same(['UNION', 'EXCEPT'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['hasUnionDistinct']);
    $t->true($compound['hasExcept']);
    $t->same(2, $compound['exceptArmIndex']);
};

$tests['compound select window recursive limit next173 current rows'] = static function (TestRunner $t) use ($summary173): void {
    $rows = $summary173()['currentRows'];
    $t->same([2, 3, 3, 4, 5], array_column($rows, 'id'));
    $t->same(['home', 'seed:2:3', 'theme_mods', 'seed:2:3:4', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same([2, 2, 3, 3, 4], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit next173 next rows'] = static function (TestRunner $t) use ($summary173): void {
    $rows = $summary173()['nextRows'];
    $t->same([1, 3, 2, 4, 3], array_column($rows, 'id'));
    $t->same(['siteurl', 'seed:2:3', 'home', 'seed:2:3:4', 'theme_mods'], array_column($rows, 'label'));
    $t->same([2, 2, 3, 3, 4], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit next173 prelimit captures except source shift'] = static function (TestRunner $t) use ($summary173): void {
    $plan = $summary173();
    $t->same(['siteurl', 'home', 'seed:2:3'], array_slice(array_column($plan['currentPreLimitRows'], 'label'), 0, 3));
    $t->same(['plugin_alpha', 'siteurl', 'seed:2:3'], array_slice(array_column($plan['nextPreLimitRows'], 'label'), 0, 3));
    $t->true(in_array('rewrite_rules', array_column($plan['nextPreLimitRows'], 'label'), true));
};

$tests['compound select window recursive limit next173 recursive trace'] = static function (TestRunner $t) use ($summary173): void {
    $recursive = $summary173()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit next173 window metadata'] = static function (TestRunner $t) use ($summary173): void {
    $windows = $summary173()['windows'];
    $t->same(['row_number', 'dense_rank'], $windows['functions']);
    $t->same(['metric', 'metric'], array_column($windows['current'], 'alias'));
    $t->same([0, 0], array_column($windows['current'], 'argumentCount'));
    $t->same([2, 1], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit next173 except diagnostics'] = static function (TestRunner $t) use ($summary173): void {
    $except = $summary173()['except'];
    $t->same(['theme_mods', 'seed:2'], $except['currentRemovedLabels']);
    $t->same(['theme_mods', 'seed:2'], $except['nextRemovedLabels']);
    $t->true(in_array('plugin_alpha', $except['changedSurvivors'], true));
    $t->true(in_array('rewrite_rules', $except['changedSurvivors'], true));
};

$tests['compound select window recursive limit next173 limit trace'] = static function (TestRunner $t) use ($summary173): void {
    $trace = $summary173()['limitTrace'];
    $t->same(8, $trace['current']['preLimitCount']);
    $t->same(10, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['plugin_alpha'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6', 'rewrite_rules', 'seed:2:3:4:5:6:7'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit next173 source classes'] = static function (TestRunner $t) use ($summary173): void {
    $classes = $summary173()['sourceClasses'];
    $t->same(['recursive' => 3, 'table' => 2], $classes['current']);
    $t->same(['recursive' => 2, 'table' => 3], $classes['next']);
};

$tests['compound select window recursive limit next173 boundary delta'] = static function (TestRunner $t) use ($summary173): void {
    $boundary = $summary173()['boundary'];
    $t->same('home', $boundary['currentFirst']['label']);
    $t->same('siteurl', $boundary['nextFirst']['label']);
    $t->same('seed:2:3:4:5', $boundary['currentLast']['label']);
    $t->same('theme_mods', $boundary['nextLast']['label']);
    $t->contains('"label":"siteurl"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"seed:2:3:4:5"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit next173 replan reasons'] = static function (TestRunner $t) use ($summary173): void {
    $plan = $summary173();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"siteurl"', $changed);
    $t->contains('"label":"seed:2:3:4:5"', $changed);
    $t->true(in_array('limited-except-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-except-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-skipped-anchor', $plan['replanReasons'], true));
    $t->true(in_array('compound-union-distinct-before-except', $plan['replanReasons'], true));
    $t->true(in_array('derived-except-after-window', $plan['replanReasons'], true));
    $t->true(in_array('mixed-window-functions-before-except', $plan['replanReasons'], true));
    $t->true(in_array('compound-tail-limit-offset', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit next173 rejects union all'] = static function (TestRunner $t) use ($currentTables173): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext173(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 70) UNION ALL SELECT id + 1, label, weight - 4 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY weight) AS metric FROM q UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY weight) FROM wp_options EXCEPT SELECT option_id, option_name, weight FROM wp_options ORDER BY metric LIMIT 3 OFFSET 1",
        $currentTables173,
        $currentTables173,
    ));
};

$tests['compound select window recursive limit next173 rejects missing except'] = static function (TestRunner $t) use ($currentTables173): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext173(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 70) UNION ALL SELECT id + 1, label, weight - 4 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY weight) AS metric FROM q UNION SELECT option_id, option_name, dense_rank() OVER (ORDER BY weight) FROM wp_options ORDER BY metric LIMIT 3 OFFSET 1",
        $currentTables173,
        $currentTables173,
    ));
};

$tests['compound select window recursive limit next173 rejects missing final offset'] = static function (TestRunner $t) use ($currentTables173): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext173(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 70) UNION ALL SELECT id + 1, label, weight - 4 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY weight) AS metric FROM q UNION SELECT option_id, option_name, dense_rank() OVER (ORDER BY weight) FROM wp_options EXCEPT SELECT option_id, option_name, weight FROM wp_options ORDER BY metric LIMIT 3",
        $currentTables173,
        $currentTables173,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit next173 generated except boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 4 + ($case % 4);
        $finalLimit = 3 + ($case % 4);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'weight' => 80 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 70 + $case],
                ['option_id' => 3, 'option_name' => 'theme_mods_' . $case, 'autoload' => 'yes', 'weight' => 50 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'weight' => 20 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed_{$case}', " . (90 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 4 FROM q WHERE id < 9 LIMIT {$recursiveLimit} OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY weight DESC, id) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, dense_rank() OVER (ORDER BY weight DESC) AS metric FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT id, label, metric FROM (SELECT option_id AS id, option_name AS label, dense_rank() OVER (ORDER BY weight DESC) AS metric FROM wp_options WHERE option_name LIKE 'theme%' UNION ALL SELECT id, label, row_number() OVER (ORDER BY weight DESC, id) AS metric FROM q WHERE id = 2) ORDER BY metric, id LIMIT {$finalLimit} OFFSET 1";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['metric']));
        $t->true($rows[0]['metric'] <= $rows[count($rows) - 1]['metric']);
        $t->same(false, in_array('seed_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
