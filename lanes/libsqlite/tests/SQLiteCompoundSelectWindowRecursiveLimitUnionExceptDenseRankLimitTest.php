<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions229 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions229 = [
    ...$currentOptions229,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables229 = ['wp_options' => $currentOptions229];
$nextTables229 = ['wp_options' => $nextOptions229];

$sql229 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 2
)
SELECT id,
       label,
       dense_rank() OVER (ORDER BY score DESC) AS rn
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY rn, label
 LIMIT 3 OFFSET 1
SQL;

$summary229 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionExceptDenseRankLimit($sql229, $currentTables229, $nextTables229, $cursor);
$tests = [];

$tests['compound select window recursive limit current source union-except-dense-rank-limit status dependencies'] = static function (TestRunner $t) use ($summary229): void {
    $plan = $summary229();
    $t->same('compound-select-window-recursive-limit-current-source-union-except-dense-rank-limit-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-limit-offset-union-except-dense-rank-limit',
        'sqlite-select-sql-dense-rank-window-union-except-union-except-dense-rank-limit',
        'sqlite-compound-current-source-token-fence-union-except-dense-rank-limit',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit compound metadata'] = static function (TestRunner $t) use ($summary229): void {
    $compound = $summary229()['compound'];
    $t->same(['UNION', 'EXCEPT'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['rn', 'label'], $compound['orderColumns']);
    $t->same([3, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionDistinctHead']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit current rows'] = static function (TestRunner $t) use ($summary229): void {
    $rows = $summary229()['currentRows'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], array_column($rows, 'label'));
    $t->same([2, 2, 3], array_column($rows, 'rn'));
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit next rows shift except page'] = static function (TestRunner $t) use ($summary229): void {
    $plan = $summary229();
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], array_column($plan['nextRows'], 'label'));
    $t->same([2, 2, 3], array_column($plan['nextRows'], 'rn'));
    $t->same(['seed:2:3'], $plan['sourceWindow']['nextSkippedLabels']);
    $t->same(['seed:2:3:4:5', 'rewrite_rules', 'seed:2:3:4:5:6', 'blogname', 'seed:2:3:4:5:6:7'], $plan['sourceWindow']['nextTruncatedLabels']);
    $t->same(['plugin_prime'], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit recursive queue limit'] = static function (TestRunner $t) use ($summary229): void {
    $queue = $summary229()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed', 'seed:2'], $queue['currentSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit window metadata'] = static function (TestRunner $t) use ($summary229): void {
    $windows = $summary229()['windows'];
    $t->same(['dense_rank'], $windows['functions']);
    $t->same([0, 1, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 1, 1], array_column($windows['current'], 'orderCount'));
    $t->same([1, 2, 2, 3, 3, 4, 4, 5], array_map('intval', $windows['currentRanks']));
    $t->same([1, 2, 2, 3, 3, 4, 4, 5, 5], array_map('intval', $windows['nextRanks']));
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit source window trace'] = static function (TestRunner $t) use ($summary229): void {
    $window = $summary229()['sourceWindow'];
    $t->same(['seed:2:3'], $window['currentSkippedLabels']);
    $t->same(['seed:2:3'], $window['nextSkippedLabels']);
    $t->same(['seed:2:3:4:5', 'blogname', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $window['currentTruncatedLabels']);
    $t->same(['seed:2:3:4:5', 'rewrite_rules', 'seed:2:3:4:5:6', 'blogname', 'seed:2:3:4:5:6:7'], $window['nextTruncatedLabels']);
    $t->true($window['intersectExceptBoundaryChanged']);
    $t->same(false, $window['currentToken'] === $window['nextToken']);
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit cursor token fence'] = static function (TestRunner $t) use ($summary229): void {
    $first = $summary229();
    $second = $summary229($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(4, $first['cursor']['resumeOffset']);
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit rejects stale cursor'] = static function (TestRunner $t) use ($summary229): void {
    $cursor = $summary229()['cursor'];
    $cursor['currentToken'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary229($cursor));
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit executor parity'] = static function (TestRunner $t) use ($sql229, $currentTables229, $summary229): void {
    $t->same(SQLiteSelectSql::execute($sql229, $currentTables229), $summary229()['currentRows']);
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit replan reasons'] = static function (TestRunner $t) use ($summary229): void {
    $plan = $summary229();
    $t->contains('does not repeat next224', $plan['non_overlap']);
    $t->true(in_array('compound-union-distinct-except-dense-rank-current-source-union-except-dense-rank-limit', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-before-union-except-window-union-except-dense-rank-limit', $plan['replanReasons'], true));
    $t->true(in_array('dense-rank-shift-changes-except-final-limit-page-union-except-dense-rank-limit', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit rejects missing except'] = static function (TestRunner $t) use ($currentTables229): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionExceptDenseRankLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 5 OFFSET 2) SELECT id, label, dense_rank() OVER (ORDER BY score DESC) AS rn FROM q UNION SELECT option_id, option_name, dense_rank() OVER (ORDER BY score DESC) FROM wp_options ORDER BY rn, label LIMIT 3 OFFSET 1",
        $currentTables229,
        $currentTables229,
    ));
};

$tests['compound select window recursive limit current source union-except-dense-rank-limit rejects missing recursive offset'] = static function (TestRunner $t) use ($currentTables229): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionExceptDenseRankLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 5) SELECT id, label, dense_rank() OVER (ORDER BY score DESC) AS rn FROM q UNION SELECT option_id, option_name, dense_rank() OVER (ORDER BY score DESC) FROM wp_options EXCEPT SELECT option_id, option_name, dense_rank() OVER (ORDER BY score DESC) FROM wp_options ORDER BY rn, label LIMIT 3 OFFSET 1",
        $currentTables229,
        $currentTables229,
    ));
};

foreach (range(1, 60) as $case) {
    $tests['compound select window recursive limit current source union-except-dense-rank-limit generated intersect except boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 80 + $case],
                ['option_id' => 4, 'option_name' => 'blog_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
                ['option_id' => 5, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 60 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 6, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 95 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 5 OFFSET 2) SELECT id, label, dense_rank() OVER (ORDER BY score DESC) AS rn FROM q UNION SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn FROM wp_options WHERE option_name IN ('siteurl_{$case}') ORDER BY rn, label LIMIT 3 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionExceptDenseRankLimit($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionExceptDenseRankLimit($sql, $tables, $nextTables, $plan['cursor']);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3:4', 'rewrite_' . $case], array_column($plan['currentRows'], 'label'));
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], array_column($plan['nextRows'], 'label'));
        $t->same(['seed_' . $case . ':2:3'], $plan['sourceWindow']['nextSkippedLabels']);
        $t->same([
            'seed_' . $case . ':2:3:4:5',
            'rewrite_' . $case,
            'seed_' . $case . ':2:3:4:5:6',
            'blog_' . $case,
            'seed_' . $case . ':2:3:4:5:6:7',
        ], $plan['sourceWindow']['nextTruncatedLabels']);
        $t->same(['plugin_' . $case], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
        $t->same('seed_' . $case, $plan['recursiveQueue']['currentSkippedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
    };
}

return $tests;
