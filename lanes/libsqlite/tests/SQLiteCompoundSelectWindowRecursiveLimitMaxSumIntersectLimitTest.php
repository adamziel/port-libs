<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptionsMaxSumIntersectLimit = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptionsMaxSumIntersectLimit = [
    ...$currentOptionsMaxSumIntersectLimit,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 70],
];
$currentTablesMaxSumIntersectLimit = ['wp_options' => $currentOptionsMaxSumIntersectLimit];
$nextTablesMaxSumIntersectLimit = ['wp_options' => $nextOptionsMaxSumIntersectLimit];

$sqlMaxSumIntersectLimit = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 120)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 5
      FROM q
     WHERE id < 7
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       max(score) OVER (
           ORDER BY id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       sum(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC
           ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id, label, metric
  FROM (
        SELECT id,
               label,
               max(score) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric
          FROM q
        UNION ALL
        SELECT option_id AS id,
               option_name AS label,
               sum(score) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric
          FROM wp_options
         WHERE autoload = 'yes'
       )
 WHERE metric >= 90
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       score AS metric
  FROM wp_options
 WHERE option_name = 'home'
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summaryMaxSumIntersectLimit = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMaxSumIntersectLimit($sqlMaxSumIntersectLimit, $currentTablesMaxSumIntersectLimit, $nextTablesMaxSumIntersectLimit, $cursor);
$tests = [];

$tests['compound select window recursive limit current source max-sum-intersect-limit status dependencies'] = static function (TestRunner $t) use ($summaryMaxSumIntersectLimit): void {
    $plan = $summaryMaxSumIntersectLimit();
    $t->same('compound-select-window-recursive-limit-current-source-max-sum-intersect-limit-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-max-sum-intersect-limit',
        'sqlite-select-sql-max-sum-window-max-sum-intersect-limit',
        'sqlite-compound-intersect-current-source-token-fence-max-sum-intersect-limit',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source max-sum-intersect-limit compound metadata'] = static function (TestRunner $t) use ($summaryMaxSumIntersectLimit): void {
    $compound = $summaryMaxSumIntersectLimit()['compound'];
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([5, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectTail']);
};

$tests['compound select window recursive limit current source max-sum-intersect-limit current rows'] = static function (TestRunner $t) use ($summaryMaxSumIntersectLimit): void {
    $rows = $summaryMaxSumIntersectLimit()['currentRows'];
    $t->same(5, count($rows));
    $t->same(['home', 'seed:2', 'seed:2:3', 'seed:2:3:4', 'siteurl'], array_column($rows, 'label'));
    $t->same([190, 115, 110, 105, 100], array_map('intval', array_column($rows, 'metric')));
};

$tests['compound select window recursive limit current source max-sum-intersect-limit next source boundary'] = static function (TestRunner $t) use ($summaryMaxSumIntersectLimit): void {
    $plan = $summaryMaxSumIntersectLimit();
    $t->same(['rewrite_rules', 'theme_mods_next', 'plugin_prime', 'seed:2', 'seed:2:3'], array_column($plan['nextRows'], 'label'));
    $t->same(['rewrite_rules', 'theme_mods_next', 'plugin_prime'], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['home', 'seed:2:3:4', 'siteurl'], $plan['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source max-sum-intersect-limit recursive queue trace'] = static function (TestRunner $t) use ($summaryMaxSumIntersectLimit): void {
    $queue = $summaryMaxSumIntersectLimit()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source max-sum-intersect-limit aggregate window shape'] = static function (TestRunner $t) use ($summaryMaxSumIntersectLimit): void {
    $windows = $summaryMaxSumIntersectLimit()['windows'];
    $t->same(['max', 'sum'], $windows['functions']);
    $t->same([true, true], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([270, 190, 115, 110, 105], array_slice(array_map('intval', $windows['aggregateMetrics']), 0, 5));
    $t->true(in_array('seed:2:3:4', $windows['textMetrics'], true));
};

$tests['compound select window recursive limit current source max-sum-intersect-limit token fence'] = static function (TestRunner $t) use ($summaryMaxSumIntersectLimit): void {
    $first = $summaryMaxSumIntersectLimit();
    $second = $summaryMaxSumIntersectLimit($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(6, $first['cursor']['nextOffset']);
};

$tests['compound select window recursive limit current source max-sum-intersect-limit rejects stale cursor'] = static function (TestRunner $t) use ($summaryMaxSumIntersectLimit): void {
    $cursor = $summaryMaxSumIntersectLimit()['cursor'];
    $cursor['currentToken'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summaryMaxSumIntersectLimit($cursor));
};

$tests['compound select window recursive limit current source max-sum-intersect-limit limit trace'] = static function (TestRunner $t) use ($summaryMaxSumIntersectLimit): void {
    $trace = $summaryMaxSumIntersectLimit()['limitTrace'];
    $t->same(1, count($trace['current']['skippedBeforeOffset']));
    $t->same(['rewrite_rules'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(9, $trace['current']['preLimitCount']);
    $t->same(11, $trace['next']['preLimitCount']);
    $t->same(5, $trace['current']['finalCount']);
};

$tests['compound select window recursive limit current source max-sum-intersect-limit replan reasons'] = static function (TestRunner $t) use ($summaryMaxSumIntersectLimit): void {
    $plan = $summaryMaxSumIntersectLimit();
    $t->contains('max/sum window output', $plan['non_overlap']);
    $t->true(in_array('compound-max-sum-current-source-max-sum-intersect-limit', $plan['replanReasons'], true));
    $t->true(in_array('recursive-queue-exhausted-before-intersect-max-sum-intersect-limit', $plan['replanReasons'], true));
    $t->true(in_array('intersect-window-membership-before-final-limit-max-sum-intersect-limit', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source max-sum-intersect-limit base rows match executor'] = static function (TestRunner $t) use ($sqlMaxSumIntersectLimit, $currentTablesMaxSumIntersectLimit, $summaryMaxSumIntersectLimit): void {
    $t->same(SQLiteSelectSql::execute($sqlMaxSumIntersectLimit, $currentTablesMaxSumIntersectLimit), $summaryMaxSumIntersectLimit()['currentRows']);
};

$tests['compound select window recursive limit current source max-sum-intersect-limit rejects missing sum'] = static function (TestRunner $t) use ($currentTablesMaxSumIntersectLimit): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMaxSumIntersectLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 120) UNION ALL SELECT id + 1, label, score - 5 FROM q WHERE id < 7 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, max(score) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM q UNION ALL SELECT option_id, option_name, max(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, max(score) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM q) ORDER BY metric DESC, id LIMIT 5 OFFSET 1",
        $currentTablesMaxSumIntersectLimit,
        $currentTablesMaxSumIntersectLimit,
    ));
};

$tests['compound select window recursive limit current source max-sum-intersect-limit rejects missing intersect'] = static function (TestRunner $t) use ($currentTablesMaxSumIntersectLimit): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMaxSumIntersectLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 120) UNION ALL SELECT id + 1, label, score - 5 FROM q WHERE id < 7 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, max(score) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM q UNION ALL SELECT option_id, option_name, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) FROM wp_options ORDER BY metric DESC, id LIMIT 5 OFFSET 1",
        $currentTablesMaxSumIntersectLimit,
        $currentTablesMaxSumIntersectLimit,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit current source max-sum-intersect-limit generated aggregate boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 80 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 40 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 95 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (120 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 5 FROM q WHERE id < 7 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, max(score) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, sum(score) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, max(score) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, sum(score) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM wp_options WHERE autoload = 'yes') WHERE metric >= " . (90 + $case) . " EXCEPT SELECT option_id AS id, option_name AS label, score AS metric FROM wp_options WHERE option_name = 'home_{$case}' ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMaxSumIntersectLimit($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMaxSumIntersectLimit($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['max', 'sum'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true(in_array('plugin_' . $case, $plan['sourceWindow']['nextOnlyAdmittedLabels'], true));
    };
}

return $tests;
