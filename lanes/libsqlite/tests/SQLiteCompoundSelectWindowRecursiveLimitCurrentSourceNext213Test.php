<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions213 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 124],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 96],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 74],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 42],
];
$nextOptions213 = [
    ...$currentOptions213,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 116],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 86],
];
$currentTables213 = ['wp_options' => $currentOptions213];
$nextTables213 = ['wp_options' => $nextOptions213];

$sql213 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       max(score) OVER (
           ORDER BY score DESC, id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric,
       min(score) OVER (
           ORDER BY score DESC, id
           ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
       ) AS floor_metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       max(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric,
       min(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
       ) AS floor_metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       max(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric,
       min(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
       ) AS floor_metric
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY metric DESC, id
 LIMIT 3 OFFSET 1
SQL;

$summary213 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext213($sql213, $currentTables213, $nextTables213, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next213 status dependencies'] = static function (TestRunner $t) use ($summary213): void {
    $plan = $summary213();
    $t->same('compound-select-window-recursive-limit-current-source-next213-ready', $plan['status']);
    $t->same([
        'sqlite-window-min-max-frame-values-next213',
        'sqlite-select-sql-recursive-queue-limit-next213',
        'sqlite-compound-intersect-current-source-token-fence-next213',
    ], $plan['dependencies']);
    $t->contains('min/max window frame execution', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next213 compound metadata'] = static function (TestRunner $t) use ($summary213): void {
    $compound = $summary213()['compound'];
    $t->same(['UNION ALL', 'INTERSECT'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([3, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectTail']);
};

$tests['compound select window recursive limit current source next213 current and next rows'] = static function (TestRunner $t) use ($summary213): void {
    $plan = $summary213();
    $t->same(['home', 'rewrite_rules'], array_column($plan['currentRows'], 'label'));
    $t->same(['plugin_prime', 'home', 'theme_mods_next'], array_column($plan['nextRows'], 'label'));
    $t->same([96, 74], array_column($plan['currentRows'], 'metric'));
    $t->same([116, 96, 86], array_column($plan['nextRows'], 'metric'));
};

$tests['compound select window recursive limit current source next213 recursive queue trace'] = static function (TestRunner $t) use ($summary213): void {
    $queue = $summary213()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same([8, 8], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next213 min max windows'] = static function (TestRunner $t) use ($summary213): void {
    $windows = $summary213()['windows'];
    $t->same(['max', 'min'], $windows['functions']);
    $t->same(['max', 'min', 'max', 'min', 'max', 'min'], $windows['aggregateFunctions']);
    $t->same([124, 96, 74], array_slice($windows['maxFrameMetrics'], 0, 3));
    $t->same([124, 96, 74], array_slice($windows['minFrameMetrics'], 0, 3));
    $t->same([true, true, true, true, true, true], array_column($windows['current'], 'hasFrame'));
};

$tests['compound select window recursive limit current source next213 source boundary shifts'] = static function (TestRunner $t) use ($summary213): void {
    $source = $summary213()['sourceWindow'];
    $t->same(['siteurl'], $source['currentSkippedLabels']);
    $t->same(['siteurl'], $source['nextSkippedLabels']);
    $t->same(['plugin_prime', 'theme_mods_next'], $source['nextOnlyAdmittedLabels']);
    $t->same(['rewrite_rules'], $source['currentOnlyAdmittedLabels']);
    $t->same([], $source['intersectMatchedLabels']);
};

$tests['compound select window recursive limit current source next213 token fence'] = static function (TestRunner $t) use ($summary213): void {
    $first = $summary213();
    $second = $summary213($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(4, $first['cursor']['nextOffset']);
};

$tests['compound select window recursive limit current source next213 rejects stale cursor'] = static function (TestRunner $t) use ($summary213): void {
    $cursor = $summary213()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary213($cursor));
};

$tests['compound select window recursive limit current source next213 limit trace'] = static function (TestRunner $t) use ($summary213): void {
    $trace = $summary213()['limitTrace'];
    $t->same(3, $trace['current']['preLimitCount']);
    $t->same(5, $trace['next']['preLimitCount']);
    $t->same(2, $trace['current']['finalCount']);
    $t->same(3, $trace['next']['finalCount']);
};

$tests['compound select window recursive limit current source next213 executor parity'] = static function (TestRunner $t) use ($sql213, $currentTables213, $summary213): void {
    $t->same(SQLiteSelectSql::execute($sql213, $currentTables213), $summary213()['currentRows']);
};

$tests['compound select window recursive limit current source next213 replan reasons'] = static function (TestRunner $t) use ($summary213): void {
    $plan = $summary213();
    $t->contains('avoids accepted next212', $plan['non_overlap']);
    $t->true(in_array('compound-min-max-window-current-source-next213', $plan['replanReasons'], true));
    $t->true(in_array('intersect-after-window-output-next213', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next213 rejects missing min'] = static function (TestRunner $t) use ($currentTables213): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext213(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, max(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric, max(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS floor_metric FROM q UNION ALL SELECT option_id, option_name, max(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING), max(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options INTERSECT SELECT option_id, option_name, max(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING), max(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options ORDER BY metric DESC, id LIMIT 3 OFFSET 1",
        $currentTables213,
        $currentTables213,
    ));
};

$tests['compound select window recursive limit current source next213 rejects missing intersect'] = static function (TestRunner $t) use ($currentTables213): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext213(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, max(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric, min(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS floor_metric FROM q UNION ALL SELECT option_id, option_name, max(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING), min(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) FROM wp_options ORDER BY metric DESC, id LIMIT 3 OFFSET 1",
        $currentTables213,
        $currentTables213,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound select window recursive limit current source next213 generated min max intersect boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 2 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 124 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 96 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 74 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 42 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 116 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, max(score) OVER (ORDER BY score DESC, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric, min(score) OVER (ORDER BY score DESC, id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS floor_metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, max(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric, min(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS floor_metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT option_id AS id, option_name AS label, max(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric, min(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS floor_metric FROM wp_options WHERE autoload = 'yes' ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext213($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext213($sql, $tables, $nextTables, $plan['cursor']);

        $t->same(min($finalLimit, 3), count($plan['nextRows']));
        $t->same(['max', 'min'], array_values(array_unique($plan['windows']['aggregateFunctions'])));
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true(in_array('plugin_' . $case, $plan['sourceWindow']['nextOnlyAdmittedLabels'], true));
    };
}

return $tests;
