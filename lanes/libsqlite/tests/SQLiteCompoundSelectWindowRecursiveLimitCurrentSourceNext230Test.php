<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions230 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 130],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 3, 'option_name' => 'plugin_old', 'autoload' => 'yes', 'score' => 94],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptions230 = [
    ...$currentOptions230,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 124],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 105],
];
$currentTables230 = ['wp_options' => $currentOptions230];
$nextTables230 = ['wp_options' => $nextOptions230];

$sql230 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 144)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       avg(score) OVER (
           ORDER BY score DESC
           ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING
       ) AS metric
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       first_value(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
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
              avg(score) OVER (
                  ORDER BY score DESC
                  ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING
              ) AS metric
         FROM q
       UNION
       SELECT option_id AS id,
              option_name AS label,
              first_value(score) OVER (
                  PARTITION BY autoload
                  ORDER BY score DESC, option_id
                  ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
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
              avg(score) OVER (
                  ORDER BY score DESC
                  ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING
              ) AS metric
         FROM q
        WHERE id = 4
       UNION
       SELECT option_id AS id,
              option_name AS label,
              first_value(score) OVER (
                  PARTITION BY autoload
                  ORDER BY score DESC, option_id
                  ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
              ) AS metric
         FROM wp_options
        WHERE option_name = 'plugin_old'
  )
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$summary230 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext230($sql230, $currentTables230, $nextTables230, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next230 status dependencies'] = static function (TestRunner $t) use ($summary230): void {
    $plan = $summary230();
    $t->same('compound-select-window-recursive-limit-current-source-next230-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next230',
        'sqlite-select-sql-avg-first-value-window-next230',
        'sqlite-compound-union-intersect-except-current-source-token-fence-next230',
    ], $plan['dependencies']);
    $t->contains('avg window dispatch', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next230 compound metadata'] = static function (TestRunner $t) use ($summary230): void {
    $compound = $summary230()['compound'];
    $t->same(['UNION', 'INTERSECT', 'EXCEPT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionDistinctHead']);
    $t->true($compound['hasIntersectMiddle']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source next230 avg window rows'] = static function (TestRunner $t) use ($summary230): void {
    $rows = $summary230()['currentRows'];
    $t->same(6, count($rows));
    $t->same(['siteurl', 'seed:2:3', 'home', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], array_column($rows, 'label'));
    $t->same([130.0, 128.0, 112.0, 112.0, 104.0, 96.0], array_map(static fn (mixed $value): float => round((float) $value, 6), array_column($rows, 'metric')));
};

$tests['compound select window recursive limit current source next230 next boundary'] = static function (TestRunner $t) use ($summary230): void {
    $plan = $summary230();
    $t->same(['plugin_prime', 'theme_mods_next'], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['siteurl', 'seed:2:3', 'home', 'seed:2:3:4:5'], $plan['sourceWindow']['currentOnlyAdmittedLabels']);
    $t->same(false, $plan['sourceWindow']['currentToken'] === $plan['sourceWindow']['nextToken']);
};

$tests['compound select window recursive limit current source next230 recursive queue trace'] = static function (TestRunner $t) use ($summary230): void {
    $queue = $summary230()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same([], $queue['currentSkippedLabels']);
    $t->same([], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same([8, 8], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next230 window shape'] = static function (TestRunner $t) use ($summary230): void {
    $windows = $summary230()['windows'];
    $t->same(['avg', 'first_value'], $windows['functions']);
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2], array_column($windows['current'], 'orderCount'));
    $t->true(in_array(128.0, array_map(static fn (float $value): float => round($value, 6), $windows['aggregateMetrics']), true));
    $t->true(in_array(124.0, array_map(static fn (float $value): float => round($value, 6), $windows['nextAggregateMetrics']), true));
};

$tests['compound select window recursive limit current source next230 token fence'] = static function (TestRunner $t) use ($summary230): void {
    $first = $summary230();
    $second = $summary230($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(7, $first['cursor']['nextOffset']);
};

$tests['compound select window recursive limit current source next230 rejects stale cursor'] = static function (TestRunner $t) use ($summary230): void {
    $cursor = $summary230()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary230($cursor));
};

$tests['compound select window recursive limit current source next230 limit trace'] = static function (TestRunner $t) use ($summary230): void {
    $trace = $summary230()['limitTrace'];
    $t->same(['seed:2'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(8, $trace['current']['preLimitCount']);
    $t->same(10, $trace['next']['preLimitCount']);
};

$tests['compound select window recursive limit current source next230 replan reasons'] = static function (TestRunner $t) use ($summary230): void {
    $plan = $summary230();
    $t->contains('avoids accepted next226', $plan['non_overlap']);
    $t->true(in_array('compound-avg-first-value-union-distinct-current-source-next230', $plan['replanReasons'], true));
    $t->true(in_array('recursive-ordered-limit-before-avg-window-next230', $plan['replanReasons'], true));
    $t->true(in_array('intersect-except-after-window-output-next230', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next230 rows match executor'] = static function (TestRunner $t) use ($sql230, $currentTables230, $summary230): void {
    $t->same(SQLiteSelectSql::execute($sql230, $currentTables230), $summary230()['currentRows']);
};

$tests['compound select window recursive limit current source next230 rejects missing avg'] = static function (TestRunner $t) use ($currentTables230): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext230(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 144) UNION ALL SELECT id + 1, label, score - 8 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS metric FROM q UNION SELECT option_id, option_name, first_value(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS metric FROM q) EXCEPT SELECT id, label, metric FROM (SELECT id, label, score AS metric FROM q WHERE id = 4) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables230,
        $currentTables230,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit current source next230 generated avg first value boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 130 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 112 + $case],
                ['option_id' => 3, 'option_name' => 'plugin_old_' . $case, 'autoload' => 'yes', 'score' => 94 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 4, 'option_name' => 'plugin_prime_' . $case, 'autoload' => 'yes', 'score' => 124 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (144 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 8 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes') EXCEPT SELECT id, label, metric FROM (SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS metric FROM q WHERE id = 4 UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE option_name = 'plugin_old_{$case}') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext230($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext230($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['avg', 'first_value'], $plan['windows']['functions']);
        $t->same([], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same([], $plan['recursiveQueue']['currentEmittedLabels']);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->same(['plugin_prime_' . $case], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
    };
}

return $tests;
