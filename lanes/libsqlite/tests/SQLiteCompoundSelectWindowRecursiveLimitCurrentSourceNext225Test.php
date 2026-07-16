<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions225 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 72],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptions225 = [
    ...$currentOptions225,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 84],
];
$currentTables225 = ['wp_options' => $currentOptions225];
$nextTables225 = ['wp_options' => $nextOptions225];

$sql225 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 132)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric
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
INTERSECT
SELECT id,
       label,
       metric
  FROM (
       SELECT id,
              label,
              lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric
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
  )
EXCEPT
SELECT id,
       label,
       metric
  FROM (
       SELECT id,
              label,
              lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric
         FROM q
        WHERE id = 3
       UNION ALL
       SELECT option_id AS id,
              option_name AS label,
              last_value(score) OVER (
                  PARTITION BY autoload
                  ORDER BY score DESC, option_id
                  ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
              ) AS metric
         FROM wp_options
        WHERE option_name = 'home'
  )
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$summary225 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLagLastValueFence($sql225, $currentTables225, $nextTables225, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next225 status dependencies'] = static function (TestRunner $t) use ($summary225): void {
    $plan = $summary225();
    $t->same('compound-select-window-recursive-limit-current-source-next225-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next225',
        'sqlite-select-sql-lag-last-value-window-next225',
        'sqlite-compound-intersect-except-current-source-token-fence-next225',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next225 compound metadata'] = static function (TestRunner $t) use ($summary225): void {
    $compound = $summary225()['compound'];
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectMiddle']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source next225 current row boundary'] = static function (TestRunner $t) use ($summary225): void {
    $rows = $summary225()['currentRows'];
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'siteurl', 'seed:2:3:4:5:6:7'], array_column($rows, 'label'));
    $t->same([123, 114, 105, 96, 95, 87], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next225 next row boundary'] = static function (TestRunner $t) use ($summary225): void {
    $rows = $summary225()['nextRows'];
    $t->same(['seed:2:3', 'seed:2:3:4', 'siteurl', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'plugin_prime'], array_column($rows, 'label'));
    $t->same([123, 114, 112, 105, 96, 95], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next225 recursive queue trace'] = static function (TestRunner $t) use ($summary225): void {
    $queue = $summary225()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same('seed:2:3:4:5:6:7:8', $queue['currentEmittedLabels'][6]);
    $t->same([8, 8], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next225 window shape'] = static function (TestRunner $t) use ($summary225): void {
    $windows = $summary225()['windows'];
    $t->same(['lag', 'last_value'], $windows['functions']);
    $t->same(['last_value'], $windows['valueFunctions']);
    $t->same([false, true], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same(78, $windows['lagMetrics']['seed:2:3:4:5:6:7:8']);
    $t->same(72, $windows['lastValueMetrics']['rewrite_rules']);
};

$tests['compound select window recursive limit current source next225 token fence'] = static function (TestRunner $t) use ($summary225): void {
    $first = $summary225();
    $second = $summary225($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
    $t->same(['plugin_prime'], $first['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['seed:2:3:4:5:6:7'], $first['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next225 rejects stale cursor'] = static function (TestRunner $t) use ($summary225): void {
    $cursor = $summary225()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary225($cursor));
};

$tests['compound select window recursive limit current source next225 limit trace'] = static function (TestRunner $t) use ($summary225): void {
    $trace = $summary225()['limitTrace'];
    $t->same(['seed:2'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6:7:8', 'home'], array_slice(array_column($trace['current']['truncatedAfterLimit'], 'label'), 0, 2));
    $t->same(['seed:2:3:4:5:6:7', 'home'], array_slice(array_column($trace['next']['truncatedAfterLimit'], 'label'), 0, 2));
};

$tests['compound select window recursive limit current source next225 replan reasons'] = static function (TestRunner $t) use ($summary225): void {
    $plan = $summary225();
    $t->contains('avoids accepted next219', $plan['non_overlap']);
    $t->true(in_array('compound-lag-last-value-current-source-next225', $plan['replanReasons'], true));
    $t->true(in_array('recursive-ordered-limit-before-value-windows-next225', $plan['replanReasons'], true));
    $t->true(in_array('intersect-before-except-window-output-next225', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next225 rejects missing last value'] = static function (TestRunner $t) use ($currentTables225): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLagLastValueFence(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 132) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric FROM q) EXCEPT SELECT id, label, metric FROM (SELECT id, label, lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric FROM q WHERE id = 3) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables225,
        $currentTables225,
    ));
};

$tests['compound select window recursive limit current source next225 rejects unordered recursive limit'] = static function (TestRunner $t) use ($currentTables225): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLagLastValueFence(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 132) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 8 LIMIT 7 OFFSET 1) SELECT id, label, lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, last_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric FROM q) EXCEPT SELECT id, label, metric FROM (SELECT id, label, lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric FROM q WHERE id = 3) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables225,
        $currentTables225,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source next225 generated intersect except value boundary ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (132 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 9 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, last_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, last_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes') EXCEPT SELECT id, label, metric FROM (SELECT id, label, lag(score, 1, score) OVER (ORDER BY score DESC, id) AS metric FROM q WHERE id = 3 UNION ALL SELECT option_id AS id, option_name AS label, last_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE option_name = 'home_{$case}') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLagLastValueFence($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLagLastValueFence($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['lag', 'last_value'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true(isset($plan['windows']['lagMetrics']['seed_' . $case . ':2:3:4:5']));
    };
}

return $tests;
