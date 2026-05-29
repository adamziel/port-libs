<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions211 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 72],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptions211 = [
    ...$currentOptions211,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'no', 'score' => 84],
];
$currentTables211 = ['wp_options' => $currentOptions211];
$nextTables211 = ['wp_options' => $nextOptions211];

$sql211 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 134)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 11
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       sum(score) FILTER (WHERE score >= 100) OVER (
           ORDER BY score DESC, id
           ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       count(*) FILTER (WHERE autoload = 'yes') OVER (
           ORDER BY score DESC, option_id
           ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM wp_options
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       0 AS metric
  FROM wp_options
 WHERE autoload = 'no'
UNION
SELECT id,
       label,
       score AS metric
  FROM q
 ORDER BY metric DESC, id
 LIMIT 7 OFFSET 1
SQL;

$summary211 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext211($sql211, $currentTables211, $nextTables211, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next211 status dependencies'] = static function (TestRunner $t) use ($summary211): void {
    $plan = $summary211();
    $t->same('compound-select-window-recursive-limit-current-source-next211-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-window-filter-next211',
        'sqlite-select-sql-recursive-limit-offset-next211',
        'sqlite-compound-except-union-filter-current-source-next211',
    ], $plan['dependencies']);
    $t->contains('FILTERed aggregate window frames', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next211 compound metadata'] = static function (TestRunner $t) use ($summary211): void {
    $compound = $summary211()['compound'];
    $t->same(['UNION ALL', 'EXCEPT', 'UNION'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([7, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasExceptFilterFence']);
    $t->true($compound['hasUnionDistinctTail']);
};

$tests['compound select window recursive limit current source next211 current rows'] = static function (TestRunner $t) use ($summary211): void {
    $rows = $summary211()['currentRows'];
    $t->same(['seed:2:3', 'seed:2:3:4:5', 'seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5:6', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same([235, 213, 123, 112, 101, 101, 90], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next211 next rows'] = static function (TestRunner $t) use ($summary211): void {
    $rows = $summary211()['nextRows'];
    $t->same(['seed:2:3', 'seed:2:3:4:5', 'seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5:6', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same([235, 213, 123, 112, 101, 101, 90], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next211 filtered window metadata'] = static function (TestRunner $t) use ($summary211): void {
    $windows = $summary211()['windows'];
    $t->same(['sum', 'count'], $windows['functions']);
    $t->same(['sum', 'count'], $windows['filteredFunctions']);
    $t->same(2, $windows['filterCount']);
    $t->same(['score', 'autoload'], array_column($windows['current'], 'filterColumn'));
    $t->same([true, true], array_column($windows['current'], 'hasFrame'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit current source next211 filtered metrics'] = static function (TestRunner $t) use ($summary211): void {
    $windows = $summary211()['windows'];
    $t->same([336, 235, 213, 123, 112], array_slice($windows['sumFilterMetrics'], 0, 5));
    $t->same([3, 2, 2, 1], $windows['countFilterMetrics']);
    $t->same(['seed:2:3:4:5:6:7'], $windows['filteredOutLabels']);
};

$tests['compound select window recursive limit current source next211 recursive queue'] = static function (TestRunner $t) use ($summary211): void {
    $queue = $summary211()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same('seed:2:3:4:5:6:7', $queue['currentEmittedLabels'][5]);
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next211 source window deltas'] = static function (TestRunner $t) use ($summary211): void {
    $source = $summary211()['sourceWindow'];
    $t->same(['seed:2:3:4'], $source['currentSkippedLabels']);
    $t->same(['seed:2:3:4'], $source['nextSkippedLabels']);
    $t->same(['plugin_prime', 'theme_mods_next'], $source['nextOnlyPreLimitLabels']);
    $t->same([], $source['currentOnlyPreLimitLabels']);
    $t->true(in_array('cache_seed', $source['nextTruncatedLabels'], true));
};

$tests['compound select window recursive limit current source next211 cursor fence'] = static function (TestRunner $t) use ($summary211): void {
    $first = $summary211();
    $second = $summary211($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
};

$tests['compound select window recursive limit current source next211 rejects stale cursor'] = static function (TestRunner $t) use ($summary211): void {
    $cursor = $summary211()['cursor'];
    $cursor['currentToken'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary211($cursor));
};

$tests['compound select window recursive limit current source next211 limit trace'] = static function (TestRunner $t) use ($summary211): void {
    $trace = $summary211()['limitTrace'];
    $t->same(15, $trace['current']['preLimitCount']);
    $t->same(17, $trace['next']['preLimitCount']);
    $t->same(7, $trace['current']['finalCount']);
    $t->same(['seed:2:3:4'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], array_slice(array_column($trace['current']['truncatedAfterLimit'], 'label'), 0, 2));
};

$tests['compound select window recursive limit current source next211 replan reasons'] = static function (TestRunner $t) use ($summary211): void {
    $plan = $summary211();
    $t->contains('avoids accepted next209', $plan['non_overlap']);
    $t->true(in_array('compound-filtered-window-current-source-next211', $plan['replanReasons'], true));
    $t->true(in_array('except-removes-filtered-window-row-next211', $plan['replanReasons'], true));
    $t->true(in_array('union-distinct-after-filter-window-next211', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next211 rejects missing filter'] = static function (TestRunner $t) use ($currentTables211): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext211(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 134) UNION ALL SELECT id + 1, label, score - 11 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM q UNION ALL SELECT option_id, option_name, count(*) OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) FROM wp_options EXCEPT SELECT option_id, option_name, 0 FROM wp_options UNION SELECT id, label, score FROM q ORDER BY metric DESC, id LIMIT 7 OFFSET 1",
        $currentTables211,
        $currentTables211,
    ));
};

$tests['compound select window recursive limit current source next211 rejects missing except'] = static function (TestRunner $t) use ($currentTables211): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext211(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 134) UNION ALL SELECT id + 1, label, score - 11 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, sum(score) FILTER (WHERE score >= 100) OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM q UNION ALL SELECT option_id, option_name, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) FROM wp_options UNION SELECT id, label, score FROM q ORDER BY metric DESC, id LIMIT 7 OFFSET 1",
        $currentTables211,
        $currentTables211,
    ));
};

foreach (range(1, 56) as $case) {
    $tests['compound select window recursive limit current source next211 generated filtered boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 5 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 120 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 95 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 72 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 40 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 112 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (134 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 11 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, sum(score) FILTER (WHERE score >= " . (100 + $case) . ") OVER (ORDER BY score DESC, id ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY score DESC, option_id ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM wp_options EXCEPT SELECT option_id AS id, option_name AS label, 0 AS metric FROM wp_options WHERE autoload = 'no' UNION SELECT id, label, score AS metric FROM q ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext211($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext211($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['sum', 'count'], $plan['windows']['filteredFunctions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true($plan['windows']['sumFilterMetrics'][0] > $plan['windows']['sumFilterMetrics'][1]);
    };
}

return $tests;
