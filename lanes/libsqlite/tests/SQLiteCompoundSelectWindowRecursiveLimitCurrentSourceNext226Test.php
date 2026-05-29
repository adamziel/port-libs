<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions226 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 128],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 3, 'option_name' => 'plugin_old', 'autoload' => 'yes', 'score' => 98],
    ['option_id' => 4, 'option_name' => 'transient_seed', 'autoload' => 'no', 'score' => 20],
];
$nextOptions226 = [
    ...$currentOptions226,
    ['option_id' => 5, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 6, 'option_name' => 'plugin_fresh', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 7, 'option_name' => 'plugin_legacy', 'autoload' => 'yes', 'score' => 92],
];
$currentTables226 = ['wp_options' => $currentOptions226];
$nextTables226 = ['wp_options' => $nextOptions226];

$sql226 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 150)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       sum(score) OVER (
           ORDER BY score DESC
           ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       count(*) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       count(*) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM wp_options
 WHERE option_name IN ('plugin_old', 'plugin_legacy')
INTERSECT
SELECT id, label, metric
  FROM (
        SELECT id,
               label,
               sum(score) OVER (
                   ORDER BY score DESC
                   ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
               ) AS metric
          FROM q
        UNION ALL
        SELECT option_id AS id,
               option_name AS label,
               count(*) OVER (
                   PARTITION BY autoload
                   ORDER BY score DESC, option_id
                   ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
               ) AS metric
          FROM wp_options
         WHERE autoload = 'yes'
  )
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summary226 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareAggregateWindowFence($sql226, $currentTables226, $nextTables226, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next226 status dependencies'] = static function (TestRunner $t) use ($summary226): void {
    $plan = $summary226();
    $t->same('compound-select-window-recursive-limit-current-source-next226-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next226',
        'sqlite-select-sql-sum-count-window-next226',
        'sqlite-compound-except-intersect-current-source-token-fence-next226',
    ], $plan['dependencies']);
    $t->contains('sum/count window dispatch', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next226 compound metadata'] = static function (TestRunner $t) use ($summary226): void {
    $compound = $summary226()['compound'];
    $t->same(['UNION ALL', 'EXCEPT', 'INTERSECT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([5, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasExceptTail']);
    $t->true($compound['hasIntersectTail']);
};

$tests['compound select window recursive limit current source next226 current rows'] = static function (TestRunner $t) use ($summary226): void {
    $rows = $summary226()['currentRows'];
    $t->same(5, count($rows));
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7', 'seed:2:3', 'seed:2'], array_column($rows, 'label'));
    $t->same([360.0, 330.0, 300.0, 270.0, 140.0], array_map(static fn (mixed $value): float => round((float) $value, 6), array_column($rows, 'metric')));
};

$tests['compound select window recursive limit current source next226 next source boundary'] = static function (TestRunner $t) use ($summary226): void {
    $plan = $summary226();
    $t->same(array_column($plan['currentRows'], 'label'), array_column($plan['nextRows'], 'label'));
    $t->same([], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(array_column($plan['currentRows'], 'label'), $plan['sourceWindow']['currentOnlyAdmittedLabels']);
    $t->same(false, $plan['sourceWindow']['currentToken'] === $plan['sourceWindow']['nextToken']);
};

$tests['compound select window recursive limit current source next226 recursive queue trace'] = static function (TestRunner $t) use ($summary226): void {
    $queue = $summary226()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same([], $queue['currentSkippedLabels']);
    $t->same([], $queue['currentEmittedLabels']);
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next226 window shape'] = static function (TestRunner $t) use ($summary226): void {
    $windows = $summary226()['windows'];
    $t->same(['sum', 'count'], $windows['functions']);
    $t->same([0, 1, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2, 2], array_column($windows['current'], 'orderCount'));
    $t->same([390.0, 360.0, 330.0], array_slice(array_map(static fn (float $value): float => round($value, 6), $windows['aggregateMetrics']), 0, 3));
    $t->true(in_array(2.0, array_map(static fn (float $value): float => round($value, 6), $windows['nextAggregateMetrics']), true));
};

$tests['compound select window recursive limit current source next226 except intersect and limit trace'] = static function (TestRunner $t) use ($summary226): void {
    $plan = $summary226();
    $t->same(['plugin_old', 'plugin_legacy'], $plan['sourceWindow']['exceptFilteredLabels']);
    $t->same(['seed:2:3:4'], $plan['sourceWindow']['currentSkippedLabels']);
    $t->same(['home', 'plugin_old', 'siteurl'], $plan['sourceWindow']['currentTruncatedLabels']);
    $t->same(9, $plan['limitTrace']['current']['preLimitCount']);
    $t->same(11, $plan['limitTrace']['next']['preLimitCount']);
};

$tests['compound select window recursive limit current source next226 token fence'] = static function (TestRunner $t) use ($summary226): void {
    $first = $summary226();
    $second = $summary226($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(6, $first['cursor']['nextOffset']);
};

$tests['compound select window recursive limit current source next226 rejects stale cursor'] = static function (TestRunner $t) use ($summary226): void {
    $cursor = $summary226()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary226($cursor));
};

$tests['compound select window recursive limit current source next226 replan reasons'] = static function (TestRunner $t) use ($summary226): void {
    $plan = $summary226();
    $t->contains('avoids accepted next219', $plan['non_overlap']);
    $t->true(in_array('compound-sum-count-intersect-current-source-next226', $plan['replanReasons'], true));
    $t->true(in_array('recursive-ordered-limit-before-aggregate-window-next226', $plan['replanReasons'], true));
    $t->true(in_array('except-intersect-after-window-output-next226', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next226 rows match executor'] = static function (TestRunner $t) use ($sql226, $currentTables226, $summary226): void {
    $t->same(SQLiteSelectSql::execute($sql226, $currentTables226), $summary226()['currentRows']);
};

$tests['compound select window recursive limit current source next226 rejects missing count'] = static function (TestRunner $t) use ($currentTables226): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareAggregateWindowFence(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 150) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM q UNION ALL SELECT option_id, option_name, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) FROM wp_options EXCEPT SELECT option_id, option_name, score FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM q) ORDER BY metric DESC, id LIMIT 5 OFFSET 1",
        $currentTables226,
        $currentTables226,
    ));
};

$tests['compound select window recursive limit current source next226 rejects missing intersect'] = static function (TestRunner $t) use ($currentTables226): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareAggregateWindowFence(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 150) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM q UNION ALL SELECT option_id, option_name, count(*) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) FROM wp_options EXCEPT SELECT option_id, option_name, score FROM wp_options ORDER BY metric DESC, id LIMIT 5 OFFSET 1",
        $currentTables226,
        $currentTables226,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit current source next226 generated sum count intersect boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 128 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 118 + $case],
                ['option_id' => 3, 'option_name' => 'plugin_old', 'autoload' => 'yes', 'score' => 98 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 4, 'option_name' => 'plugin_fresh_' . $case, 'autoload' => 'yes', 'score' => 112 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (150 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, count(*) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, count(*) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM wp_options WHERE option_name = 'plugin_old' INTERSECT SELECT id, label, metric FROM (SELECT id, label, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, count(*) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM wp_options WHERE autoload = 'yes') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareAggregateWindowFence($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareAggregateWindowFence($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['sum', 'count'], $plan['windows']['functions']);
        $t->same([], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same([], $plan['recursiveQueue']['currentEmittedLabels']);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->same([], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
    };
}

return $tests;
