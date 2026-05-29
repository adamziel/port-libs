<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions182 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 75],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 20],
];
$nextOptions182 = [
    ...$currentOptions182,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 85],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 65],
];
$currentTables182 = ['wp_options' => $currentOptions182];
$nextTables182 = ['wp_options' => $nextOptions182];

$sql182 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 100)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 7
     LIMIT 0
)
SELECT id,
       label,
       row_number() OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY score DESC) AS metric
  FROM wp_options
 WHERE score >= 60
 ORDER BY metric, id
 LIMIT 4 OFFSET 1
SQL;

$summary182 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionAllEmptyRecursiveArm($sql182, $currentTables182, $nextTables182);
$tests = [];

$tests['compound select window recursive limit union-all-empty-recursive-arm status dependencies'] = static function (TestRunner $t) use ($summary182): void {
    $plan = $summary182();
    $t->same('compound-select-window-recursive-limit-current-source-union-all-empty-recursive-arm-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-zero-union-all-empty-recursive-arm',
        'sqlite-select-sql-window-before-compound-union-all-empty-recursive-arm',
        'sqlite-select-sql-union-all-empty-recursive-arm-union-all-empty-recursive-arm',
        'sqlite-select-sql-compound-tail-limit-union-all-empty-recursive-arm',
        'sqlite-current-source-union-all-empty-recursive-arm',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit union-all-empty-recursive-arm compound metadata'] = static function (TestRunner $t) use ($summary182): void {
    $compound = $summary182()['compound'];
    $t->same(['UNION ALL', 'UNION ALL'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(4, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['allArmsAreUnionAll']);
};

$tests['compound select window recursive limit union-all-empty-recursive-arm current rows'] = static function (TestRunner $t) use ($summary182): void {
    $rows = $summary182()['currentRows'];
    $t->same([1, 2, 2, 3], array_column($rows, 'id'));
    $t->same(['siteurl', 'home', 'home', 'rewrite_rules'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit union-all-empty-recursive-arm next rows'] = static function (TestRunner $t) use ($summary182): void {
    $rows = $summary182()['nextRows'];
    $t->same([1, 5, 5, 2], array_column($rows, 'id'));
    $t->same(['siteurl', 'plugin_alpha', 'plugin_alpha', 'home'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit union-all-empty-recursive-arm prelimit rows'] = static function (TestRunner $t) use ($summary182): void {
    $plan = $summary182();
    $t->same(6, count($plan['currentPreLimitRows']));
    $t->same(10, count($plan['nextPreLimitRows']));
    $t->same(['siteurl', 'siteurl', 'home'], array_slice(array_column($plan['currentPreLimitRows'], 'label'), 0, 3));
    $t->same(['siteurl', 'siteurl', 'plugin_alpha', 'plugin_alpha'], array_slice(array_column($plan['nextPreLimitRows'], 'label'), 0, 4));
};

$tests['compound select window recursive limit union-all-empty-recursive-arm recursive limit zero trace'] = static function (TestRunner $t) use ($summary182): void {
    $recursive = $summary182()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same([], $recursive['currentRows']);
    $t->same([], $recursive['nextRows']);
    $t->same(['seed'], $recursive['currentSuppressedLabels']);
    $t->same(['seed'], $recursive['nextSuppressedLabels']);
    $t->same([], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
};

$tests['compound select window recursive limit union-all-empty-recursive-arm window metadata'] = static function (TestRunner $t) use ($summary182): void {
    $windows = $summary182()['windows'];
    $t->same(['row_number', 'rank', 'dense_rank'], $windows['functions']);
    $t->same(['metric', 'metric', 'metric'], array_column($windows['current'], 'alias'));
    $t->same([0, 1, 0], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2, 1], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit union-all-empty-recursive-arm limit trace'] = static function (TestRunner $t) use ($summary182): void {
    $trace = $summary182()['limitTrace'];
    $t->same(6, $trace['current']['preLimitCount']);
    $t->same(10, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['rewrite_rules'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['home', 'theme_mods', 'theme_mods', 'rewrite_rules', 'rewrite_rules'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit union-all-empty-recursive-arm source classes'] = static function (TestRunner $t) use ($summary182): void {
    $classes = $summary182()['sourceClasses'];
    $t->same(['table' => 4], $classes['current']);
    $t->same(['table' => 4], $classes['next']);
    $t->same(['table' => 6], $classes['preLimitCurrent']);
    $t->same(['table' => 10], $classes['preLimitNext']);
};

$tests['compound select window recursive limit union-all-empty-recursive-arm boundary delta'] = static function (TestRunner $t) use ($summary182): void {
    $boundary = $summary182()['boundary'];
    $t->same('siteurl', $boundary['currentFirst']['label']);
    $t->same('siteurl', $boundary['nextFirst']['label']);
    $t->same('rewrite_rules', $boundary['currentLast']['label']);
    $t->same('home', $boundary['nextLast']['label']);
    $t->contains('"label":"plugin_alpha"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"rewrite_rules"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit union-all-empty-recursive-arm replan reasons'] = static function (TestRunner $t) use ($summary182): void {
    $plan = $summary182();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->contains('"label":"rewrite_rules"', $changed);
    $t->true(in_array('limited-union-all-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-union-all-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-zero-suppressed-anchor', $plan['replanReasons'], true));
    $t->true(in_array('compound-union-all-preserves-window-duplicates', $plan['replanReasons'], true));
    $t->true(in_array('window-functions-materialized-after-empty-recursive-arm', $plan['replanReasons'], true));
    $t->true(in_array('compound-tail-limit-offset-after-empty-recursive-arm', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit union-all-empty-recursive-arm rejects non zero recursive limit'] = static function (TestRunner $t) use ($currentTables182): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionAllEmptyRecursiveArm(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 7 LIMIT 1) SELECT id, label, row_number() OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score) FROM wp_options UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY score) FROM wp_options ORDER BY metric LIMIT 4 OFFSET 1",
        $currentTables182,
        $currentTables182,
    ));
};

$tests['compound select window recursive limit union-all-empty-recursive-arm rejects distinct union'] = static function (TestRunner $t) use ($currentTables182): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionAllEmptyRecursiveArm(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 7 LIMIT 0) SELECT id, label, row_number() OVER (ORDER BY id) AS metric FROM q UNION SELECT option_id, option_name, rank() OVER (ORDER BY score) FROM wp_options UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY score) FROM wp_options ORDER BY metric LIMIT 4 OFFSET 1",
        $currentTables182,
        $currentTables182,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit union-all-empty-recursive-arm generated empty recursive arm ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 3 + ($case % 4);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 80 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => 60 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 20 + $case],
                ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (120 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 0) SELECT id, label, row_number() OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (ORDER BY score DESC) AS metric FROM wp_options WHERE score >= " . (60 + $case) . " ORDER BY metric, id LIMIT {$finalLimit} OFFSET 1";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);
        $trace = SQLiteSelectSql::recursiveCteCycleTrace("WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (120 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 0) SELECT * FROM q", $tables);

        $t->same($finalLimit, count($rows));
        $t->same([], $trace['rows']);
        $t->same('seed_' . $case, $trace['trace'][0]['current']['label']);
        $t->same(false, in_array('seed_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
