<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions203 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
];
$nextOptions203 = [
    ...$currentOptions203,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 84],
];
$currentTables203 = ['wp_options' => $currentOptions203];
$nextTables203 = ['wp_options' => $nextOptions203];

$sql203 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       lag(score, 1, -1) OVER (ORDER BY score DESC, id) AS metric
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
EXCEPT
SELECT id,
       label,
       score AS metric
  FROM q
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$summary203 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext203($sql203, $currentTables203, $nextTables203, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next203 status dependencies'] = static function (TestRunner $t) use ($summary203): void {
    $plan = $summary203();
    $t->same('compound-select-window-recursive-limit-current-source-next203-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next203',
        'sqlite-select-sql-lag-last-value-window-next203',
        'sqlite-current-source-token-fence-next203',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next203 compound metadata'] = static function (TestRunner $t) use ($summary203): void {
    $compound = $summary203()['compound'];
    $t->same(['UNION ALL', 'EXCEPT'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source next203 current row boundary'] = static function (TestRunner $t) use ($summary203): void {
    $rows = $summary203()['currentRows'];
    $t->same(['seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'siteurl', 'seed:2:3:4:5:6:7', 'seed:2:3:4:5:6:7:8'], array_column($rows, 'label'));
    $t->same([122, 113, 104, 95, 95, 86], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next203 next row boundary'] = static function (TestRunner $t) use ($summary203): void {
    $rows = $summary203()['nextRows'];
    $t->same(['seed:2:3:4', 'seed:2:3:4:5', 'siteurl', 'seed:2:3:4:5:6', 'plugin_prime', 'seed:2:3:4:5:6:7'], array_column($rows, 'label'));
    $t->same([122, 113, 112, 104, 95, 95], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next203 recursive queue trace'] = static function (TestRunner $t) use ($summary203): void {
    $queue = $summary203()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7', 'seed:2:3:4:5:6:7:8'], $queue['currentEmittedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentQueueFronts'], 0, 3));
    $t->same([8, 8], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next203 window shape'] = static function (TestRunner $t) use ($summary203): void {
    $windows = $summary203()['windows'];
    $t->same(['lag', 'last_value'], $windows['functions']);
    $t->same(['last_value'], $windows['frameFunctions']);
    $t->same([2], $windows['lagDefaults']);
    $t->same([false, true], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
};

$tests['compound select window recursive limit current source next203 token fence'] = static function (TestRunner $t) use ($summary203): void {
    $first = $summary203();
    $second = $summary203($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
    $t->same(['plugin_prime'], $first['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['seed:2:3:4:5:6:7:8'], $first['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next203 rejects stale cursor'] = static function (TestRunner $t) use ($summary203): void {
    $cursor = $summary203()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary203($cursor));
};

$tests['compound select window recursive limit current source next203 limit trace'] = static function (TestRunner $t) use ($summary203): void {
    $trace = $summary203()['limitTrace'];
    $t->same(['seed:2:3'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['home', 'rewrite_rules'], array_slice(array_column($trace['current']['truncatedAfterLimit'], 'label'), 0, 2));
    $t->same(['seed:2:3:4:5:6:7:8', 'home'], array_slice(array_column($trace['next']['truncatedAfterLimit'], 'label'), 0, 2));
};

$tests['compound select window recursive limit current source next203 replan reasons'] = static function (TestRunner $t) use ($summary203): void {
    $plan = $summary203();
    $t->contains('avoids accepted next196', $plan['non_overlap']);
    $t->true(in_array('compound-lag-last-value-current-source-next203', $plan['replanReasons'], true));
    $t->true(in_array('recursive-order-limit-offset-before-offset-windows-next203', $plan['replanReasons'], true));
    $t->true(in_array('except-after-window-output-next203', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next203 rejects missing last value'] = static function (TestRunner $t) use ($currentTables203): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext203(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, lag(score, 1, -1) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options EXCEPT SELECT id, label, score FROM q ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables203,
        $currentTables203,
    ));
};

$tests['compound select window recursive limit current source next203 rejects unordered recursive limit'] = static function (TestRunner $t) use ($currentTables203): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext203(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 9 LIMIT 7 OFFSET 1) SELECT id, label, lag(score, 1, -1) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, last_value(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options EXCEPT SELECT id, label, score FROM q ORDER BY metric DESC, id LIMIT 6 OFFSET 1",
        $currentTables203,
        $currentTables203,
    ));
};

foreach (range(1, 56) as $case) {
    $tests['compound select window recursive limit current source next203 generated lag except boundary ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 9 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, lag(score, 1, -1) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, last_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT id, label, score AS metric FROM q WHERE id = 2 ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext203($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext203($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['lag', 'last_value'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->same([2], $plan['windows']['lagDefaults']);
    };
}

return $tests;
