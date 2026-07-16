<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions200 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 4, 'option_name' => 'transient_seed', 'autoload' => 'no', 'score' => 30],
];
$nextOptions200 = [
    ...$currentOptions200,
    ['option_id' => 5, 'option_name' => 'plugin_loaded', 'autoload' => 'yes', 'score' => 116],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 88],
];
$currentTables200 = ['wp_options' => $currentOptions200];
$nextTables200 = ['wp_options' => $nextOptions200];

$sql200 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 124)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       rank() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       last_value(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT id,
       label,
       score AS metric
  FROM q
EXCEPT
SELECT 2 AS id,
       'home' AS label,
       90 AS metric
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summary200 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankLastValueUnionExcept($sql200, $currentTables200, $nextTables200, $cursor);
$tests = [];

$tests['compound select window recursive limit rankLastValueUnionExcept status dependencies'] = static function (TestRunner $t) use ($summary200): void {
    $plan = $summary200();
    $t->same('compound-select-window-recursive-limit-current-source-rankLastValueUnionExcept-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-order-limit-offset-rankLastValueUnionExcept',
        'sqlite-window-rank-last-value-compound-rankLastValueUnionExcept',
        'sqlite-compound-union-distinct-except-final-limit-rankLastValueUnionExcept',
    ], $plan['dependencies']);
};

$tests['compound select window recursive limit rankLastValueUnionExcept compound metadata'] = static function (TestRunner $t) use ($summary200): void {
    $compound = $summary200()['compound'];
    $t->same(['UNION ALL', 'UNION', 'EXCEPT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([5, 1], [$compound['limit'], $compound['offset']]);
};

$tests['compound select window recursive limit rankLastValueUnionExcept recursive order trace'] = static function (TestRunner $t) use ($summary200): void {
    $recursive = $summary200()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $recursive['currentEmittedLabels']);
    $t->same(7, $recursive['currentTraceCount']);
    $t->same([0, 0], [$recursive['currentLimitRemaining'], $recursive['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit rankLastValueUnionExcept window metadata'] = static function (TestRunner $t) use ($summary200): void {
    $windows = $summary200()['windows'];
    $t->same(['rank', 'last_value'], $windows['functions']);
    $t->same(['last_value'], $windows['valueFunctions']);
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
    $t->same([null, 'ROWS'], array_column($windows['current'], 'frameUnit'));
};

$tests['compound select window recursive limit rankLastValueUnionExcept current boundary'] = static function (TestRunner $t) use ($summary200): void {
    $boundary = $summary200()['distinctExceptBoundary'];
    $t->same(['seed:2:3', 'seed:2:3:4', 'siteurl', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $boundary['currentAdmittedLabels']);
    $t->same(['seed:2'], $boundary['currentSkippedByFinalOffset']);
    $t->same(['home', 'rewrite_rules', 'seed:2:3:4:5:6:7', 'seed:2:3:4:5:6:7', 'seed:2:3:4:5:6', 'seed:2:3:4:5', 'seed:2:3:4', 'seed:2:3', 'seed:2'], $boundary['currentTruncatedByFinalLimit']);
    $t->same([], $boundary['deduplicatedLabels']);
};

$tests['compound select window recursive limit rankLastValueUnionExcept next boundary shifts'] = static function (TestRunner $t) use ($summary200): void {
    $boundary = $summary200()['distinctExceptBoundary'];
    $t->same(['seed:2:3', 'plugin_loaded', 'seed:2:3:4', 'siteurl', 'home'], $boundary['nextAdmittedLabels']);
    $t->true(in_array('plugin_loaded', $boundary['gainedAdmittedLabels'], true));
    $t->true(in_array('home', $boundary['gainedAdmittedLabels'], true));
    $t->true(in_array('seed:2:3:4:5', $boundary['lostAdmittedLabels'], true));
    $t->true(in_array('seed:2:3:4:5:6', $boundary['lostAdmittedLabels'], true));
    $t->same(64, strlen($boundary['currentToken']));
    $t->same(64, strlen($boundary['nextToken']));
    $t->same(false, $boundary['currentToken'] === $boundary['nextToken']);
};

$tests['compound select window recursive limit rankLastValueUnionExcept cursor accepts current token'] = static function (TestRunner $t) use ($summary200): void {
    $first = $summary200();
    $second = $summary200($first['cursor']);
    $t->same($first['distinctExceptBoundary']['currentToken'], $second['distinctExceptBoundary']['currentToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
};

$tests['compound select window recursive limit rankLastValueUnionExcept rejects stale cursor'] = static function (TestRunner $t) use ($summary200): void {
    $cursor = $summary200()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary200($cursor));
};

$tests['compound select window recursive limit rankLastValueUnionExcept replan reasons'] = static function (TestRunner $t) use ($summary200): void {
    $reasons = $summary200()['replanReasons'];
    $t->true(in_array('compound-union-distinct-except-window-recursive-limit-rankLastValueUnionExcept', $reasons, true));
    $t->true(in_array('recursive-order-limit-offset-before-window-rankLastValueUnionExcept', $reasons, true));
    $t->true(in_array('rank-last-value-before-compound-distinct-rankLastValueUnionExcept', $reasons, true));
    $t->true(in_array('application-setting-preview-distinct-except-boundary-rankLastValueUnionExcept', $reasons, true));
};

$tests['compound select window recursive limit rankLastValueUnionExcept dependency closure and non overlap'] = static function (TestRunner $t) use ($summary200): void {
    $plan = $summary200();
    $t->contains('no new support component needed', $plan['dependency_closure']);
    $t->contains('rankLastValueUnionExcept covers recursive ORDER queue', $plan['non_overlap']);
    $t->contains('UNION distinct plus EXCEPT', $plan['non_overlap']);
};

$tests['compound select window recursive limit rankLastValueUnionExcept rejects missing union distinct'] = static function (TestRunner $t) use ($currentTables200): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankLastValueUnionExcept(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 ORDER BY score LIMIT 2 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, last_value(score) OVER (ORDER BY score ROWS BETWEEN CURRENT ROW AND CURRENT ROW) FROM wp_options EXCEPT SELECT 2, 'home', 1 ORDER BY metric LIMIT 2 OFFSET 0",
        $currentTables200,
        $currentTables200,
    ));
};

$tests['compound select window recursive limit rankLastValueUnionExcept rejects missing last value'] = static function (TestRunner $t) use ($currentTables200): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankLastValueUnionExcept(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 ORDER BY score LIMIT 2 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score) FROM wp_options UNION SELECT id, label, score FROM q EXCEPT SELECT 2, 'home', 1 ORDER BY metric LIMIT 2 OFFSET 0",
        $currentTables200,
        $currentTables200,
    ));
};

$tests['compound select window recursive limit rankLastValueUnionExcept rejects missing recursive order'] = static function (TestRunner $t) use ($currentTables200): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankLastValueUnionExcept(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, last_value(score) OVER (ORDER BY score ROWS BETWEEN CURRENT ROW AND CURRENT ROW) FROM wp_options UNION SELECT id, label, score FROM q EXCEPT SELECT 2, 'home', 1 ORDER BY metric LIMIT 2 OFFSET 0",
        $currentTables200,
        $currentTables200,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound select window recursive limit rankLastValueUnionExcept generated union except fence ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 30 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'queued_' . $case, 'autoload' => 'yes', 'score' => 116 + $case];
        $nextTables['wp_options'][] = ['option_id' => 6, 'option_name' => 'theme_' . $case, 'autoload' => 'yes', 'score' => 88 + $case];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (124 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 9 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, last_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' UNION SELECT id, label, score AS metric FROM q EXCEPT SELECT 2 AS id, 'home_{$case}' AS label, " . (90 + $case) . " AS metric ORDER BY metric DESC, id LIMIT 5 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankLastValueUnionExcept($generatedSql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankLastValueUnionExcept($generatedSql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same(['UNION ALL', 'UNION', 'EXCEPT'], $plan['compound']['operators']);
        $t->same(count($rows), count($plan['currentRows']));
        $t->same($plan['distinctExceptBoundary']['currentToken'], $again['distinctExceptBoundary']['currentToken']);
        $t->true(in_array('queued_' . $case, $plan['distinctExceptBoundary']['gainedAdmittedLabels'], true));
    };
}

return $tests;
