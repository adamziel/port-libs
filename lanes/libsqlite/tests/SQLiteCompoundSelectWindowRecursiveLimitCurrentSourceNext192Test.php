<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions192 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 104],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 75],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 20],
];
$nextOptions192 = [
    ...$currentOptions192,
    ['option_id' => 5, 'option_name' => 'plugin_loaded', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 83],
];
$currentTables192 = ['wp_options' => $currentOptions192];
$nextTables192 = ['wp_options' => $nextOptions192];

$sql192 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 132)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       percent_rank() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       cume_dist() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT id,
       label,
       score AS metric
  FROM q
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summary192 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCurrentGap($sql192, $currentTables192, $nextTables192, $cursor);
$tests = [];

$tests['compound select window recursive limit recursiveCurrentGap status dependencies'] = static function (TestRunner $t) use ($summary192): void {
    $plan = $summary192();
    $t->same('compound-select-window-recursive-limit-current-source-recursiveCurrentGap-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-recursiveCurrentGap',
        'sqlite-select-sql-percent-rank-cume-dist-window-recursiveCurrentGap',
        'sqlite-current-source-token-fence-recursiveCurrentGap',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit recursiveCurrentGap compound metadata'] = static function (TestRunner $t) use ($summary192): void {
    $compound = $summary192()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
};

$tests['compound select window recursive limit recursiveCurrentGap recursive ordered queue'] = static function (TestRunner $t) use ($summary192): void {
    $queue = $summary192()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $queue['currentEmittedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentQueueFronts'], 0, 3));
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit recursiveCurrentGap distribution windows'] = static function (TestRunner $t) use ($summary192): void {
    $windows = $summary192()['windows'];
    $t->same(['percent_rank', 'cume_dist'], $windows['functions']);
    $t->same(['percent_rank', 'cume_dist'], $windows['distributionFunctions']);
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit recursiveCurrentGap source tokens'] = static function (TestRunner $t) use ($summary192): void {
    $source = $summary192()['sourceWindow'];
    $t->same(64, strlen($source['currentToken']));
    $t->same(64, strlen($source['nextToken']));
    $t->same(false, $source['currentToken'] === $source['nextToken']);
    $t->same(['seed:2:3', 'seed:2:3:4'], array_slice($source['currentAdmittedLabels'], 0, 2));
    $t->true(in_array('plugin_loaded', $source['nextTruncatedLabels'], true));
};

$tests['compound select window recursive limit recursiveCurrentGap cursor accepts current token'] = static function (TestRunner $t) use ($summary192): void {
    $first = $summary192();
    $second = $summary192($first['cursor']);
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
};

$tests['compound select window recursive limit recursiveCurrentGap rejects stale cursor'] = static function (TestRunner $t) use ($summary192): void {
    $cursor = $summary192()['cursor'];
    $cursor['currentToken'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary192($cursor));
};

$tests['compound select window recursive limit recursiveCurrentGap rejects missing queue order'] = static function (TestRunner $t) use ($currentTables192): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCurrentGap(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id, label, percent_rank() OVER (ORDER BY id) AS metric FROM q UNION SELECT option_id, option_name, cume_dist() OVER (ORDER BY score) FROM wp_options ORDER BY metric LIMIT 2 OFFSET 0",
        $currentTables192,
        $currentTables192,
    ));
};

$tests['compound select window recursive limit recursiveCurrentGap rejects missing distribution window'] = static function (TestRunner $t) use ($currentTables192): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCurrentGap(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 ORDER BY score DESC LIMIT 2 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY id) AS metric FROM q UNION SELECT option_id, option_name, cume_dist() OVER (ORDER BY score) FROM wp_options ORDER BY metric LIMIT 2 OFFSET 0",
        $currentTables192,
        $currentTables192,
    ));
};

$tests['compound select window recursive limit recursiveCurrentGap rejects missing final offset'] = static function (TestRunner $t) use ($currentTables192): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCurrentGap(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 ORDER BY score DESC LIMIT 2 OFFSET 1) SELECT id, label, percent_rank() OVER (ORDER BY id) AS metric FROM q UNION SELECT option_id, option_name, cume_dist() OVER (ORDER BY score) FROM wp_options ORDER BY metric LIMIT 2",
        $currentTables192,
        $currentTables192,
    ));
};

$tests['compound select window recursive limit recursiveCurrentGap non overlap reasons'] = static function (TestRunner $t) use ($summary192): void {
    $plan = $summary192();
    $t->contains('avoids accepted source-token-fence', $plan['non_overlap']);
    $t->true(in_array('recursive-queue-order-limit-current-source-recursiveCurrentGap', $plan['replanReasons'], true));
    $t->true(in_array('percent-rank-cume-dist-before-compound-limit-recursiveCurrentGap', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit recursiveCurrentGap generated distribution queue ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 5 + ($case % 4);
        $finalLimit = 3 + ($case % 5);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 120 + $case],
                ['option_id' => 2, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 108 + $case],
                ['option_id' => 3, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 96 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 10 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'queued_' . $case, 'autoload' => 'yes', 'score' => 114 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (132 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 8 FROM q WHERE id < 9 ORDER BY score DESC LIMIT {$recursiveLimit} OFFSET 1) SELECT id, label, percent_rank() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, cume_dist() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' UNION SELECT id, label, score AS metric FROM q ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCurrentGap($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCurrentGap($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['percent_rank', 'cume_dist'], $plan['windows']['distributionFunctions']);
        $t->same('seed_' . $case, $plan['recursiveQueue']['currentSkippedLabels'][0]);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->same(64, strlen($plan['sourceWindow']['nextToken']));
    };
}

return $tests;
