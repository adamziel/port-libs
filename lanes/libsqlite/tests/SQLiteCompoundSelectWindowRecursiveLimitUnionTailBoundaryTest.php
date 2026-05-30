<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions185 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 30],
];
$nextOptions185 = [
    ...$currentOptions185,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 88],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 70],
];
$currentTables185 = ['wp_options' => $currentOptions185];
$nextTables185 = ['wp_options' => $nextOptions185];

$sql185 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 120)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 15
      FROM q
     WHERE id < 8
     LIMIT 1 OFFSET 3
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC) AS metric
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY score DESC) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE score >= 70
 ORDER BY metric, id
 LIMIT 5 OFFSET 1
SQL;

$summary185 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionTailRecursiveLimitBoundary($sql185, $currentTables185, $nextTables185);
$tests = [];

$tests['compound select window recursive limit next185 status dependencies'] = static function (TestRunner $t) use ($summary185): void {
    $plan = $summary185();
    $t->same('compound-select-window-recursive-limit-current-source-next185-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-single-row-limit-offset-next185',
        'sqlite-select-sql-compound-union-distinct-before-union-all-next185',
        'sqlite-select-sql-window-before-distinct-compound-next185',
        'sqlite-select-sql-compound-tail-limit-offset-next185',
        'sqlite-current-source-next185',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit next185 compound metadata'] = static function (TestRunner $t) use ($summary185): void {
    $compound = $summary185()['compound'];
    $t->same(['UNION', 'UNION ALL'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->same(1, $compound['distinctArmIndex']);
    $t->true($compound['hasUnionAllTail']);
};

$tests['compound select window recursive limit next185 current rows'] = static function (TestRunner $t) use ($summary185): void {
    $rows = $summary185()['currentRows'];
    $t->same([1, 4, 2, 2, 3], array_column($rows, 'id'));
    $t->same(['siteurl', 'seed:2:3:4', 'home', 'home', 'rewrite_rules'], array_column($rows, 'label'));
    $t->same([1, 1, 2, 2, 3], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit next185 next rows'] = static function (TestRunner $t) use ($summary185): void {
    $rows = $summary185()['nextRows'];
    $t->same([1, 4, 5, 5, 2], array_column($rows, 'id'));
    $t->same(['siteurl', 'seed:2:3:4', 'plugin_alpha', 'plugin_alpha', 'home'], array_column($rows, 'label'));
    $t->same([1, 1, 2, 2, 3], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit next185 prelimit duplicate trace'] = static function (TestRunner $t) use ($summary185): void {
    $plan = $summary185();
    $t->same(6, count($plan['currentPreLimitRows']));
    $t->same(10, count($plan['nextPreLimitRows']));
    $t->same(['siteurl', 'siteurl', 'seed:2:3:4'], array_slice(array_column($plan['currentPreLimitRows'], 'label'), 0, 3));
    $t->same(['siteurl', 'siteurl', 'seed:2:3:4', 'plugin_alpha'], array_slice(array_column($plan['nextPreLimitRows'], 'label'), 0, 4));
    $t->same(['siteurl', 'home'], $plan['distinctUnion']['currentDuplicateLabels']);
    $t->same(['siteurl', 'plugin_alpha', 'home', 'theme_mods'], $plan['distinctUnion']['nextDuplicateLabels']);
};

$tests['compound select window recursive limit next185 recursive single-row trace'] = static function (TestRunner $t) use ($summary185): void {
    $recursive = $summary185()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed:2:3:4'], array_column($recursive['currentRows'], 'label'));
    $t->same(['seed', 'seed:2', 'seed:2:3'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2:3:4'], $recursive['currentEmittedLabels']);
    $t->same(4, $recursive['currentTraceCount']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound select window recursive limit next185 window metadata'] = static function (TestRunner $t) use ($summary185): void {
    $windows = $summary185()['windows'];
    $t->same(['row_number', 'dense_rank', 'rank'], $windows['functions']);
    $t->same(['metric', 'metric', 'metric'], array_column($windows['current'], 'alias'));
    $t->same([0, 0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 1, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit next185 limit trace'] = static function (TestRunner $t) use ($summary185): void {
    $trace = $summary185()['limitTrace'];
    $t->same(6, $trace['current']['preLimitCount']);
    $t->same(10, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same([], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['home', 'theme_mods', 'theme_mods', 'rewrite_rules'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit next185 source classes'] = static function (TestRunner $t) use ($summary185): void {
    $classes = $summary185()['sourceClasses'];
    $t->same(['recursive' => 1, 'table' => 4], $classes['current']);
    $t->same(['recursive' => 1, 'table' => 4], $classes['next']);
    $t->same(['recursive' => 1, 'table' => 5], $classes['preLimitCurrent']);
    $t->same(['recursive' => 1, 'table' => 9], $classes['preLimitNext']);
};

$tests['compound select window recursive limit next185 boundary delta'] = static function (TestRunner $t) use ($summary185): void {
    $boundary = $summary185()['boundary'];
    $t->same('siteurl', $boundary['currentFirst']['label']);
    $t->same('siteurl', $boundary['nextFirst']['label']);
    $t->same('rewrite_rules', $boundary['currentLast']['label']);
    $t->same('home', $boundary['nextLast']['label']);
    $t->contains('"label":"plugin_alpha"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"rewrite_rules"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit next185 replan reasons'] = static function (TestRunner $t) use ($summary185): void {
    $plan = $summary185();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->contains('"label":"rewrite_rules"', $changed);
    $t->true(in_array('limited-union-distinct-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-union-distinct-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('union-distinct-arm-collapsed-duplicates-before-union-all-tail', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-emitted-single-row', $plan['replanReasons'], true));
    $t->true(in_array('window-functions-materialized-before-distinct-union', $plan['replanReasons'], true));
    $t->true(in_array('compound-tail-limit-offset-after-distinct-union', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit next185 rejects union all first operator'] = static function (TestRunner $t) use ($currentTables185): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionTailRecursiveLimitBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 120) UNION ALL SELECT id + 1, label, score - 15 FROM q WHERE id < 8 LIMIT 1 OFFSET 3) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY score DESC) FROM wp_options UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options ORDER BY metric LIMIT 5 OFFSET 1",
        $currentTables185,
        $currentTables185,
    ));
};

$tests['compound select window recursive limit next185 rejects missing recursive single row limit'] = static function (TestRunner $t) use ($currentTables185): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionTailRecursiveLimitBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 120) UNION ALL SELECT id + 1, label, score - 15 FROM q WHERE id < 8 LIMIT 2 OFFSET 3) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q UNION SELECT option_id, option_name, dense_rank() OVER (ORDER BY score DESC) FROM wp_options UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options ORDER BY metric LIMIT 5 OFFSET 1",
        $currentTables185,
        $currentTables185,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit next185 generated distinct boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 85 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => 60 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 30 + $case],
                ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (130 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 12 FROM q WHERE id < 9 LIMIT 1 OFFSET 3) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, dense_rank() OVER (ORDER BY score DESC) AS metric FROM wp_options WHERE autoload = 'yes' UNION ALL SELECT option_id AS id, option_name AS label, rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE score >= " . (85 + $case) . " ORDER BY metric, id LIMIT 5 OFFSET 1";
        $rows = SQLiteSelectSql::execute($sql, $tables);
        $trace = SQLiteSelectSql::recursiveCteCycleTrace("WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (130 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 12 FROM q WHERE id < 9 LIMIT 1 OFFSET 3) SELECT * FROM q", $tables);

        $t->same(5, count($rows));
        $skipped = array_map(static fn (array $step): string => (string) $step['current']['label'], array_slice($trace['trace'], 0, 3));
        $t->same(['seed_' . $case, 'seed_' . $case . ':2', 'seed_' . $case . ':2:3'], $skipped);
        $t->same('seed_' . $case . ':2:3:4', $trace['rows'][0]['label']);
        $t->true(in_array('plugin_' . $case, array_column($rows, 'label'), true));
        $t->same(false, in_array('transient_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
