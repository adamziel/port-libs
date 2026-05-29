<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions208 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
];
$nextOptions208 = [
    ...$currentOptions208,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 84],
];
$currentTables208 = ['wp_options' => $currentOptions208];
$nextTables208 = ['wp_options' => $nextOptions208];

$sql208 = <<<'SQL'
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
       rank() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT id,
       label,
       metric
  FROM (
       SELECT id,
              label,
              rank() OVER (ORDER BY score DESC, id) AS metric
         FROM q
        WHERE id = 2
       UNION ALL
       SELECT option_id AS id,
              option_name AS label,
              dense_rank() OVER (
                  PARTITION BY autoload
                  ORDER BY score DESC, option_id
              ) AS metric
         FROM wp_options
        WHERE option_name = 'rewrite_rules'
  )
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$summary208 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankExceptLimit($sql208, $currentTables208, $nextTables208, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next208 status dependencies'] = static function (TestRunner $t) use ($summary208): void {
    $plan = $summary208();
    $t->same('compound-select-window-recursive-limit-current-source-next208-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next208',
        'sqlite-select-sql-rank-dense-rank-window-next208',
        'sqlite-compound-except-current-source-token-fence-next208',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next208 compound metadata'] = static function (TestRunner $t) use ($summary208): void {
    $compound = $summary208()['compound'];
    $t->same(['UNION ALL', 'EXCEPT'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source next208 current row boundary'] = static function (TestRunner $t) use ($summary208): void {
    $rows = $summary208()['currentRows'];
    $t->same(['seed:2:3:4:5:6:7:8', 'seed:2:3:4:5:6:7', 'seed:2:3:4:5:6', 'seed:2:3:4:5', 'rewrite_rules', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([7, 6, 5, 4, 3, 3], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next208 next row boundary'] = static function (TestRunner $t) use ($summary208): void {
    $rows = $summary208()['nextRows'];
    $t->same(['seed:2:3:4:5:6:7:8', 'seed:2:3:4:5:6:7', 'rewrite_rules', 'seed:2:3:4:5:6', 'seed:2:3:4:5', 'theme_mods_next'], array_column($rows, 'label'));
    $t->same([7, 6, 5, 5, 4, 4], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next208 recursive queue trace'] = static function (TestRunner $t) use ($summary208): void {
    $queue = $summary208()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same('seed:2:3:4:5:6:7:8:9', $queue['currentEmittedLabels'][7]);
    $t->same([9, 9], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next208 window shape'] = static function (TestRunner $t) use ($summary208): void {
    $windows = $summary208()['windows'];
    $t->same(['rank', 'dense_rank'], $windows['functions']);
    $t->same(['rank', 'dense_rank'], $windows['rankingFunctions']);
    $t->same([false, false], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same(7, $windows['rankMetrics']['seed:2:3:4:5:6:7:8']);
    $t->same(3, $windows['denseRankMetrics']['rewrite_rules']);
};

$tests['compound select window recursive limit current source next208 token fence'] = static function (TestRunner $t) use ($summary208): void {
    $first = $summary208();
    $second = $summary208($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
    $t->same(['theme_mods_next'], $first['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['seed:2:3:4'], $first['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next208 rejects stale cursor'] = static function (TestRunner $t) use ($summary208): void {
    $cursor = $summary208()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary208($cursor));
};

$tests['compound select window recursive limit current source next208 limit trace'] = static function (TestRunner $t) use ($summary208): void {
    $trace = $summary208()['limitTrace'];
    $t->same(['seed:2:3:4:5:6:7:8:9'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6:7:8:9'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['home', 'seed:2:3'], array_slice(array_column($trace['current']['truncatedAfterLimit'], 'label'), 0, 2));
    $t->same(['home', 'seed:2:3:4'], array_slice(array_column($trace['next']['truncatedAfterLimit'], 'label'), 0, 2));
};

$tests['compound select window recursive limit current source next208 replan reasons'] = static function (TestRunner $t) use ($summary208): void {
    $plan = $summary208();
    $t->contains('avoids accepted next206', $plan['non_overlap']);
    $t->true(in_array('compound-rank-dense-rank-current-source-next208', $plan['replanReasons'], true));
    $t->true(in_array('recursive-ordered-limit-before-ranking-windows-next208', $plan['replanReasons'], true));
    $t->true(in_array('except-after-ranking-window-output-next208', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next208 rejects missing dense rank'] = static function (TestRunner $t) use ($currentTables208): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankExceptLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 8 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options EXCEPT SELECT id, label, metric FROM (SELECT id, label, rank() OVER (ORDER BY score DESC, id) AS metric FROM q) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables208,
        $currentTables208,
    ));
};

$tests['compound select window recursive limit current source next208 rejects unordered recursive limit'] = static function (TestRunner $t) use ($currentTables208): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankExceptLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 9 LIMIT 8 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) FROM wp_options EXCEPT SELECT id, label, metric FROM (SELECT id, label, rank() OVER (ORDER BY score DESC, id) AS metric FROM q) ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables208,
        $currentTables208,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source next208 generated ranking except boundary ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 8 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT id, label, metric FROM (SELECT id, label, rank() OVER (ORDER BY score DESC, id) AS metric FROM q WHERE id = 2 UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE option_name = 'rewrite_{$case}') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankExceptLimit($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankExceptLimit($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['rank', 'dense_rank'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true(isset($plan['windows']['rankMetrics']['seed_' . $case . ':2:3:4:5']));
    };
}

return $tests;
