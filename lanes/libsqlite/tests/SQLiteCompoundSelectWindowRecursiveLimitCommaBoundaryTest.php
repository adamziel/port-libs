<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions186 = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'load_policy' => 'yes', 'score' => 101],
    ['setting_id' => 2, 'key_name' => 'home', 'load_policy' => 'yes', 'score' => 84],
    ['setting_id' => 3, 'key_name' => 'rewrite_rules', 'load_policy' => 'yes', 'score' => 67],
    ['setting_id' => 4, 'key_name' => 'cache_seed', 'load_policy' => 'no', 'score' => 20],
];
$nextOptions186 = [
    ...$currentOptions186,
    ['setting_id' => 5, 'key_name' => 'plugin_ranked', 'load_policy' => 'yes', 'score' => 96],
    ['setting_id' => 6, 'key_name' => 'theme_mods_next', 'load_policy' => 'yes', 'score' => 73],
];
$currentTables186 = ['app_settings' => $currentOptions186];
$nextTables186 = ['app_settings' => $nextOptions186];

$sql186 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 118)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 9
     LIMIT 7 OFFSET 2
)
SELECT id,
       label,
       lag(score, 1, score) OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT setting_id AS id,
       key_name AS label,
       lead(score, 1, score) OVER (PARTITION BY load_policy ORDER BY score DESC, setting_id) AS metric
  FROM app_settings
 WHERE load_policy = 'yes'
UNION ALL
SELECT id,
       label,
       rank() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT setting_id AS id,
       key_name AS label,
       dense_rank() OVER (PARTITION BY load_policy ORDER BY score DESC, setting_id) AS metric
  FROM app_settings
 WHERE load_policy = 'yes'
UNION
SELECT setting_id AS id,
       key_name AS label,
       score AS metric
  FROM app_settings
 WHERE score >= 67
 ORDER BY metric DESC, id
 LIMIT 3, 6
SQL;

$summary186 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCommaLimitRecursiveWindowBoundary($sql186, $currentTables186, $nextTables186);
$tests = [];

$tests['compound select window recursive limit next186 status dependencies'] = static function (TestRunner $t) use ($summary186): void {
    $plan = $summary186();
    $t->same('compound-select-window-recursive-limit-current-source-next186-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-offset-next186',
        'sqlite-select-sql-compound-comma-limit-next186',
        'sqlite-select-sql-window-rank-dense-rank-next186',
        'sqlite-current-source-next186',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit next186 compound comma limit metadata'] = static function (TestRunner $t) use ($summary186): void {
    $compound = $summary186()['compound'];
    $t->same(['UNION ALL', 'UNION ALL', 'UNION ALL', 'UNION'], $compound['operators']);
    $t->same(5, $compound['currentArms']);
    $t->same(5, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(3, $compound['offset']);
    $t->same(['offset' => 3, 'count' => 6], $compound['commaLimit']);
};

$tests['compound select window recursive limit next186 row boundary labels'] = static function (TestRunner $t) use ($summary186): void {
    $boundary = $summary186()['sourceBoundary'];
    $t->same(['rewrite_rules', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'siteurl', 'home', 'seed:2:3:4:5:6:7'], $boundary['currentAdmittedLabels']);
    $t->same(6, count($boundary['nextAdmittedLabels']));
    $t->true(in_array('plugin_ranked', $boundary['nextAdmittedLabels'], true));
    $t->true(in_array('theme_mods_next', $boundary['nextTruncatedLabels'], true));
};

$tests['compound select window recursive limit next186 recursive and window metadata'] = static function (TestRunner $t) use ($summary186): void {
    $plan = $summary186();
    $t->same('q', $plan['recursive']['name']);
    $t->same(['seed', 'seed:2'], $plan['recursive']['currentSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7', 'seed:2:3:4:5:6:7:8', 'seed:2:3:4:5:6:7:8:9'], $plan['recursive']['currentEmittedLabels']);
    $t->same(['lag', 'lead', 'rank', 'dense_rank'], $plan['windows']['functions']);
    $t->same([0, 1, 0, 1], array_column($plan['windows']['current'], 'partitionCount'));
};

$tests['compound select window recursive limit next186 source boundary diagnostics'] = static function (TestRunner $t) use ($summary186): void {
    $boundary = $summary186()['sourceBoundary'];
    $t->same(['seed:2:3', 'seed:2:3:4', 'siteurl'], $boundary['nextSkippedLabels']);
    $t->true(count($boundary['addedAdmittedLabels']) > 0);
    $t->true(count($boundary['removedAdmittedLabels']) > 0);
    $t->same(['siteurl', 'rewrite_rules', 'siteurl', 'plugin_ranked', 'home'], array_slice($boundary['nextLoadPolicyWindowLabels'], 0, 5));
    $t->true(in_array('seed:2:3:4:5:6:7:8:9', $boundary['nextRecursiveLabels'], true));
};

$tests['compound select window recursive limit next186 replan reasons'] = static function (TestRunner $t) use ($summary186): void {
    $reasons = $summary186()['replanReasons'];
    $t->true(in_array('compound-tail-comma-limit-current-source-next186', $reasons, true));
    $t->true(in_array('window-rank-dense-rank-before-distinct-union-next186', $reasons, true));
    $t->true(in_array('recursive-offset-source-boundary-next186', $reasons, true));
    $t->true(in_array('application-load-policy-setting-rank-replans-limit-window-next186', $reasons, true));
};

$tests['compound select window recursive limit next186 rejects offset limit syntax'] = static function (TestRunner $t) use ($currentTables186): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCommaLimitRecursiveWindowBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 118) UNION ALL SELECT id + 1, label, score - 8 FROM q WHERE id < 9 LIMIT 7 OFFSET 2) SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT setting_id, key_name, dense_rank() OVER (ORDER BY score DESC) FROM app_settings UNION SELECT setting_id, key_name, score FROM app_settings ORDER BY metric DESC, id LIMIT 6 OFFSET 3",
        $currentTables186,
        $currentTables186,
    ));
};

$tests['compound select window recursive limit next186 rejects missing rank windows'] = static function (TestRunner $t) use ($currentTables186): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCommaLimitRecursiveWindowBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 118) UNION ALL SELECT id + 1, label, score - 8 FROM q WHERE id < 9 LIMIT 7 OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT setting_id, key_name, lead(score, 1, score) OVER (ORDER BY score DESC) FROM app_settings UNION SELECT setting_id, key_name, score FROM app_settings ORDER BY metric DESC, id LIMIT 3, 6",
        $currentTables186,
        $currentTables186,
    ));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit next186 generated comma limit boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 6 + ($case % 4);
        $finalLimit = 4 + ($case % 4);
        $scoreFloor = 61 + ($case % 13);
        $tables = [
            'app_settings' => [
                ['setting_id' => 1, 'key_name' => 'siteurl_' . $case, 'load_policy' => 'yes', 'score' => 106 + $case],
                ['setting_id' => 2, 'key_name' => 'plugin_' . $case, 'load_policy' => 'yes', 'score' => 92 + $case],
                ['setting_id' => 3, 'key_name' => 'home_' . $case, 'load_policy' => 'yes', 'score' => 82 + $case],
                ['setting_id' => 4, 'key_name' => 'rewrite_' . $case, 'load_policy' => 'yes', 'score' => 66 + $case],
                ['setting_id' => 5, 'key_name' => 'transient_' . $case, 'load_policy' => 'no', 'score' => 18 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (120 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 8 FROM q WHERE id < 10 LIMIT {$recursiveLimit} OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT setting_id AS id, key_name AS label, lead(score, 1, score) OVER (PARTITION BY load_policy ORDER BY score DESC, setting_id) AS metric FROM app_settings WHERE load_policy = 'yes' UNION SELECT setting_id AS id, key_name AS label, score AS metric FROM app_settings WHERE score >= {$scoreFloor} ORDER BY metric DESC, id LIMIT 3, {$finalLimit}";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['metric']));
        $t->true($rows[0]['metric'] >= $rows[count($rows) - 1]['metric']);
        $t->same(false, in_array('seed_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
