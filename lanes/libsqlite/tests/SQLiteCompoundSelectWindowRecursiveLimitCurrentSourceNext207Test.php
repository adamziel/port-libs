<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions207 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
];
$nextOptions207 = [
    ...$currentOptions207,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 84],
];
$currentTables207 = ['wp_options' => $currentOptions207];
$nextTables207 = ['wp_options' => $nextOptions207];

$sql207 = <<<'SQL'
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
EXCEPT
SELECT 2 AS id,
       'seed:2' AS label,
       120 AS metric
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

$summary207 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext207($sql207, $currentTables207, $nextTables207, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next207 status dependencies'] = static function (TestRunner $t) use ($summary207): void {
    $plan = $summary207();
    $t->same('compound-select-window-recursive-limit-current-source-next207-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next207',
        'sqlite-select-sql-lead-nth-value-window-except-next207',
        'sqlite-compound-except-intersect-current-source-token-fence-next207',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next207 compound metadata'] = static function (TestRunner $t) use ($summary207): void {
    $compound = $summary207()['compound'];
    $t->same(['UNION ALL', 'EXCEPT', 'INTERSECT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasExceptFence']);
    $t->true($compound['hasIntersectTail']);
};

$tests['compound select window recursive limit current source next207 current row boundary'] = static function (TestRunner $t) use ($summary207): void {
    $rows = $summary207()['currentRows'];
    $t->same(['seed:2:3:4', 'siteurl', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'home', 'seed:2:3:4:5:6:7'], array_column($rows, 'label'));
    $t->same([100, 95, 90, 80, 70, 70], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next207 next row boundary'] = static function (TestRunner $t) use ($summary207): void {
    $rows = $summary207()['nextRows'];
    $t->same(['seed:2:3', 'seed:2:3:4', 'plugin_prime', 'seed:2:3:4:5', 'home', 'seed:2:3:4:5:6'], array_column($rows, 'label'));
    $t->same([110, 100, 95, 90, 84, 80], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next207 recursive queue trace'] = static function (TestRunner $t) use ($summary207): void {
    $queue = $summary207()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same('seed:2:3:4:5:6:7:8:9', $queue['currentEmittedLabels'][7]);
    $t->same([9, 9], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next207 window shape'] = static function (TestRunner $t) use ($summary207): void {
    $windows = $summary207()['windows'];
    $t->same(['lead', 'nth_value'], $windows['functions']);
    $t->same(['nth_value'], $windows['frameFunctions']);
    $t->same([9], $windows['leadDefaults']);
    $t->same([3], $windows['nthValueNullIds']);
    $t->same([false, true], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
};

$tests['compound select window recursive limit current source next207 token fence'] = static function (TestRunner $t) use ($summary207): void {
    $first = $summary207();
    $second = $summary207($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
    $t->same(['seed:2:3', 'plugin_prime'], $first['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['siteurl', 'seed:2:3:4:5:6:7'], $first['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next207 rejects stale cursor'] = static function (TestRunner $t) use ($summary207): void {
    $cursor = $summary207()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary207($cursor));
};

$tests['compound select window recursive limit current source next207 limit trace'] = static function (TestRunner $t) use ($summary207): void {
    $trace = $summary207()['limitTrace'];
    $t->same(['seed:2:3'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6:7:8', 'seed:2:3:4:5:6:7:8:9'], array_slice(array_column($trace['current']['truncatedAfterLimit'], 'label'), 0, 2));
    $t->same(['theme_mods_next', 'seed:2:3:4:5:6:7'], array_slice(array_column($trace['next']['truncatedAfterLimit'], 'label'), 0, 2));
};

$tests['compound select window recursive limit current source next207 replan reasons'] = static function (TestRunner $t) use ($summary207): void {
    $plan = $summary207();
    $t->contains('avoids accepted next206', $plan['non_overlap']);
    $t->true(in_array('compound-except-before-intersect-current-source-next207', $plan['replanReasons'], true));
    $t->true(in_array('recursive-ordered-limit-except-fence-next207', $plan['replanReasons'], true));
    $t->true(in_array('window-output-membership-after-except-next207', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next207 rejects missing except'] = static function (TestRunner $t) use ($currentTables207): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext207(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 8 OFFSET 1) SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables207,
        $currentTables207,
    ));
};

$tests['compound select window recursive limit current source next207 rejects unordered recursive limit'] = static function (TestRunner $t) use ($currentTables207): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext207(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 9 LIMIT 8 OFFSET 1) SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, nth_value(score, 2) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables207,
        $currentTables207,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source next207 generated lead intersect boundary ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 8 OFFSET 1) SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, nth_value(score, 2) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT 2 AS id, 'seed_{$case}:2' AS label, " . (120 + $case) . " AS metric INTERSECT SELECT id, label, metric FROM (SELECT id, label, lead(score, 1, -7) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, nth_value(score, 2) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext207($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext207($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['UNION ALL', 'EXCEPT', 'INTERSECT'], $plan['compound']['operators']);
        $t->same(['lead', 'nth_value'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->same([9], $plan['windows']['leadDefaults']);
    };
}

return $tests;
