<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions183 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 92],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 78],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 64],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 22],
];
$nextOptions183 = [
    ...$currentOptions183,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 88],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 70],
];
$currentTables183 = ['wp_options' => $currentOptions183];
$nextTables183 = ['wp_options' => $nextOptions183];

$sql183 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 104)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 2
)
SELECT id,
       label,
       lag(score, 1, score) OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       lead(score, 1, score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_id AS id,
       option_name AS label,
       score AS metric
  FROM wp_options
 WHERE score >= 64
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 2
SQL;

$summary183 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareTailWindowLimitBoundary($sql183, $currentTables183, $nextTables183);
$tests = [];

$tests['compound select window recursive limit tail-window-limit-boundary status dependencies'] = static function (TestRunner $t) use ($summary183): void {
    $plan = $summary183();
    $t->same('compound-select-window-recursive-limit-current-source-tail-window-limit-boundary-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-offset-tail-window-limit-boundary',
        'sqlite-select-sql-compound-window-tail-limit-tail-window-limit-boundary',
        'sqlite-select-sql-current-source-boundary-tail-window-limit-boundary',
        'sqlite-current-source-tail-window-limit-boundary',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit tail-window-limit-boundary compound metadata'] = static function (TestRunner $t) use ($summary183): void {
    $compound = $summary183()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(2, $compound['offset']);
    $t->true($compound['hasUnionAll']);
    $t->true($compound['hasUnionDistinct']);
};

$tests['compound select window recursive limit tail-window-limit-boundary row boundaries'] = static function (TestRunner $t) use ($summary183): void {
    $plan = $summary183();
    $t->same(['siteurl', 'plugin_alpha', 'seed:2:3', 'seed:2:3:4', 'home', 'plugin_alpha'], array_column($plan['nextRows'], 'label'));
    $t->same(['seed:2:3', 'seed:2:3:4', 'siteurl', 'home', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], array_column($plan['currentRows'], 'label'));
    $t->same(['plugin_alpha'], $plan['tailWindowLimit']['gainedLabels']);
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6'], $plan['tailWindowLimit']['lostLabels']);
};

$tests['compound select window recursive limit tail-window-limit-boundary recursive offset trace'] = static function (TestRunner $t) use ($summary183): void {
    $recursive = $summary183()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed', 'seed:2'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7', 'seed:2:3:4:5:6:7:8'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit tail-window-limit-boundary window metadata'] = static function (TestRunner $t) use ($summary183): void {
    $windows = $summary183()['windows'];
    $t->same(['lag', 'lead'], $windows['functions']);
    $t->same(['metric', 'metric'], array_column($windows['current'], 'alias'));
    $t->same([3, 3], array_column($windows['current'], 'argumentCount'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit tail-window-limit-boundary tail diagnostics'] = static function (TestRunner $t) use ($summary183): void {
    $tail = $summary183()['tailWindowLimit'];
    $t->same(['siteurl', 'rewrite_rules'], $tail['nextSkippedLabels']);
    $t->same(['home', 'rewrite_rules', 'seed:2:3:4:5:6:7'], array_slice($tail['currentTruncatedLabels'], 0, 3));
    $t->same(['seed:2:3:4:5', 'home', 'theme_mods'], array_slice($tail['nextTruncatedLabels'], 0, 3));
    $t->true($tail['windowMetrics'][0] >= $tail['windowMetrics'][count($tail['windowMetrics']) - 1]);
};

$tests['compound select window recursive limit tail-window-limit-boundary replan reasons'] = static function (TestRunner $t) use ($summary183): void {
    $reasons = $summary183()['replanReasons'];
    $t->true(in_array('limited-distinct-union-rowset-changed', $reasons, true));
    $t->true(in_array('prelimit-distinct-union-rowset-changed', $reasons, true));
    $t->true(in_array('compound-tail-window-limit-current-source-tail-window-limit-boundary', $reasons, true));
    $t->true(in_array('recursive-offset-window-arm-before-union-distinct-tail-window-limit-boundary', $reasons, true));
    $t->true(in_array('wordpress-option-boundary-replans-final-limit-tail-window-limit-boundary', $reasons, true));
};

$tests['compound select window recursive limit tail-window-limit-boundary rejects missing recursive cte'] = static function (TestRunner $t) use ($currentTables183): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareTailWindowLimitBoundary(
        'SELECT option_id AS id, option_name AS label, score AS metric FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 2 OFFSET 1',
        $currentTables183,
        $currentTables183,
    ));
};

$tests['compound select window recursive limit tail-window-limit-boundary rejects missing final offset'] = static function (TestRunner $t) use ($currentTables183): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareTailWindowLimitBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 104) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 8 LIMIT 6 OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(score, 1, score) OVER (ORDER BY score) FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 6",
        $currentTables183,
        $currentTables183,
    ));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit tail-window-limit-boundary generated boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 5 + ($case % 4);
        $finalLimit = 4 + ($case % 3);
        $scoreFloor = 60 + $case;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 104 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 88 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => 72 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 18 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (116 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 9 FROM q WHERE id < 9 LIMIT {$recursiveLimit} OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, lead(score, 1, score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' UNION SELECT option_id AS id, option_name AS label, score AS metric FROM wp_options WHERE score >= {$scoreFloor} ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 2";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['metric']));
        $t->true($rows[0]['metric'] >= $rows[count($rows) - 1]['metric']);
        $t->same(false, in_array('seed_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
