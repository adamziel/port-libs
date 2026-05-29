<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions209 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 72],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptions209 = [
    ...$currentOptions209,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 84],
];
$currentTables209 = ['wp_options' => $currentOptions209];
$nextTables209 = ['wp_options' => $nextOptions209];

$sql209 = <<<'SQL'
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
       sum(score) OVER (
           ORDER BY score DESC, id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
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
       1 AS metric
  FROM wp_options
 WHERE option_name = 'home'
UNION
SELECT id,
       label,
       score AS metric
  FROM q
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$summary209 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSumCountExceptUnionLimit($sql209, $currentTables209, $nextTables209, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next209 status dependencies'] = static function (TestRunner $t) use ($summary209): void {
    $plan = $summary209();
    $t->same('compound-select-window-recursive-limit-current-source-next209-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next209',
        'sqlite-select-sql-sum-count-window-next209',
        'sqlite-compound-except-union-current-source-token-fence-next209',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next209 compound metadata'] = static function (TestRunner $t) use ($summary209): void {
    $compound = $summary209()['compound'];
    $t->same(['UNION ALL', 'EXCEPT', 'UNION'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasExceptMiddle']);
    $t->true($compound['hasUnionDistinctTail']);
};

$tests['compound select window recursive limit current source next209 current rows'] = static function (TestRunner $t) use ($summary209): void {
    $rows = $summary209()['currentRows'];
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7', 'seed:2'], array_column($rows, 'label'));
    $t->same([219, 201, 183, 165, 147, 123], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next209 next rows'] = static function (TestRunner $t) use ($summary209): void {
    $rows = $summary209()['nextRows'];
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7', 'seed:2'], array_column($rows, 'label'));
    $t->same([219, 201, 183, 165, 147, 123], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next209 prelimit wp boundary shifts'] = static function (TestRunner $t) use ($summary209): void {
    $plan = $summary209();
    $t->same(['home', 'rewrite_rules', 'plugin_prime', 'theme_mods_next', 'siteurl'], array_values(array_filter(array_column($plan['nextPreLimitRows'], 'label'), static fn (string $label): bool => !str_starts_with($label, 'seed'))));
    $t->same(['home', 'rewrite_rules', 'siteurl'], array_values(array_filter(array_column($plan['currentPreLimitRows'], 'label'), static fn (string $label): bool => !str_starts_with($label, 'seed'))));
    $t->same([], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next209 recursive queue trace'] = static function (TestRunner $t) use ($summary209): void {
    $queue = $summary209()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same('seed:2:3:4:5:6:7:8', $queue['currentEmittedLabels'][6]);
    $t->same([8, 8], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next209 aggregate window shape'] = static function (TestRunner $t) use ($summary209): void {
    $windows = $summary209()['windows'];
    $t->same(['sum', 'count'], $windows['functions']);
    $t->same(['sum', 'count'], $windows['aggregateFunctions']);
    $t->same([237, 219, 201], array_slice($windows['sumFrameMetrics'], 0, 3));
    $t->same([2, 2, 1], $windows['countFrameMetrics']);
    $t->same([true, true], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
};

$tests['compound select window recursive limit current source next209 token fence'] = static function (TestRunner $t) use ($summary209): void {
    $first = $summary209();
    $second = $summary209($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
};

$tests['compound select window recursive limit current source next209 rejects stale cursor'] = static function (TestRunner $t) use ($summary209): void {
    $cursor = $summary209()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary209($cursor));
};

$tests['compound select window recursive limit current source next209 limit trace'] = static function (TestRunner $t) use ($summary209): void {
    $trace = $summary209()['limitTrace'];
    $t->same(['seed:2'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(16, $trace['current']['preLimitCount']);
    $t->same(18, $trace['next']['preLimitCount']);
    $t->same(6, $trace['current']['finalCount']);
    $t->same(['seed:2:3', 'seed:2:3:4'], array_slice(array_column($trace['current']['truncatedAfterLimit'], 'label'), 0, 2));
    $t->same(['seed:2:3', 'seed:2:3:4'], array_slice(array_column($trace['next']['truncatedAfterLimit'], 'label'), 0, 2));
};

$tests['compound select window recursive limit current source next209 replan reasons'] = static function (TestRunner $t) use ($summary209): void {
    $plan = $summary209();
    $t->contains('avoids accepted lead-nth-value-intersect-limit', $plan['non_overlap']);
    $t->true(in_array('compound-sum-count-window-current-source-next209', $plan['replanReasons'], true));
    $t->true(in_array('recursive-ordered-limit-before-aggregate-windows-next209', $plan['replanReasons'], true));
    $t->true(in_array('except-and-union-after-window-output-next209', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next209 rejects missing count'] = static function (TestRunner $t) use ($currentTables209): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSumCountExceptUnionLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 132) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options EXCEPT SELECT option_id, option_name, 1 FROM wp_options UNION SELECT id, label, score FROM q ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables209,
        $currentTables209,
    ));
};

$tests['compound select window recursive limit current source next209 rejects missing except'] = static function (TestRunner $t) use ($currentTables209): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSumCountExceptUnionLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 132) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, sum(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM q UNION ALL SELECT option_id, option_name, count(*) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) FROM wp_options UNION SELECT id, label, score FROM q ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables209,
        $currentTables209,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source next209 generated aggregate frame boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 120 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 94 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 38 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 112 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (132 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 9 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, sum(score) OVER (ORDER BY score DESC, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, count(*) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, 1 AS metric FROM wp_options WHERE option_name = 'home_{$case}' UNION SELECT id, label, score AS metric FROM q ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSumCountExceptUnionLimit($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSumCountExceptUnionLimit($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['sum', 'count'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true($plan['windows']['sumFrameMetrics'][0] > $plan['windows']['sumFrameMetrics'][1]);
    };
}

return $tests;
