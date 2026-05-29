<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions181 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 75],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 20],
];
$nextOptions181 = [
    ...$currentOptions181,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 85],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 65],
];
$currentTables181 = ['wp_options' => $currentOptions181];
$nextTables181 = ['wp_options' => $nextOptions181];

$sql181 = <<<'SQL'
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

$summary181 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext181($sql181, $currentTables181, $nextTables181);
$tests = [];

$tests['compound select window recursive limit next181 status dependencies'] = static function (TestRunner $t) use ($summary181): void {
    $plan = $summary181();
    $t->same('compound-select-window-recursive-limit-current-source-next181-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-offset-next181',
        'sqlite-select-sql-window-before-union-distinct-next181',
        'sqlite-select-sql-union-distinct-yield-tape-next181',
        'sqlite-select-sql-compound-final-limit-next181',
        'sqlite-current-source-next181',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit next181 compound metadata'] = static function (TestRunner $t) use ($summary181): void {
    $compound = $summary181()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['hasUnionDistinct']);
};

$tests['compound select window recursive limit next181 current next rows'] = static function (TestRunner $t) use ($summary181): void {
    $plan = $summary181();
    $t->same(['rewrite_rules', 'seed:2:3', 'seed:2:3:4', 'siteurl', 'home'], array_column($plan['currentRows'], 'label'));
    $t->same(['rewrite_rules', 'siteurl', 'plugin_alpha', 'seed:2:3', 'seed:2:3:4'], array_column($plan['nextRows'], 'label'));
    $t->same([90, 80, 80, 75, 75], array_column($plan['currentRows'], 'metric'));
    $t->same([90, 85, 85, 80, 80], array_column($plan['nextRows'], 'metric'));
};

$tests['compound select window recursive limit next181 yield tape current'] = static function (TestRunner $t) use ($summary181): void {
    $current = $summary181()['yieldTape']['current'];
    $t->same(10, count($current));
    $t->same(['siteurl', 'rewrite_rules', 'seed:2:3'], array_column(array_slice($current, 0, 3), 'label'));
    $t->same(['table', 'table', 'recursive'], array_column(array_slice($current, 0, 3), 'source'));
    $t->same([false, true, true], array_column(array_slice($current, 0, 3), 'admittedByFinalLimit'));
    $t->same([], array_values(array_filter(array_column($current, 'duplicateSuppressed'))));
};

$tests['compound select window recursive limit next181 yield tape next'] = static function (TestRunner $t) use ($summary181): void {
    $next = $summary181()['yieldTape']['next'];
    $t->same(14, count($next));
    $t->same(['siteurl', 'rewrite_rules', 'siteurl', 'plugin_alpha'], array_column(array_slice($next, 0, 4), 'label'));
    $t->same([false, true, true, true], array_column(array_slice($next, 0, 4), 'admittedByFinalLimit'));
    $t->true(in_array('theme_mods', array_column($next, 'label'), true));
    $t->same([], $summary181()['yieldTape']['suppressedDuplicateLabels']['next']);
};

$tests['compound select window recursive limit next181 recursive and windows'] = static function (TestRunner $t) use ($summary181): void {
    $plan = $summary181();
    $t->same('q', $plan['recursive']['name']);
    $t->same(['seed', 'seed:2'], $plan['recursive']['currentSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $plan['recursive']['currentEmittedLabels']);
    $t->same(['lag', 'lead'], $plan['windows']['functions']);
};

$tests['compound select window recursive limit next181 limit and boundary'] = static function (TestRunner $t) use ($summary181): void {
    $plan = $summary181();
    $t->same(['siteurl'], array_column($plan['limitTrace']['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl'], array_column($plan['limitTrace']['next']['skippedBeforeOffset'], 'label'));
    $t->same('rewrite_rules', $plan['boundary']['currentFirst']['label']);
    $t->same('rewrite_rules', $plan['boundary']['nextFirst']['label']);
    $t->same('home', $plan['boundary']['currentLast']['label']);
    $t->same('seed:2:3:4', $plan['boundary']['nextLast']['label']);
};

$tests['compound select window recursive limit next181 changed labels reasons'] = static function (TestRunner $t) use ($summary181): void {
    $plan = $summary181();
    $t->true(in_array('plugin_alpha', $plan['yieldTape']['changedLabels'], true));
    $t->true(in_array('home', $plan['yieldTape']['changedLabels'], true));
    $t->true(in_array('union-distinct-yield-tape', $plan['replanReasons'], true));
    $t->true(in_array('current-next-yield-boundary-changed', $plan['replanReasons'], true));
    $t->true(in_array('next-source-prelimit-rowset-expanded', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit next181 rejects non distinct union'] = static function (TestRunner $t) use ($currentTables181): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext181(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 7 LIMIT 5 OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(score, 1, score) OVER (ORDER BY score) FROM wp_options ORDER BY metric LIMIT 5 OFFSET 1",
        $currentTables181,
        $currentTables181,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit next181 generated yield tape ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext181($generatedSql, $tables, $tables);
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(count($plan['yieldTape']['current']), $plan['limitTrace']['current']['preLimitCount']);
        $t->true(isset($plan['yieldTape']['current'][0]['label'], $plan['yieldTape']['current'][0]['source']));
        $t->same(false, in_array('seed_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
