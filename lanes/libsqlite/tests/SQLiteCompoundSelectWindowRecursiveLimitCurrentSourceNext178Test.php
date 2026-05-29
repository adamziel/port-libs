<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions178 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 75],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 20],
];
$nextOptions178 = [
    ...$currentOptions178,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 85],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 65],
];
$currentTables178 = ['wp_options' => $currentOptions178];
$nextTables178 = ['wp_options' => $nextOptions178];

$sql178 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 100)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 7
     LIMIT 5 OFFSET 2
)
SELECT id,
       label,
       lag(score, 1, score) OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       lead(score, 1, score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_id AS id,
       option_name AS label,
       score AS metric
  FROM wp_options
 WHERE score >= 65
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summary178 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext178($sql178, $currentTables178, $nextTables178);
$tests = [];

$tests['compound select window recursive limit next178 status dependencies'] = static function (TestRunner $t) use ($summary178): void {
    $plan = $summary178();
    $t->same('compound-select-window-recursive-limit-current-source-next178-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-offset-next178',
        'sqlite-select-sql-compound-union-all-union-distinct-next178',
        'sqlite-select-sql-window-lag-lead-next178',
        'sqlite-current-source-next178',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit next178 compound metadata'] = static function (TestRunner $t) use ($summary178): void {
    $compound = $summary178()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['hasUnionAll']);
    $t->true($compound['hasUnionDistinct']);
    $t->same(2, $compound['distinctArmIndex']);
};

$tests['compound select window recursive limit next178 current rows'] = static function (TestRunner $t) use ($summary178): void {
    $rows = $summary178()['currentRows'];
    $t->same([3, 3, 4, 1, 2], array_column($rows, 'id'));
    $t->same(['rewrite_rules', 'seed:2:3', 'seed:2:3:4', 'siteurl', 'home'], array_column($rows, 'label'));
    $t->same([90, 80, 80, 75, 75], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit next178 next rows'] = static function (TestRunner $t) use ($summary178): void {
    $rows = $summary178()['nextRows'];
    $t->same([3, 1, 5, 3, 4], array_column($rows, 'id'));
    $t->same(['rewrite_rules', 'siteurl', 'plugin_alpha', 'seed:2:3', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([90, 85, 85, 80, 80], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit next178 prelimit rows'] = static function (TestRunner $t) use ($summary178): void {
    $plan = $summary178();
    $t->same(['siteurl', 'rewrite_rules', 'seed:2:3'], array_slice(array_column($plan['currentPreLimitRows'], 'label'), 0, 3));
    $t->same(['siteurl', 'rewrite_rules', 'siteurl'], array_slice(array_column($plan['nextPreLimitRows'], 'label'), 0, 3));
    $t->true(in_array('plugin_alpha', array_column($plan['nextPreLimitRows'], 'label'), true));
};

$tests['compound select window recursive limit next178 recursive trace'] = static function (TestRunner $t) use ($summary178): void {
    $recursive = $summary178()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed', 'seed:2'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit next178 window metadata'] = static function (TestRunner $t) use ($summary178): void {
    $windows = $summary178()['windows'];
    $t->same(['lag', 'lead'], $windows['functions']);
    $t->same(['metric', 'metric'], array_column($windows['current'], 'alias'));
    $t->same([3, 3], array_column($windows['current'], 'argumentCount'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit next178 distinct union diagnostics'] = static function (TestRunner $t) use ($summary178): void {
    $distinct = $summary178()['distinctUnion'];
    $t->same([], $distinct['currentDuplicateLabels']);
    $t->same([], $distinct['nextDuplicateLabels']);
    $t->true(in_array('plugin_alpha', $distinct['changedSurvivors'], true));
    $t->true(in_array('home', $distinct['changedSurvivors'], true));
};

$tests['compound select window recursive limit next178 limit trace'] = static function (TestRunner $t) use ($summary178): void {
    $trace = $summary178()['limitTrace'];
    $t->same(10, $trace['current']['preLimitCount']);
    $t->same(14, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5', 'home', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['home', 'plugin_alpha', 'seed:2:3:4:5', 'home', 'theme_mods', 'seed:2:3:4:5:6', 'theme_mods', 'seed:2:3:4:5:6:7'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit next178 source classes'] = static function (TestRunner $t) use ($summary178): void {
    $classes = $summary178()['sourceClasses'];
    $t->same(['recursive' => 2, 'table' => 3], $classes['current']);
    $t->same(['recursive' => 2, 'table' => 3], $classes['next']);
};

$tests['compound select window recursive limit next178 boundary delta'] = static function (TestRunner $t) use ($summary178): void {
    $boundary = $summary178()['boundary'];
    $t->same('rewrite_rules', $boundary['currentFirst']['label']);
    $t->same('rewrite_rules', $boundary['nextFirst']['label']);
    $t->same('home', $boundary['currentLast']['label']);
    $t->same('seed:2:3:4', $boundary['nextLast']['label']);
    $t->contains('"label":"plugin_alpha"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"home"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit next178 replan reasons'] = static function (TestRunner $t) use ($summary178): void {
    $plan = $summary178();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->contains('"label":"home"', $changed);
    $t->true(in_array('limited-distinct-union-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-distinct-union-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-skipped-anchor-and-first-recursive-row', $plan['replanReasons'], true));
    $t->true(in_array('compound-union-all-before-union-distinct', $plan['replanReasons'], true));
    $t->true(in_array('mixed-offset-window-values-before-distinct-union', $plan['replanReasons'], true));
    $t->true(in_array('compound-tail-limit-offset', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit next178 rejects missing distinct union'] = static function (TestRunner $t) use ($currentTables178): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext178(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 7 LIMIT 5 OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(score, 1, score) OVER (ORDER BY score) FROM wp_options ORDER BY metric LIMIT 5 OFFSET 1",
        $currentTables178,
        $currentTables178,
    ));
};

$tests['compound select window recursive limit next178 rejects missing lead'] = static function (TestRunner $t) use ($currentTables178): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext178(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 7 LIMIT 5 OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score) FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 5 OFFSET 1",
        $currentTables178,
        $currentTables178,
    ));
};

$tests['compound select window recursive limit next178 rejects final limit without offset'] = static function (TestRunner $t) use ($currentTables178): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext178(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 7 LIMIT 5 OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(score, 1, score) OVER (ORDER BY score) FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 5",
        $currentTables178,
        $currentTables178,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit next178 generated union boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 4 + ($case % 4);
        $finalLimit = 3 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 85 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 10 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (120 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT {$recursiveLimit} OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, lead(score, 1, score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' UNION SELECT option_id AS id, option_name AS label, score AS metric FROM wp_options WHERE score >= " . (70 + $case) . " ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['metric']));
        $t->true($rows[0]['metric'] >= $rows[count($rows) - 1]['metric']);
        $t->same(false, in_array('seed_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
