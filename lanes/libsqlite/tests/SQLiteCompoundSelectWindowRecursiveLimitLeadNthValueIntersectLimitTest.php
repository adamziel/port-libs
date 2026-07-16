<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptionsLeadNthValueIntersectLimit = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
];
$nextOptionsLeadNthValueIntersectLimit = [
    ...$currentOptionsLeadNthValueIntersectLimit,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 84],
];
$currentTablesLeadNthValueIntersectLimit = ['wp_options' => $currentOptionsLeadNthValueIntersectLimit];
$nextTablesLeadNthValueIntersectLimit = ['wp_options' => $nextOptionsLeadNthValueIntersectLimit];

$sqlLeadNthValueIntersectLimit = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 8 OFFSET 1
)
SELECT id,
       label,
       lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       nth_value(score, 2) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id,
       label,
       metric
  FROM (
       SELECT id,
              label,
              lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric
         FROM q
       UNION ALL
       SELECT option_id AS id,
              option_name AS label,
              nth_value(score, 2) OVER (
                  PARTITION BY autoload
                  ORDER BY score DESC, option_id
                  ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
              ) AS metric
         FROM wp_options
        WHERE autoload = 'yes'
  )
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$summaryLeadNthValueIntersectLimit = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLeadNthValueIntersectLimit($sqlLeadNthValueIntersectLimit, $currentTablesLeadNthValueIntersectLimit, $nextTablesLeadNthValueIntersectLimit, $cursor);
$tests = [];

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit status dependencies'] = static function (TestRunner $t) use ($summaryLeadNthValueIntersectLimit): void {
    $plan = $summaryLeadNthValueIntersectLimit();
    $t->same('compound-select-window-recursive-limit-current-source-lead-nth-value-intersect-limit-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-lead-nth-value-intersect-limit',
        'sqlite-select-sql-lead-nth-value-window-lead-nth-value-intersect-limit',
        'sqlite-compound-intersect-current-source-token-fence-lead-nth-value-intersect-limit',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit compound metadata'] = static function (TestRunner $t) use ($summaryLeadNthValueIntersectLimit): void {
    $compound = $summaryLeadNthValueIntersectLimit()['compound'];
    $t->same(['UNION ALL', 'INTERSECT'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectTail']);
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit current row boundary'] = static function (TestRunner $t) use ($summaryLeadNthValueIntersectLimit): void {
    $rows = $summaryLeadNthValueIntersectLimit()['currentRows'];
    $t->same(['seed:2:3', 'seed:2:3:4', 'siteurl', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'home'], array_column($rows, 'label'));
    $t->same([110, 100, 95, 90, 80, 70], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit next row boundary'] = static function (TestRunner $t) use ($summaryLeadNthValueIntersectLimit): void {
    $rows = $summaryLeadNthValueIntersectLimit()['nextRows'];
    $t->same(['siteurl', 'seed:2:3', 'seed:2:3:4', 'plugin_prime', 'seed:2:3:4:5', 'home'], array_column($rows, 'label'));
    $t->same([112, 110, 100, 95, 90, 84], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit recursive queue trace'] = static function (TestRunner $t) use ($summaryLeadNthValueIntersectLimit): void {
    $queue = $summaryLeadNthValueIntersectLimit()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same('seed:2:3:4:5:6:7:8:9', $queue['currentEmittedLabels'][7]);
    $t->same([9, 9], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit window shape'] = static function (TestRunner $t) use ($summaryLeadNthValueIntersectLimit): void {
    $windows = $summaryLeadNthValueIntersectLimit()['windows'];
    $t->same(['lead', 'nth_value'], $windows['functions']);
    $t->same(['nth_value'], $windows['frameFunctions']);
    $t->same([9], $windows['leadDefaults']);
    $t->same([3], $windows['nthValueNullIds']);
    $t->same([false, true], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit token fence'] = static function (TestRunner $t) use ($summaryLeadNthValueIntersectLimit): void {
    $first = $summaryLeadNthValueIntersectLimit();
    $second = $summaryLeadNthValueIntersectLimit($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
    $t->same(['plugin_prime'], $first['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['seed:2:3:4:5:6'], $first['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit rejects stale cursor'] = static function (TestRunner $t) use ($summaryLeadNthValueIntersectLimit): void {
    $cursor = $summaryLeadNthValueIntersectLimit()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summaryLeadNthValueIntersectLimit($cursor));
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit limit trace'] = static function (TestRunner $t) use ($summaryLeadNthValueIntersectLimit): void {
    $trace = $summaryLeadNthValueIntersectLimit()['limitTrace'];
    $t->same(['seed:2'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6:7', 'seed:2:3:4:5:6:7:8'], array_slice(array_column($trace['current']['truncatedAfterLimit'], 'label'), 0, 2));
    $t->same(['seed:2:3:4:5:6', 'theme_mods_next'], array_slice(array_column($trace['next']['truncatedAfterLimit'], 'label'), 0, 2));
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit replan reasons'] = static function (TestRunner $t) use ($summaryLeadNthValueIntersectLimit): void {
    $plan = $summaryLeadNthValueIntersectLimit();
    $t->contains('avoids accepted lag-last-value-except', $plan['non_overlap']);
    $t->true(in_array('compound-lead-nth-value-current-source-lead-nth-value-intersect-limit', $plan['replanReasons'], true));
    $t->true(in_array('recursive-ordered-limit-before-intersect-windows-lead-nth-value-intersect-limit', $plan['replanReasons'], true));
    $t->true(in_array('intersect-after-window-output-lead-nth-value-intersect-limit', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit rejects missing nth value'] = static function (TestRunner $t) use ($currentTablesLeadNthValueIntersectLimit): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLeadNthValueIntersectLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 8 OFFSET 1) SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTablesLeadNthValueIntersectLimit,
        $currentTablesLeadNthValueIntersectLimit,
    ));
};

$tests['compound select window recursive limit current source lead-nth-value-intersect-limit rejects unordered recursive limit'] = static function (TestRunner $t) use ($currentTablesLeadNthValueIntersectLimit): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLeadNthValueIntersectLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 9 LIMIT 8 OFFSET 1) SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, nth_value(score, 2) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTablesLeadNthValueIntersectLimit,
        $currentTablesLeadNthValueIntersectLimit,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source lead-nth-value-intersect-limit generated lead intersect boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 120 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 95 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 40 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 112 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 8 OFFSET 1) SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, nth_value(score, 2) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, nth_value(score, 2) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLeadNthValueIntersectLimit($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLeadNthValueIntersectLimit($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['lead', 'nth_value'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->same([9], $plan['windows']['leadDefaults']);
    };
}

return $tests;
