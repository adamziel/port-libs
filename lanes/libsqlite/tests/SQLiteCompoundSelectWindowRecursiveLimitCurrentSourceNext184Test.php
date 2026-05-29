<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions184 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 92],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 78],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 64],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptions184 = [
    ...$currentOptions184,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 88],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 70],
];
$currentTables184 = ['wp_options' => $currentOptions184];
$nextTables184 = ['wp_options' => $nextOptions184];

$sql184 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 110)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 2
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

$summary184 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext184($sql184, $currentTables184, $nextTables184);
$tests = [];

$tests['compound select window recursive limit current source next184 status dependencies'] = static function (TestRunner $t) use ($summary184): void {
    $plan = $summary184();
    $t->same('compound-select-window-recursive-limit-current-source-next184-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-exhaustion-next184',
        'sqlite-select-sql-window-before-union-distinct-next184',
        'sqlite-select-sql-compound-yield-source-boundary-next184',
        'sqlite-select-sql-final-limit-current-source-next184',
        'sqlite-current-source-next184',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next184 compound metadata'] = static function (TestRunner $t) use ($summary184): void {
    $compound = $summary184()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['hasUnionDistinct']);
};

$tests['compound select window recursive limit current source next184 current rows'] = static function (TestRunner $t) use ($summary184): void {
    $rows = $summary184()['currentRows'];
    $t->same(['seed:2:3', 'rewrite_rules', 'seed:2:3:4', 'seed:2:3:4:5', 'siteurl'], array_column($rows, 'label'));
    $t->same([92, 92, 92, 83, 78], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next184 next rows'] = static function (TestRunner $t) use ($summary184): void {
    $rows = $summary184()['nextRows'];
    $t->same(['seed:2:3', 'rewrite_rules', 'seed:2:3:4', 'siteurl', 'plugin_alpha'], array_column($rows, 'label'));
    $t->same([92, 92, 92, 88, 88], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next184 recursive pressure current'] = static function (TestRunner $t) use ($summary184): void {
    $pressure = $summary184()['recursiveLimitPressure']['current'];
    $t->same(7, $pressure['traceCount']);
    $t->same(0, $pressure['limitRemaining']);
    $t->same(0, $pressure['offsetRemaining']);
    $t->same(['seed', 'seed:2'], $pressure['skippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], $pressure['admittedRecursiveLabels']);
    $t->same(['seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $pressure['recursiveRowsDroppedByFinalLimit']);
};

$tests['compound select window recursive limit current source next184 recursive pressure next'] = static function (TestRunner $t) use ($summary184): void {
    $pressure = $summary184()['recursiveLimitPressure']['next'];
    $t->same(7, $pressure['traceCount']);
    $t->same(['seed', 'seed:2'], $pressure['skippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4'], $pressure['admittedRecursiveLabels']);
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $pressure['recursiveRowsDroppedByFinalLimit']);
    $t->same('table', $pressure['firstTapeSource']);
    $t->same('table', $pressure['lastTapeSource']);
};

$tests['compound select window recursive limit current source next184 source boundary'] = static function (TestRunner $t) use ($summary184): void {
    $shift = $summary184()['recursiveLimitPressure']['sourceBoundaryShift'];
    $t->same(['table', 'recursive', 'table', 'recursive'], $shift['currentFirstSources']);
    $t->same(['table', 'recursive', 'table', 'recursive'], $shift['nextFirstSources']);
    $t->same(5, $shift['currentRecursiveCount']);
    $t->same(5, $shift['nextRecursiveCount']);
    $t->same(5, $shift['currentTableCount']);
    $t->same(9, $shift['nextTableCount']);
};

$tests['compound select window recursive limit current source next184 yield tape and limit trace'] = static function (TestRunner $t) use ($summary184): void {
    $plan = $summary184();
    $t->same(10, count($plan['yieldTape']['current']));
    $t->same(14, count($plan['yieldTape']['next']));
    $t->same(['siteurl'], array_column($plan['limitTrace']['current']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl'], array_column($plan['limitTrace']['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], array_values(array_filter(array_column($plan['limitTrace']['current']['truncatedAfterLimit'], 'label'), static fn (string $label): bool => str_starts_with($label, 'seed'))));
};

$tests['compound select window recursive limit current source next184 changed recursive labels and reasons'] = static function (TestRunner $t) use ($summary184): void {
    $plan = $summary184();
    $t->same(['seed:2:3:4:5'], $plan['recursiveLimitPressure']['changedAdmittedRecursiveLabels']);
    $t->true(in_array('recursive-limit-exhausted-before-compound-yield', $plan['replanReasons'], true));
    $t->true(in_array('admitted-recursive-labels-changed-by-final-limit', $plan['replanReasons'], true));
    $t->true(in_array('recursive-offset-skipped-anchor-before-limit', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next184 rejects non exhausted recursive limit'] = static function (TestRunner $t) use ($currentTables184): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext184(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 110) UNION ALL SELECT id + 1, label, score - 9 FROM q WHERE id < 3 LIMIT 8 OFFSET 1) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(score, 1, score) OVER (ORDER BY score DESC) FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric DESC LIMIT 3 OFFSET 1",
        $currentTables184,
        $currentTables184,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit current source next184 generated exhausted source tape ' . $case] = static function (TestRunner $t) use ($case): void {
        $limit = 4 + ($case % 3);
        $finalLimit = 3 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 110 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => 75 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 20 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (130 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 8 FROM q WHERE id < 8 LIMIT {$limit} OFFSET 2) SELECT id, label, lag(score, 1, score) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, lead(score, 1, score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' UNION SELECT option_id AS id, option_name AS label, score AS metric FROM wp_options WHERE score >= " . (75 + $case) . " ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext184($generatedSql, $tables, $tables);
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(0, $plan['recursiveLimitPressure']['current']['limitRemaining']);
        $t->same(0, $plan['recursiveLimitPressure']['current']['offsetRemaining']);
        $t->true($plan['recursiveLimitPressure']['current']['traceCount'] >= $limit);
        $t->same(['seed_' . $case, 'seed_' . $case . ':2'], $plan['recursiveLimitPressure']['current']['skippedLabels']);
        $t->true(isset($plan['recursiveLimitPressure']['current']['tapeRecursiveLabels'][0]));
    };
}

return $tests;
