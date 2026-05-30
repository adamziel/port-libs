<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 96],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 82],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_search', 'autoload' => 'yes', 'score' => 108],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 74],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 130)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 7
      FROM q
     WHERE id < 9
     LIMIT (2 + 4) OFFSET (1 + 1)
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
UNION ALL
SELECT id,
       label,
       first_value(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       score AS metric
  FROM wp_options
 WHERE score >= 74
 ORDER BY metric DESC, id
 LIMIT (2 * 3) OFFSET (1 + 1)
SQL;

$summary = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareExpressionLimitBoundary($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound select window recursive limit expression-limit-boundary status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-window-recursive-limit-current-source-expression-limit-boundary-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-expression-expression-limit-boundary',
        'sqlite-select-sql-compound-final-limit-expression-expression-limit-boundary',
        'sqlite-select-sql-window-before-expression-limit-expression-limit-boundary',
        'sqlite-current-source-expression-limit-boundary',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit expression-limit-boundary compound expression metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL', 'UNION ALL', 'UNION'], $compound['operators']);
    $t->same(4, $compound['currentArms']);
    $t->same(4, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(2, $compound['offset']);
    $t->same('(2 * 3)', $compound['limitExpression']);
    $t->same('(1 + 1)', $compound['offsetExpression']);
};

$tests['compound select window recursive limit expression-limit-boundary recursive expression metadata'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same('(2 + 4)', $recursive['limitExpression']);
    $t->same('(1 + 1)', $recursive['offsetExpression']);
    $t->same(['seed', 'seed:2'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7', 'seed:2:3:4:5:6:7:8'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit expression-limit-boundary current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(6, count($rows));
    $t->same(['siteurl', 'rewrite_rules', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], array_column($rows, 'label'));
    $t->same([112, 112, 109, 109, 102, 102], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit expression-limit-boundary next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(6, count($rows));
    $t->same(['siteurl', 'theme_mods_next', 'seed:2:3:4', 'seed:2:3:4:5', 'siteurl', 'plugin_search'], array_column($rows, 'label'));
    $t->same([112, 112, 109, 109, 108, 108], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit expression-limit-boundary windows before compound'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['lag', 'lead', 'first_value'], $windows['functions']);
    $t->same(['metric', 'metric', 'metric'], array_column($windows['current'], 'alias'));
    $t->same([0, 1, 0], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2, 1], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit expression-limit-boundary expression boundary'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['expressionLimitBoundary'];
    $t->same(['siteurl', 'rewrite_rules', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $boundary['currentAdmittedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4'], $boundary['currentSkippedBeforeFinalOffset']);
    $t->same(['siteurl', 'home', 'seed:2:3:4:5:6'], array_slice($boundary['currentTruncatedAfterFinalLimit'], 0, 3));
    $t->true(in_array('plugin_search', $boundary['gainedAdmittedLabels'], true));
    $t->true(in_array('rewrite_rules', $boundary['lostAdmittedLabels'], true));
    $t->same([
        'seed:2:3',
        'seed:2:3:4',
        'seed:2:3:4',
        'seed:2:3:4:5',
        'seed:2:3:4:5',
        'seed:2:3:4:5:6',
        'seed:2:3:4:5:6',
        'seed:2:3:4:5:6:7',
        'seed:2:3:4:5:6:7',
        'seed:2:3:4:5:6:7:8',
        'seed:2:3:4:5:6:7:8',
    ], $boundary['currentRecursivePreLimitLabels']);
};

$tests['compound select window recursive limit expression-limit-boundary limit trace'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['limitTrace'];
    $t->same(17, $trace['current']['preLimitCount']);
    $t->same(21, $trace['next']['preLimitCount']);
    $t->same(6, $trace['current']['finalCount']);
    $t->same(6, $trace['next']['finalCount']);
    $t->same(6, $trace['current']['limit']);
    $t->same(2, $trace['current']['offset']);
    $t->same('siteurl', $trace['current']['firstFinalLabel']);
    $t->same('plugin_search', $trace['next']['lastFinalLabel']);
};

$tests['compound select window recursive limit expression-limit-boundary replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->true(in_array('recursive-limit-expression-current-source-expression-limit-boundary', $reasons, true));
    $t->true(in_array('compound-tail-limit-expression-current-source-expression-limit-boundary', $reasons, true));
    $t->true(in_array('window-values-before-compound-limit-expression-expression-limit-boundary', $reasons, true));
    $t->true(in_array('application-option-source-boundary-shifts-expression-limit-expression-limit-boundary', $reasons, true));
};

$tests['compound select window recursive limit expression-limit-boundary rejects literal recursive limit'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareExpressionLimitBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 130) UNION ALL SELECT id + 1, label, score - 7 FROM q WHERE id < 9 LIMIT 6 OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(score, 1, score) OVER (ORDER BY score DESC) FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric DESC, id LIMIT (2 * 3) OFFSET (1 + 1)",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound select window recursive limit expression-limit-boundary rejects literal final limit'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareExpressionLimitBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 130) UNION ALL SELECT id + 1, label, score - 7 FROM q WHERE id < 9 LIMIT (2 + 4) OFFSET (1 + 1)) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(score, 1, score) OVER (ORDER BY score DESC) FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric DESC, id LIMIT 6 OFFSET 2",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit expression-limit-boundary generated expression limit ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimitRight = 3 + ($case % 3);
        $finalLimitRight = 3 + ($case % 4);
        $scoreFloor = 70 + ($case % 12);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 120 + $case],
                ['option_id' => 2, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 104 + $case],
                ['option_id' => 3, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 88 + $case],
                ['option_id' => 4, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => $scoreFloor],
                ['option_id' => 5, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 18 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 7 FROM q WHERE id < 10 LIMIT (2 + {$recursiveLimitRight}) OFFSET (1 + 1)) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, lead(score, 1, score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' UNION ALL SELECT id, label, first_value(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, score AS metric FROM wp_options WHERE score >= {$scoreFloor} ORDER BY metric DESC, id LIMIT (1 + {$finalLimitRight}) OFFSET (1 + 1)";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareExpressionLimitBoundary($generatedSql, $tables, $tables);

        $t->same(1 + $finalLimitRight, count($rows));
        $t->same(2 + $recursiveLimitRight, count($plan['recursive']['currentRows']));
        $t->same(['seed_' . $case, 'seed_' . $case . ':2'], $plan['recursive']['currentSkippedLabels']);
        $t->same(0, $plan['recursive']['currentFinalLimitRemaining']);
        $t->true($rows[0]['metric'] >= $rows[count($rows) - 1]['metric']);
    };
}

return $tests;
