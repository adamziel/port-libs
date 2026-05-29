<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions217 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 128],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 96],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 55],
];
$nextOptions217 = [
    ...$currentOptions217,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 94],
];
$currentTables217 = ['wp_options' => $currentOptions217];
$nextTables217 = ['wp_options' => $nextOptions217];

$sql217 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 146)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       rank() OVER (
           ORDER BY score DESC
       ) AS win_rank
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (
           PARTITION BY autoload
           ORDER BY score DESC
       ) AS win_rank
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id,
       label,
       win_rank
  FROM (
        SELECT id,
               label,
               rank() OVER (ORDER BY score DESC) AS win_rank
          FROM q
        UNION ALL
        SELECT option_id AS id,
               option_name AS label,
               dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS win_rank
          FROM wp_options
         WHERE autoload = 'yes'
       )
 WHERE win_rank <= 6
 ORDER BY win_rank DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summary217 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersectLimit($sql217, $currentTables217, $nextTables217, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next217 status dependencies'] = static function (TestRunner $t) use ($summary217): void {
    $plan = $summary217();
    $t->same('compound-select-window-recursive-limit-current-source-next217-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next217',
        'sqlite-select-sql-rank-dense-rank-window-next217',
        'sqlite-compound-intersect-current-source-token-fence-next217',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next217 compound metadata'] = static function (TestRunner $t) use ($summary217): void {
    $compound = $summary217()['compound'];
    $t->same(['UNION ALL', 'INTERSECT'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['win_rank', 'id'], $compound['orderColumns']);
    $t->same([5, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectTail']);
};

$tests['compound select window recursive limit current source next217 current rows'] = static function (TestRunner $t) use ($summary217): void {
    $rows = $summary217()['currentRows'];
    $t->same(5, count($rows));
    $t->same(['seed:2:3:4:5:6', 'seed:2:3:4:5', 'rewrite_rules', 'seed:2:3:4', 'home'], array_column($rows, 'label'));
    $t->same([5, 4, 3, 3, 2], array_map('intval', array_column($rows, 'win_rank')));
};

$tests['compound select window recursive limit current source next217 next source boundary'] = static function (TestRunner $t) use ($summary217): void {
    $plan = $summary217();
    $t->same(['seed:2:3:4:5:6', 'seed:2:3:4:5', 'theme_mods_next', 'rewrite_rules', 'seed:2:3:4'], array_column($plan['nextRows'], 'label'));
    $t->same(['theme_mods_next'], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['home'], $plan['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next217 recursive queue trace'] = static function (TestRunner $t) use ($summary217): void {
    $queue = $summary217()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same([8, 8], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next217 window shape'] = static function (TestRunner $t) use ($summary217): void {
    $windows = $summary217()['windows'];
    $t->same(['rank', 'dense_rank'], $windows['functions']);
    $t->same([false, false], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([6, 5, 4, 3, 3, 2], array_slice(array_map('intval', $windows['rankMetrics']), 0, 6));
    $t->true(in_array('seed:2:3:4', $windows['textMetrics'], true));
};

$tests['compound select window recursive limit current source next217 token fence'] = static function (TestRunner $t) use ($summary217): void {
    $first = $summary217();
    $second = $summary217($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(6, $first['cursor']['nextOffset']);
};

$tests['compound select window recursive limit current source next217 rejects stale cursor'] = static function (TestRunner $t) use ($summary217): void {
    $cursor = $summary217()['cursor'];
    $cursor['currentToken'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary217($cursor));
};

$tests['compound select window recursive limit current source next217 limit trace'] = static function (TestRunner $t) use ($summary217): void {
    $trace = $summary217()['limitTrace'];
    $t->same(1, count($trace['current']['skippedBeforeOffset']));
    $t->same(9, $trace['current']['preLimitCount']);
    $t->same(11, $trace['next']['preLimitCount']);
    $t->same(5, $trace['current']['finalCount']);
};

$tests['compound select window recursive limit current source next217 replan reasons'] = static function (TestRunner $t) use ($summary217): void {
    $plan = $summary217();
    $t->contains('avoids accepted group_concat/row_number EXCEPT fencing', $plan['non_overlap']);
    $t->true(in_array('compound-rank-dense-rank-current-source-next217', $plan['replanReasons'], true));
    $t->true(in_array('recursive-queue-exhausted-before-intersect-next217', $plan['replanReasons'], true));
    $t->true(in_array('intersect-window-membership-before-final-limit-next217', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next217 base rows match executor'] = static function (TestRunner $t) use ($sql217, $currentTables217, $summary217): void {
    $t->same(SQLiteSelectSql::execute($sql217, $currentTables217), $summary217()['currentRows']);
};

$tests['compound select window recursive limit current source next217 rejects missing dense rank'] = static function (TestRunner $t) use ($currentTables217): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersectLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 146) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS win_rank FROM q UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options INTERSECT SELECT id, label, win_rank FROM (SELECT id, label, rank() OVER (ORDER BY score DESC) AS win_rank FROM q) ORDER BY win_rank DESC, id LIMIT 5 OFFSET 1",
        $currentTables217,
        $currentTables217,
    ));
};

$tests['compound select window recursive limit current source next217 rejects missing intersect'] = static function (TestRunner $t) use ($currentTables217): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersectLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 146) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS win_rank FROM q UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY score DESC) FROM wp_options ORDER BY win_rank DESC, id LIMIT 5 OFFSET 1",
        $currentTables217,
        $currentTables217,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit current source next217 generated rank boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 128 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 118 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 96 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 55 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 118 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (146 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 9 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS win_rank FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS win_rank FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, win_rank FROM (SELECT id, label, rank() OVER (ORDER BY score DESC) AS win_rank FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS win_rank FROM wp_options WHERE autoload = 'yes') WHERE win_rank <= 6 ORDER BY win_rank DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersectLimit($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersectLimit($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['rank', 'dense_rank'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true(in_array('seed_' . $case . ':2:3', $plan['windows']['textMetrics'], true));
    };
}

return $tests;
