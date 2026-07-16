<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions210 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 72],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptions210 = [
    ...$currentOptions210,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 84],
];
$currentTables210 = ['wp_options' => $currentOptions210];
$nextTables210 = ['wp_options' => $nextOptions210];

$sql210 = <<<'SQL'
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
       row_number() OVER (ORDER BY score DESC, id) AS metric
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
              row_number() OVER (ORDER BY score DESC, id) AS metric
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
              row_number() OVER (ORDER BY score DESC, id) AS metric
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

$summary210 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRowNumberLastValueIntersectExceptLimit($sql210, $currentTables210, $nextTables210, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next210 status dependencies'] = static function (TestRunner $t) use ($summary210): void {
    $plan = $summary210();
    $t->same('compound-select-window-recursive-limit-current-source-next210-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next210',
        'sqlite-select-sql-row-number-last-value-window-next210',
        'sqlite-compound-intersect-except-current-source-token-fence-next210',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next210 compound metadata'] = static function (TestRunner $t) use ($summary210): void {
    $compound = $summary210()['compound'];
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectMiddle']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source next210 current row boundary'] = static function (TestRunner $t) use ($summary210): void {
    $rows = $summary210()['currentRows'];
    $t->same(['home', 'rewrite_rules', 'seed:2:3:4:5:6:7:8', 'seed:2:3:4:5:6:7', 'seed:2:3:4:5:6', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same([72, 72, 7, 6, 5, 4], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next210 next row boundary'] = static function (TestRunner $t) use ($summary210): void {
    $rows = $summary210()['nextRows'];
    $t->same(['plugin_prime', 'home', 'rewrite_rules', 'theme_mods_next', 'seed:2:3:4:5:6:7:8', 'seed:2:3:4:5:6:7'], array_column($rows, 'label'));
    $t->same([95, 84, 72, 72, 7, 6], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next210 recursive queue trace'] = static function (TestRunner $t) use ($summary210): void {
    $queue = $summary210()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same('seed:2:3:4:5:6:7:8', $queue['currentEmittedLabels'][6]);
    $t->same([8, 8], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next210 window shape'] = static function (TestRunner $t) use ($summary210): void {
    $windows = $summary210()['windows'];
    $t->same(['row_number', 'last_value'], $windows['functions']);
    $t->same(['last_value'], $windows['valueFunctions']);
    $t->same([false, true], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same(7, $windows['rowNumberMetrics']['seed:2:3:4:5:6:7:8']);
    $t->same(72, $windows['lastValueMetrics']['rewrite_rules']);
};

$tests['compound select window recursive limit current source next210 token fence'] = static function (TestRunner $t) use ($summary210): void {
    $first = $summary210();
    $second = $summary210($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
    $t->same(['plugin_prime', 'theme_mods_next'], $first['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['seed:2:3:4:5:6', 'seed:2:3:4:5'], $first['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next210 rejects stale cursor'] = static function (TestRunner $t) use ($summary210): void {
    $cursor = $summary210()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary210($cursor));
};

$tests['compound select window recursive limit current source next210 limit trace'] = static function (TestRunner $t) use ($summary210): void {
    $trace = $summary210()['limitTrace'];
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4', 'seed:2:3'], array_slice(array_column($trace['current']['truncatedAfterLimit'], 'label'), 0, 2));
    $t->same(['seed:2:3:4:5:6', 'seed:2:3:4:5'], array_slice(array_column($trace['next']['truncatedAfterLimit'], 'label'), 0, 2));
};

$tests['compound select window recursive limit current source next210 replan reasons'] = static function (TestRunner $t) use ($summary210): void {
    $plan = $summary210();
    $t->contains('avoids accepted next209', $plan['non_overlap']);
    $t->true(in_array('compound-row-number-last-value-current-source-next210', $plan['replanReasons'], true));
    $t->true(in_array('recursive-ordered-limit-before-value-windows-next210', $plan['replanReasons'], true));
    $t->true(in_array('intersect-before-except-window-output-next210', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next210 rejects missing last value'] = static function (TestRunner $t) use ($currentTables210): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRowNumberLastValueIntersectExceptLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 132) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q) EXCEPT SELECT id, label, metric FROM (SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q WHERE id = 3) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables210,
        $currentTables210,
    ));
};

$tests['compound select window recursive limit current source next210 rejects unordered recursive limit'] = static function (TestRunner $t) use ($currentTables210): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRowNumberLastValueIntersectExceptLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 132) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 8 LIMIT 7 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, last_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q) EXCEPT SELECT id, label, metric FROM (SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q WHERE id = 3) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables210,
        $currentTables210,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source next210 generated intersect except value boundary ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (132 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 9 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, last_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, last_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes') EXCEPT SELECT id, label, metric FROM (SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q WHERE id = 3 UNION ALL SELECT option_id AS id, option_name AS label, last_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE option_name = 'home_{$case}') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRowNumberLastValueIntersectExceptLimit($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRowNumberLastValueIntersectExceptLimit($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['row_number', 'last_value'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true(isset($plan['windows']['rowNumberMetrics']['seed_' . $case . ':2:3:4:5']));
    };
}

return $tests;
