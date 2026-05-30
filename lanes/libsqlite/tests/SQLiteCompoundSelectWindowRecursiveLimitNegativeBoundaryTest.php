<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions187 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 75],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 64],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptions187 = [
    ...$currentOptions187,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 88],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 70],
];
$currentTables187 = ['wp_options' => $currentOptions187];
$nextTables187 = ['wp_options' => $nextOptions187];

$sql187 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 110)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 6
     LIMIT -1 OFFSET 2
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
 WHERE score >= 70
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summary187 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNegativeRecursiveLimitBoundary($sql187, $currentTables187, $nextTables187);
$tests = [];

$tests['compound select window recursive limit current source next187 status dependencies'] = static function (TestRunner $t) use ($summary187): void {
    $plan = $summary187();
    $t->same('compound-select-window-recursive-limit-current-source-next187-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-negative-limit-offset-next187',
        'sqlite-select-sql-window-before-union-distinct-next187',
        'sqlite-select-sql-compound-final-limit-current-source-next187',
        'sqlite-current-source-next187',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next187 compound metadata'] = static function (TestRunner $t) use ($summary187): void {
    $compound = $summary187()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['hasUnionDistinct']);
};

$tests['compound select window recursive limit current source next187 current rows'] = static function (TestRunner $t) use ($summary187): void {
    $rows = $summary187()['currentRows'];
    $t->same(['seed:2:3:4', 'siteurl', 'rewrite_rules', 'seed:2:3:4:5', 'siteurl'], array_column($rows, 'label'));
    $t->same([92, 90, 90, 83, 75], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next187 next rows'] = static function (TestRunner $t) use ($summary187): void {
    $rows = $summary187()['nextRows'];
    $t->same(['seed:2:3:4', 'siteurl', 'rewrite_rules', 'siteurl', 'plugin_alpha'], array_column($rows, 'label'));
    $t->same([92, 90, 90, 88, 88], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next187 recursive negative limit current'] = static function (TestRunner $t) use ($summary187): void {
    $negative = $summary187()['negativeLimitOffset']['current'];
    $t->same(6, $negative['traceCount']);
    $t->same(null, $negative['limitRemaining']);
    $t->same(0, $negative['offsetRemaining']);
    $t->same(['seed', 'seed:2'], $negative['skippedLabels']);
    $t->same(['seed:2:3:4', 'seed:2:3:4:5'], $negative['admittedRecursiveLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4:5:6'], $negative['recursiveRowsDroppedByFinalLimit']);
};

$tests['compound select window recursive limit current source next187 recursive negative limit next'] = static function (TestRunner $t) use ($summary187): void {
    $negative = $summary187()['negativeLimitOffset']['next'];
    $t->same(6, $negative['traceCount']);
    $t->same(null, $negative['limitRemaining']);
    $t->same(['seed', 'seed:2'], $negative['skippedLabels']);
    $t->same(['seed:2:3:4'], $negative['admittedRecursiveLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $negative['recursiveRowsDroppedByFinalLimit']);
    $t->same(4, $negative['preLimitRecursiveCount']);
    $t->same(1, $negative['finalRecursiveCount']);
};

$tests['compound select window recursive limit current source next187 yield tape'] = static function (TestRunner $t) use ($summary187): void {
    $plan = $summary187();
    $t->same(9, count($plan['yieldTape']['current']));
    $t->same(13, count($plan['yieldTape']['next']));
    $t->same(['recursive', 'recursive', 'table', 'table'], array_column(array_slice($plan['yieldTape']['current'], 0, 4), 'source'));
    $t->same(['recursive', 'recursive', 'table', 'table'], array_column(array_slice($plan['yieldTape']['next'], 0, 4), 'source'));
};

$tests['compound select window recursive limit current source next187 final limit trace'] = static function (TestRunner $t) use ($summary187): void {
    $trace = $summary187()['limitTrace'];
    $t->same(9, $trace['current']['preLimitCount']);
    $t->same(13, $trace['next']['preLimitCount']);
    $t->same(5, $trace['current']['finalCount']);
    $t->same('seed:2:3:4', $trace['current']['firstFinalLabel']);
    $t->same('plugin_alpha', $trace['next']['lastFinalLabel']);
};

$tests['compound select window recursive limit current source next187 changed rows and reasons'] = static function (TestRunner $t) use ($summary187): void {
    $plan = $summary187();
    $t->same(['seed:2:3:4:5', 'siteurl', 'plugin_alpha'], $plan['negativeLimitOffset']['changedAdmittedLabels']);
    $t->same(['seed:2:3:4:5'], $plan['negativeLimitOffset']['changedRecursiveLabels']);
    $t->true(in_array('recursive-negative-limit-offset-drains-queue', $plan['replanReasons'], true));
    $t->true(in_array('recursive-offset-skipped-anchor-with-unbounded-limit', $plan['replanReasons'], true));
    $t->true(in_array('current-next-final-limit-boundary-shifted', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next187 rejects bounded recursive limit'] = static function (TestRunner $t) use ($currentTables187): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNegativeRecursiveLimitBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 110) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 6 LIMIT 4 OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(score, 1, score) OVER (ORDER BY score DESC) FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric DESC LIMIT 3 OFFSET 1",
        $currentTables187,
        $currentTables187,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound select window recursive limit current source next187 generated negative limit offset ' . $case] = static function (TestRunner $t) use ($case): void {
        $depth = 5 + ($case % 4);
        $finalLimit = 3 + ($case % 3);
        $threshold = 70 + $case;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 110 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => $threshold],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 20 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (130 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 8 FROM q WHERE id < {$depth} LIMIT -1 OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, lead(score, 1, score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' UNION SELECT option_id AS id, option_name AS label, score AS metric FROM wp_options WHERE score >= {$threshold} ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNegativeRecursiveLimitBoundary($generatedSql, $tables, $tables);
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(null, $plan['negativeLimitOffset']['current']['limitRemaining']);
        $t->same(0, $plan['negativeLimitOffset']['current']['offsetRemaining']);
        $t->same(['seed_' . $case, 'seed_' . $case . ':2'], $plan['negativeLimitOffset']['current']['skippedLabels']);
        $t->true($plan['negativeLimitOffset']['current']['traceCount'] >= $depth);
        $t->true($plan['negativeLimitOffset']['current']['preLimitRecursiveCount'] >= $plan['negativeLimitOffset']['current']['finalRecursiveCount']);
    };
}

return $tests;
