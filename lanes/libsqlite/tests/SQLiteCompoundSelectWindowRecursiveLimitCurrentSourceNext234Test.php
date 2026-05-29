<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions234 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 130],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 3, 'option_name' => 'plugin_old', 'autoload' => 'yes', 'score' => 94],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptions234 = [
    ...$currentOptions234,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 124],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 105],
];
$currentTables234 = ['wp_options' => $currentOptions234];
$nextTables234 = ['wp_options' => $nextOptions234];

$sql234 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 144)
    UNION ALL
    SELECT option_id, option_name, score
      FROM wp_options
     WHERE autoload = 'yes'
       AND score >= 100
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (
           ORDER BY score DESC
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
              row_number() OVER (
                  ORDER BY score DESC
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
              row_number() OVER (
                  ORDER BY score DESC
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

$summary234 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext234($sql234, $currentTables234, $nextTables234, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next234 status dependencies'] = static function (TestRunner $t) use ($summary234): void {
    $plan = $summary234();
    $t->same('compound-select-window-recursive-limit-current-source-next234-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-multi-anchor-queue-limit-next234',
        'sqlite-select-sql-row-number-first-value-window-next234',
        'sqlite-compound-union-intersect-except-current-source-token-fence-next234',
    ], $plan['dependencies']);
    $t->contains('compound-anchor queue', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next234 compound metadata'] = static function (TestRunner $t) use ($summary234): void {
    $compound = $summary234()['compound'];
    $t->same(['UNION', 'INTERSECT', 'EXCEPT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionDistinctHead']);
    $t->true($compound['hasIntersectMiddle']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source next234 anchor window rows'] = static function (TestRunner $t) use ($summary234): void {
    $rows = $summary234()['currentRows'];
    $t->same(6, count($rows));
    $t->same(['home', 'home', 'siteurl:2:3', 'seed:2:3:4', 'siteurl:2', 'seed:2:3'], array_column($rows, 'label'));
    $t->same([112.0, 7.0, 6.0, 5.0, 4.0, 3.0], array_map(static fn (mixed $value): float => round((float) $value, 6), array_column($rows, 'metric')));
};

$tests['compound select window recursive limit current source next234 next boundary'] = static function (TestRunner $t) use ($summary234): void {
    $plan = $summary234();
    $t->same(['plugin_prime', 'theme_mods_next', 'plugin_prime:6', 'seed:2:3:4', 'siteurl:2'], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['home'], $plan['sourceWindow']['currentOnlyAdmittedLabels']);
    $t->same(false, $plan['sourceWindow']['currentToken'] === $plan['sourceWindow']['nextToken']);
};

$tests['compound select window recursive limit current source next234 recursive queue trace'] = static function (TestRunner $t) use ($summary234): void {
    $queue = $summary234()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'siteurl', 'seed:2:3'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same([8, 8], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next234 window shape'] = static function (TestRunner $t) use ($summary234): void {
    $windows = $summary234()['windows'];
    $t->same(['row_number', 'first_value'], $windows['functions']);
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2], array_column($windows['current'], 'orderCount'));
    $t->true(in_array(130.0, array_map(static fn (float $value): float => round($value, 6), $windows['aggregateMetrics']), true));
    $t->true(in_array(124.0, array_map(static fn (float $value): float => round($value, 6), $windows['nextAggregateMetrics']), true));
};

$tests['compound select window recursive limit current source next234 token fence'] = static function (TestRunner $t) use ($summary234): void {
    $first = $summary234();
    $second = $summary234($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(7, $first['cursor']['nextOffset']);
};

$tests['compound select window recursive limit current source next234 rejects stale cursor'] = static function (TestRunner $t) use ($summary234): void {
    $cursor = $summary234()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary234($cursor));
};

$tests['compound select window recursive limit current source next234 limit trace'] = static function (TestRunner $t) use ($summary234): void {
    $trace = $summary234()['limitTrace'];
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(9, $trace['current']['preLimitCount']);
    $t->same(11, $trace['next']['preLimitCount']);
};

$tests['compound select window recursive limit current source next234 replan reasons'] = static function (TestRunner $t) use ($summary234): void {
    $plan = $summary234();
    $t->contains('avoids accepted next230', $plan['non_overlap']);
    $t->true(in_array('compound-multi-anchor-recursive-row-number-first-value-next234', $plan['replanReasons'], true));
    $t->true(in_array('recursive-anchor-wp-options-limit-before-window-next234', $plan['replanReasons'], true));
    $t->true(in_array('intersect-except-after-anchor-shift-window-output-next234', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next234 rows match executor'] = static function (TestRunner $t) use ($sql234, $currentTables234, $summary234): void {
    $t->same(SQLiteSelectSql::execute($sql234, $currentTables234), $summary234()['currentRows']);
};

$tests['compound select window recursive limit current source next234 rejects missing anchor'] = static function (TestRunner $t) use ($currentTables234): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext234(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 144) UNION ALL SELECT id + 1, label, score - 8 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q UNION SELECT option_id, option_name, first_value(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q) EXCEPT SELECT id, label, metric FROM (SELECT id, label, score AS metric FROM q WHERE id = 4) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables234,
        $currentTables234,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit current source next234 generated avg first value boundary ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (144 + $case) . ") UNION ALL SELECT option_id, option_name, score FROM wp_options WHERE autoload = 'yes' AND score >= " . (100 + $case) . " UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 8 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes') EXCEPT SELECT id, label, metric FROM (SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q WHERE id = 4 UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE option_name = 'plugin_old_{$case}') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext234($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext234($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['row_number', 'first_value'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->true(count($plan['recursiveQueue']['currentEmittedLabels']) > 0);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true($plan['sourceWindow']['currentToken'] !== $plan['sourceWindow']['nextToken']);
    };
}

return $tests;
