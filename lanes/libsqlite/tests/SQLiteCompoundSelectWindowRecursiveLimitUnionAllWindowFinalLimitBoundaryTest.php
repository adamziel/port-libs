<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptionsUnionAllBoundary = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 60],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 55],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 50],
    ['option_id' => 4, 'option_name' => 'transient_cache', 'autoload' => 'no', 'weight' => 99],
];
$nextOptionsUnionAllBoundary = [
    ...$currentOptionsUnionAllBoundary,
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 68],
];
$currentTablesUnionAllBoundary = ['wp_options' => $currentOptionsUnionAllBoundary];
$nextTablesUnionAllBoundary = ['wp_options' => $nextOptionsUnionAllBoundary];

$sqlUnionAllBoundary = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 70)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 5
      FROM q
     WHERE id < 6
     LIMIT 5
)
SELECT id,
       label,
       row_number() OVER (ORDER BY weight DESC, id) AS bucket
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY weight DESC, option_id) AS bucket
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY bucket, id
 LIMIT 8
SQL;

$summaryUnionAllBoundary = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionAllWindowFinalLimitBoundary($sqlUnionAllBoundary, $currentTablesUnionAllBoundary, $nextTablesUnionAllBoundary);
$tests = [];

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary status dependencies'] = static function (TestRunner $t) use ($summaryUnionAllBoundary): void {
    $plan = $summaryUnionAllBoundary();
    $t->same('compound-select-window-recursive-limit-current-source-union-all-window-final-limit-boundary-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-cte-limit-union-all-window-final-limit-boundary',
        'sqlite-select-sql-compound-union-all-window-union-all-window-final-limit-boundary',
        'sqlite-select-sql-final-limit-current-next-boundary-union-all-window-final-limit-boundary',
        'sqlite-current-source-union-all-window-final-limit-boundary',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary compound metadata'] = static function (TestRunner $t) use ($summaryUnionAllBoundary): void {
    $compound = $summaryUnionAllBoundary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['bucket', 'id'], $compound['orderColumns']);
    $t->same(8, $compound['limit']);
    $t->same(0, $compound['offset']);
    $t->true($compound['currentLimitExactlyFilled']);
    $t->true($compound['nextPreLimitOverflowsFinalLimit']);
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary current rows'] = static function (TestRunner $t) use ($summaryUnionAllBoundary): void {
    $rows = $summaryUnionAllBoundary()['currentRows'];
    $t->same([1, 1, 2, 2, 3, 3, 4, 5], array_column($rows, 'id'));
    $t->same(['seed', 'siteurl', 'seed:2', 'home', 'seed:2:3', 'blogname', 'seed:2:3:4', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same([1, 1, 2, 2, 3, 3, 4, 5], array_column($rows, 'bucket'));
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary next rows'] = static function (TestRunner $t) use ($summaryUnionAllBoundary): void {
    $rows = $summaryUnionAllBoundary()['nextRows'];
    $t->same([1, 5, 1, 2, 2, 3, 3, 4], array_column($rows, 'id'));
    $t->same(['seed', 'rewrite_rules', 'siteurl', 'seed:2', 'home', 'seed:2:3', 'blogname', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([1, 1, 2, 2, 3, 3, 4, 4], array_column($rows, 'bucket'));
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary prelimit and trace'] = static function (TestRunner $t) use ($summaryUnionAllBoundary): void {
    $plan = $summaryUnionAllBoundary();
    $t->same(8, count($plan['currentPreLimitRows']));
    $t->same(9, count($plan['nextPreLimitRows']));
    $t->same('seed:2:3:4:5', $plan['limitTrace']['next']['firstTruncated']['label']);
    $t->same(5, $plan['recursive']['currentTraceCount']);
    $t->same(5, $plan['recursive']['nextTraceCount']);
    $t->same(0, $plan['recursive']['currentLimitRemaining']);
    $t->same(['seed', 'seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], array_column($plan['recursive']['currentRows'], 'label'));
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary window metadata'] = static function (TestRunner $t) use ($summaryUnionAllBoundary): void {
    $windows = $summaryUnionAllBoundary()['windows'];
    $t->same(['row_number', 'dense_rank'], $windows['functions']);
    $t->same(['bucket', 'bucket'], array_column($windows['current'], 'alias'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
    $t->same([0, 0], array_column($windows['current'], 'partitionCount'));
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary boundary delta'] = static function (TestRunner $t) use ($summaryUnionAllBoundary): void {
    $boundary = $summaryUnionAllBoundary()['boundary'];
    $t->same('seed', $boundary['currentFirst']['label']);
    $t->same('seed', $boundary['nextFirst']['label']);
    $t->same('seed:2:3:4:5', $boundary['currentLast']['label']);
    $t->same('seed:2:3:4', $boundary['nextLast']['label']);
    $t->contains('"label":"rewrite_rules"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"seed:2:3:4:5"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary rank delta'] = static function (TestRunner $t) use ($summaryUnionAllBoundary): void {
    $rank = $summaryUnionAllBoundary()['rankDelta'];
    $t->same(1, $rank['currentBucketsByLabel']['siteurl']);
    $t->same(2, $rank['nextBucketsByLabel']['siteurl']);
    $t->same(2, $rank['currentBucketsByLabel']['home']);
    $t->same(3, $rank['nextBucketsByLabel']['home']);
    $t->true(in_array('siteurl', $rank['changedLabels'], true));
    $t->true(in_array('blogname', $rank['changedLabels'], true));
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary limit trace'] = static function (TestRunner $t) use ($summaryUnionAllBoundary): void {
    $trace = $summaryUnionAllBoundary()['limitTrace'];
    $t->same(8, $trace['current']['preLimitCount']);
    $t->same(8, $trace['current']['acceptedCount']);
    $t->same([], $trace['current']['truncatedAfterLimit']);
    $t->same(9, $trace['next']['preLimitCount']);
    $t->same(8, $trace['next']['acceptedCount']);
    $t->same(['seed:2:3:4:5'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary replan reasons'] = static function (TestRunner $t) use ($summaryUnionAllBoundary): void {
    $reasons = $summaryUnionAllBoundary()['replanReasons'];
    $t->true(in_array('compound-final-limit-current-next-boundary', $reasons, true));
    $t->true(in_array('current-rowset-exactly-fills-final-limit', $reasons, true));
    $t->true(in_array('next-source-rowset-truncated-after-final-limit', $reasons, true));
    $t->true(in_array('limited-rowset-boundary-changed', $reasons, true));
    $t->true(in_array('prelimit-window-rowset-changed', $reasons, true));
    $t->true(in_array('window-evaluated-before-compound-final-limit', $reasons, true));
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary rejects union distinct'] = static function (TestRunner $t) use ($currentTablesUnionAllBoundary): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionAllWindowFinalLimitBoundary(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 70) UNION ALL SELECT id + 1, label, weight - 5 FROM q WHERE id < 6 LIMIT 5) SELECT id, label, row_number() OVER (ORDER BY weight) AS bucket FROM q UNION SELECT option_id, option_name, dense_rank() OVER (ORDER BY weight) FROM wp_options ORDER BY bucket LIMIT 8",
        $currentTablesUnionAllBoundary,
        $currentTablesUnionAllBoundary,
    ));
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary rejects missing recursive limit'] = static function (TestRunner $t) use ($currentTablesUnionAllBoundary): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionAllWindowFinalLimitBoundary(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 70) UNION ALL SELECT id + 1, label, weight - 5 FROM q WHERE id < 6) SELECT id, label, row_number() OVER (ORDER BY weight) AS bucket FROM q UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY weight) FROM wp_options ORDER BY bucket LIMIT 8",
        $currentTablesUnionAllBoundary,
        $currentTablesUnionAllBoundary,
    ));
};

$tests['compound select window recursive limit current-source union-all-window-final-limit-boundary rejects missing window'] = static function (TestRunner $t) use ($currentTablesUnionAllBoundary): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionAllWindowFinalLimitBoundary(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 70) UNION ALL SELECT id + 1, label, weight - 5 FROM q WHERE id < 6 LIMIT 5) SELECT id, label, weight AS bucket FROM q UNION ALL SELECT option_id, option_name, weight FROM wp_options ORDER BY bucket LIMIT 8",
        $currentTablesUnionAllBoundary,
        $currentTablesUnionAllBoundary,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit current-source union-all-window-final-limit-boundary generated boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'weight' => 60 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 55 + $case],
                ['option_id' => 3, 'option_name' => 'blogname_' . $case, 'autoload' => 'yes', 'weight' => 50 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'weight' => 99 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed_{$case}', " . (70 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 5 FROM q WHERE id < 6 LIMIT 5) SELECT id, label, row_number() OVER (ORDER BY weight DESC, id) AS bucket FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (ORDER BY weight DESC, option_id) AS bucket FROM wp_options WHERE autoload = 'yes' ORDER BY bucket, id LIMIT 8";
        $rows = SQLiteSelectSql::execute($sql, $tables);
        $preLimit = SQLiteSelectSql::execute(preg_replace('/\s+LIMIT\s+8$/', '', $sql) ?? $sql, $tables);

        $t->same(8, count($rows));
        $t->same(8, count($preLimit));
        $t->same('seed_' . $case, $rows[0]['label'] ?? null);
        $t->same('seed_' . $case . ':2:3:4:5', $rows[7]['label'] ?? null);
    };
}

return $tests;
