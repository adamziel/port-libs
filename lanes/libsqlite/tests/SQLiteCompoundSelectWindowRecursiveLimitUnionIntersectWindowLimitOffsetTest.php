<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptionsUnionIntersect = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 75],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 20],
];
$nextOptionsUnionIntersect = [
    ...$currentOptionsUnionIntersect,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 85],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 65],
];
$currentTablesUnionIntersect = ['wp_options' => $currentOptionsUnionIntersect];
$nextTablesUnionIntersect = ['wp_options' => $nextOptionsUnionIntersect];

$sqlUnionIntersect = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 100)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 6
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY score DESC) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id,
       label,
       metric
  FROM (
        SELECT option_id AS id,
               option_name AS label,
               dense_rank() OVER (ORDER BY score DESC) AS metric
          FROM wp_options
         WHERE score >= 60
        UNION ALL
        SELECT id,
               label,
               row_number() OVER (ORDER BY score DESC, id) AS metric
          FROM q
         WHERE id >= 3
  )
 ORDER BY metric, id
 LIMIT 4 OFFSET 1
SQL;

$summaryUnionIntersect = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionIntersectWindowLimitOffset($sqlUnionIntersect, $currentTablesUnionIntersect, $nextTablesUnionIntersect);
$tests = [];

$tests['compound select window recursive limit union-intersect-window-limit-offset status dependencies'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $plan = $summaryUnionIntersect();
    $t->same('compound-select-window-recursive-limit-current-source-union-intersect-window-limit-offset-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-offset-union-intersect-window-limit-offset',
        'sqlite-select-sql-compound-union-intersect-union-intersect-window-limit-offset',
        'sqlite-select-sql-window-row-number-dense-rank-union-intersect-window-limit-offset',
        'sqlite-current-source-union-intersect-window-limit-offset',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit union-intersect-window-limit-offset compound metadata'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $compound = $summaryUnionIntersect()['compound'];
    $t->same(['UNION', 'INTERSECT'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(4, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['hasUnionDistinct']);
    $t->true($compound['hasIntersect']);
    $t->same(2, $compound['intersectArmIndex']);
};

$tests['compound select window recursive limit union-intersect-window-limit-offset current rows'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $rows = $summaryUnionIntersect()['currentRows'];
    $t->same([2, 3], array_column($rows, 'id'));
    $t->same(['home', 'rewrite_rules'], array_column($rows, 'label'));
    $t->same([2, 3], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit union-intersect-window-limit-offset next rows'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $rows = $summaryUnionIntersect()['nextRows'];
    $t->same([5, 2, 6, 3], array_column($rows, 'id'));
    $t->same(['plugin_alpha', 'home', 'theme_mods', 'rewrite_rules'], array_column($rows, 'label'));
    $t->same([2, 3, 4, 5], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit union-intersect-window-limit-offset prelimit captures intersect source shift'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $plan = $summaryUnionIntersect();
    $t->same(['siteurl', 'home', 'rewrite_rules'], array_column($plan['currentPreLimitRows'], 'label'));
    $t->same(['siteurl', 'plugin_alpha', 'home', 'theme_mods', 'rewrite_rules'], array_column($plan['nextPreLimitRows'], 'label'));
    $t->true(in_array('theme_mods', $plan['intersect']['changedSurvivors'], true));
};

$tests['compound select window recursive limit union-intersect-window-limit-offset recursive trace'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $recursive = $summaryUnionIntersect()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit union-intersect-window-limit-offset window metadata'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $windows = $summaryUnionIntersect()['windows'];
    $t->same(['row_number', 'dense_rank'], $windows['functions']);
    $t->same(['metric', 'metric'], array_column($windows['current'], 'alias'));
    $t->same([0, 0], array_column($windows['current'], 'argumentCount'));
    $t->same([2, 1], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit union-intersect-window-limit-offset intersect diagnostics'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $intersect = $summaryUnionIntersect()['intersect'];
    $t->same(['siteurl', 'home', 'rewrite_rules', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $intersect['currentFilterLabels']);
    $t->same(['siteurl', 'home', 'rewrite_rules', 'plugin_alpha', 'theme_mods', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $intersect['nextFilterLabels']);
    $t->true(in_array('plugin_alpha', $intersect['changedSurvivors'], true));
    $t->true(in_array('theme_mods', $intersect['changedSurvivors'], true));
};

$tests['compound select window recursive limit union-intersect-window-limit-offset limit trace'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $trace = $summaryUnionIntersect()['limitTrace'];
    $t->same(3, $trace['current']['preLimitCount']);
    $t->same(5, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same([], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same([], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit union-intersect-window-limit-offset source classes'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $classes = $summaryUnionIntersect()['sourceClasses'];
    $t->same(['table' => 2], $classes['current']);
    $t->same(['table' => 4], $classes['next']);
};

$tests['compound select window recursive limit union-intersect-window-limit-offset boundary delta'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $boundary = $summaryUnionIntersect()['boundary'];
    $t->same('home', $boundary['currentFirst']['label']);
    $t->same('plugin_alpha', $boundary['nextFirst']['label']);
    $t->same('rewrite_rules', $boundary['currentLast']['label']);
    $t->same('rewrite_rules', $boundary['nextLast']['label']);
    $t->contains('"label":"plugin_alpha"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"theme_mods"', implode("\n", $boundary['gainedRows']));
};

$tests['compound select window recursive limit union-intersect-window-limit-offset replan reasons'] = static function (TestRunner $t) use ($summaryUnionIntersect): void {
    $plan = $summaryUnionIntersect();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->contains('"label":"theme_mods"', $changed);
    $t->true(in_array('limited-intersect-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-intersect-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-skipped-anchor', $plan['replanReasons'], true));
    $t->true(in_array('compound-union-distinct-before-intersect', $plan['replanReasons'], true));
    $t->true(in_array('derived-intersect-after-window', $plan['replanReasons'], true));
    $t->true(in_array('mixed-window-functions-before-intersect', $plan['replanReasons'], true));
    $t->true(in_array('compound-tail-limit-offset', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit union-intersect-window-limit-offset rejects union all'] = static function (TestRunner $t) use ($currentTablesUnionIntersect): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionIntersectWindowLimitOffset(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 6 LIMIT 5 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score) AS metric FROM q UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY score) FROM wp_options INTERSECT SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 3 OFFSET 1",
        $currentTablesUnionIntersect,
        $currentTablesUnionIntersect,
    ));
};

$tests['compound select window recursive limit union-intersect-window-limit-offset rejects missing intersect'] = static function (TestRunner $t) use ($currentTablesUnionIntersect): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionIntersectWindowLimitOffset(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 6 LIMIT 5 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score) AS metric FROM q UNION SELECT option_id, option_name, dense_rank() OVER (ORDER BY score) FROM wp_options ORDER BY metric LIMIT 3 OFFSET 1",
        $currentTablesUnionIntersect,
        $currentTablesUnionIntersect,
    ));
};

$tests['compound select window recursive limit union-intersect-window-limit-offset rejects missing final offset'] = static function (TestRunner $t) use ($currentTablesUnionIntersect): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionIntersectWindowLimitOffset(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 6 LIMIT 5 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score) AS metric FROM q UNION SELECT option_id, option_name, dense_rank() OVER (ORDER BY score) FROM wp_options INTERSECT SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 3",
        $currentTablesUnionIntersect,
        $currentTablesUnionIntersect,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit union-intersect-window-limit-offset generated intersect boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 4 + ($case % 4);
        $finalLimit = 2;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 85 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 10 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (120 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT {$recursiveLimit} OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, dense_rank() OVER (ORDER BY score DESC) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT option_id AS id, option_name AS label, dense_rank() OVER (ORDER BY score DESC) AS metric FROM wp_options WHERE score >= " . (65 + $case) . " UNION ALL SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q WHERE id >= 3) ORDER BY metric, id LIMIT {$finalLimit} OFFSET 1";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['metric']));
        $t->true($rows[0]['metric'] <= $rows[count($rows) - 1]['metric']);
        $t->same(false, in_array('seed_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
