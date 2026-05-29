<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions219 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 128],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 3, 'option_name' => 'plugin_old', 'autoload' => 'yes', 'score' => 98],
    ['option_id' => 4, 'option_name' => 'transient_seed', 'autoload' => 'no', 'score' => 20],
];
$nextOptions219 = [
    ...$currentOptions219,
    ['option_id' => 5, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 6, 'option_name' => 'plugin_fresh', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 7, 'option_name' => 'plugin_legacy', 'autoload' => 'yes', 'score' => 92],
];
$currentTables219 = ['wp_options' => $currentOptions219];
$nextTables219 = ['wp_options' => $nextOptions219];

$sql219 = <<<'SQL'
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
       percent_rank() OVER (
           ORDER BY score DESC
       ) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       cume_dist() OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       cume_dist() OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
       ) AS metric
  FROM wp_options
 WHERE option_name IN ('plugin_old', 'plugin_legacy')
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summary219 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::comparePercentRankCumeDistExceptLimit($sql219, $currentTables219, $nextTables219, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next219 status dependencies'] = static function (TestRunner $t) use ($summary219): void {
    $plan = $summary219();
    $t->same('compound-select-window-recursive-limit-current-source-next219-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next219',
        'sqlite-select-sql-percent-rank-cume-dist-window-next219',
        'sqlite-compound-except-current-source-token-fence-next219',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next219 compound metadata'] = static function (TestRunner $t) use ($summary219): void {
    $compound = $summary219()['compound'];
    $t->same(['UNION ALL', 'EXCEPT'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([5, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source next219 current rows'] = static function (TestRunner $t) use ($summary219): void {
    $rows = $summary219()['currentRows'];
    $t->same(5, count($rows));
    $t->same(['seed:2:3:4:5:6', 'home', 'seed:2:3:4:5', 'seed:2:3:4', 'siteurl'], array_column($rows, 'label'));
    $t->same([0.8, 0.666667, 0.6, 0.4, 0.333333], array_map(static fn (mixed $value): float => round((float) $value, 6), array_column($rows, 'metric')));
};

$tests['compound select window recursive limit current source next219 next source boundary'] = static function (TestRunner $t) use ($summary219): void {
    $plan = $summary219();
    $t->same(['plugin_old', 'seed:2:3:4:5:6', 'plugin_fresh', 'seed:2:3:4:5', 'theme_mods_next'], array_column($plan['nextRows'], 'label'));
    $t->same(['plugin_old', 'plugin_fresh', 'theme_mods_next'], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['seed:2:3:4:5:6', 'seed:2:3:4:5'], $plan['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next219 recursive queue trace'] = static function (TestRunner $t) use ($summary219): void {
    $queue = $summary219()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same([], $queue['currentSkippedLabels']);
    $t->same([], $queue['currentEmittedLabels']);
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next219 window shape'] = static function (TestRunner $t) use ($summary219): void {
    $windows = $summary219()['windows'];
    $t->same(['percent_rank', 'cume_dist'], $windows['functions']);
    $t->same([0, 1, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2, 2], array_column($windows['current'], 'orderCount'));
    $t->same([1.0, 0.8, 0.666667], array_slice(array_map(static fn (float $value): float => round($value, 6), $windows['percentMetrics']), 0, 3));
    $t->true(in_array(0.5, array_map(static fn (float $value): float => round($value, 6), $windows['cumeMetrics']), true));
};

$tests['compound select window recursive limit current source next219 except and limit trace'] = static function (TestRunner $t) use ($summary219): void {
    $plan = $summary219();
    $t->same(['plugin_old', 'plugin_legacy'], $plan['sourceWindow']['exceptFilteredLabels']);
    $t->same(['seed:2:3:4:5:6:7'], $plan['sourceWindow']['currentSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2'], $plan['sourceWindow']['currentTruncatedLabels']);
    $t->same(8, $plan['limitTrace']['current']['preLimitCount']);
    $t->same(11, $plan['limitTrace']['next']['preLimitCount']);
};

$tests['compound select window recursive limit current source next219 token fence'] = static function (TestRunner $t) use ($summary219): void {
    $first = $summary219();
    $second = $summary219($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(6, $first['cursor']['nextOffset']);
};

$tests['compound select window recursive limit current source next219 rejects stale cursor'] = static function (TestRunner $t) use ($summary219): void {
    $cursor = $summary219()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary219($cursor));
};

$tests['compound select window recursive limit current source next219 replan reasons'] = static function (TestRunner $t) use ($summary219): void {
    $plan = $summary219();
    $t->contains('avoids accepted next217', $plan['non_overlap']);
    $t->true(in_array('compound-percent-rank-cume-dist-current-source-next219', $plan['replanReasons'], true));
    $t->true(in_array('recursive-ordered-limit-before-percent-window-next219', $plan['replanReasons'], true));
    $t->true(in_array('except-after-window-output-next219', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next219 rows match executor'] = static function (TestRunner $t) use ($sql219, $currentTables219, $summary219): void {
    $t->same(SQLiteSelectSql::execute($sql219, $currentTables219), $summary219()['currentRows']);
};

$tests['compound select window recursive limit current source next219 rejects missing cume dist'] = static function (TestRunner $t) use ($currentTables219): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::comparePercentRankCumeDistExceptLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 150) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, percent_rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, percent_rank() OVER (ORDER BY score DESC) FROM wp_options EXCEPT SELECT option_id, option_name, score FROM wp_options ORDER BY metric DESC, id LIMIT 5 OFFSET 1",
        $currentTables219,
        $currentTables219,
    ));
};

$tests['compound select window recursive limit current source next219 rejects missing except'] = static function (TestRunner $t) use ($currentTables219): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::comparePercentRankCumeDistExceptLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 150) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, percent_rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, cume_dist() OVER (ORDER BY score DESC) FROM wp_options ORDER BY metric DESC, id LIMIT 5 OFFSET 1",
        $currentTables219,
        $currentTables219,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit current source next219 generated percent cume boundary ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (150 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, percent_rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, cume_dist() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, cume_dist() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE option_name = 'plugin_old' ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::comparePercentRankCumeDistExceptLimit($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::comparePercentRankCumeDistExceptLimit($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['percent_rank', 'cume_dist'], $plan['windows']['functions']);
        $t->same([], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same([], $plan['recursiveQueue']['currentEmittedLabels']);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true(in_array('plugin_fresh_' . $case, $plan['sourceWindow']['nextOnlyAdmittedLabels'], true));
    };
}

return $tests;
