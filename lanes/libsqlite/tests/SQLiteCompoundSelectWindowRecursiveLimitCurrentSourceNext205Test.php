<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions205 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptions205 = [
    ...$currentOptions205,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 6, 'option_name' => 'transient_next', 'autoload' => 'no', 'score' => 70],
];
$currentTables205 = ['wp_options' => $currentOptions205];
$nextTables205 = ['wp_options' => $nextOptions205];

$sql205 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       rank() OVER (ORDER BY score DESC) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (
           PARTITION BY autoload
           ORDER BY score DESC
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
               rank() OVER (ORDER BY score DESC) AS metric
          FROM q
        UNION ALL
        SELECT option_id AS id,
               option_name AS label,
               dense_rank() OVER (
                   PARTITION BY autoload
                   ORDER BY score DESC
               ) AS metric
          FROM wp_options
         WHERE autoload = 'yes'
       )
 ORDER BY metric ASC, id
 LIMIT 6 OFFSET 1
SQL;

$summary205 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersect($sql205, $currentTables205, $nextTables205, $cursor);
$tests = [];

$tests['compound select window recursive limit current source rankDenseRankIntersect status dependencies'] = static function (TestRunner $t) use ($summary205): void {
    $plan = $summary205();
    $t->same('compound-select-window-recursive-limit-current-source-rankDenseRankIntersect-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-rankDenseRankIntersect',
        'sqlite-select-sql-rank-dense-rank-window-rankDenseRankIntersect',
        'sqlite-current-source-token-fence-rankDenseRankIntersect',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source rankDenseRankIntersect compound metadata'] = static function (TestRunner $t) use ($summary205): void {
    $compound = $summary205()['compound'];
    $t->same(['UNION ALL', 'INTERSECT'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectTail']);
};

$tests['compound select window recursive limit current source rankDenseRankIntersect current row boundary'] = static function (TestRunner $t) use ($summary205): void {
    $rows = $summary205()['currentRows'];
    $t->same(['seed:2', 'home', 'seed:2:3', 'theme_mods', 'seed:2:3:4', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same([1, 1, 2, 2, 3, 4], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source rankDenseRankIntersect next row boundary'] = static function (TestRunner $t) use ($summary205): void {
    $rows = $summary205()['nextRows'];
    $t->same(['seed:2', 'home', 'plugin_prime', 'seed:2:3', 'theme_mods', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([1, 1, 1, 2, 2, 3], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source rankDenseRankIntersect recursive queue trace'] = static function (TestRunner $t) use ($summary205): void {
    $queue = $summary205()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $queue['currentEmittedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentQueueFronts'], 0, 3));
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source rankDenseRankIntersect window shape'] = static function (TestRunner $t) use ($summary205): void {
    $windows = $summary205()['windows'];
    $t->same(['rank', 'dense_rank'], $windows['functions']);
    $t->same(['rank', 'dense_rank'], $windows['rankingFunctions']);
    $t->same([1 => ['siteurl', 'seed:2', 'home'], 2 => ['seed:2:3', 'theme_mods']], array_intersect_key($windows['currentPeerMetrics'], [1 => true, 2 => true]));
    $t->same([1 => ['siteurl', 'seed:2', 'home', 'plugin_prime'], 2 => ['seed:2:3', 'theme_mods']], array_intersect_key($windows['nextPeerMetrics'], [1 => true, 2 => true]));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 1], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit current source rankDenseRankIntersect token fence'] = static function (TestRunner $t) use ($summary205): void {
    $first = $summary205();
    $second = $summary205($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
    $t->same(['plugin_prime'], $first['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['seed:2:3:4:5'], $first['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source rankDenseRankIntersect rejects stale cursor'] = static function (TestRunner $t) use ($summary205): void {
    $cursor = $summary205()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary205($cursor));
};

$tests['compound select window recursive limit current source rankDenseRankIntersect limit trace'] = static function (TestRunner $t) use ($summary205): void {
    $trace = $summary205()['limitTrace'];
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], array_slice(array_column($trace['current']['truncatedAfterLimit'], 'label'), 0, 2));
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6'], array_slice(array_column($trace['next']['truncatedAfterLimit'], 'label'), 0, 2));
};

$tests['compound select window recursive limit current source rankDenseRankIntersect replan reasons'] = static function (TestRunner $t) use ($summary205): void {
    $plan = $summary205();
    $t->contains('avoids accepted lagLastValueExcept', $plan['non_overlap']);
    $t->true(in_array('compound-rank-dense-rank-current-source-rankDenseRankIntersect', $plan['replanReasons'], true));
    $t->true(in_array('recursive-order-limit-offset-before-ranking-windows-rankDenseRankIntersect', $plan['replanReasons'], true));
    $t->true(in_array('intersect-after-window-output-rankDenseRankIntersect', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source rankDenseRankIntersect rejects missing dense rank'] = static function (TestRunner $t) use ($currentTables205): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersect(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q) ORDER BY metric ASC, id LIMIT 6 OFFSET 1",
        $currentTables205,
        $currentTables205,
    ));
};

$tests['compound select window recursive limit current source rankDenseRankIntersect rejects unordered recursive limit'] = static function (TestRunner $t) use ($currentTables205): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersect(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY score DESC) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q) ORDER BY metric ASC, id LIMIT 6 OFFSET 1",
        $currentTables205,
        $currentTables205,
    ));
};

foreach (range(1, 48) as $case) {
    $tests['compound select window recursive limit current source rankDenseRankIntersect generated rank intersect boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 120 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 120 + $case],
                ['option_id' => 3, 'option_name' => 'theme_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 40 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 120 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS metric FROM wp_options WHERE autoload = 'yes') ORDER BY metric ASC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersect($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersect($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['rank', 'dense_rank'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->same(['plugin_' . $case], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
    };
}

return $tests;
