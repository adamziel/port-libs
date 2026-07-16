<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions161 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 12],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 10],
    ['option_id' => 3, 'option_name' => 'skip_seed_3', 'autoload' => 'no', 'weight' => 99],
];
$nextOptions161 = [
    ...$currentOptions161,
    ['option_id' => 4, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 18],
    ['option_id' => 5, 'option_name' => 'skip_cache', 'autoload' => 'no', 'weight' => 88],
];
$currentTables161 = ['wp_options' => $currentOptions161];
$nextTables161 = ['wp_options' => $nextOptions161];

$sql161 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 20)
    UNION ALL
    SELECT id + 1, 'seed_' || (id + 1), score - 2
      FROM q
     WHERE id < 6
     LIMIT 5
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS win
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY weight DESC, option_id) AS win
  FROM wp_options
EXCEPT
SELECT option_id,
       option_name,
       1
  FROM wp_options
 WHERE option_name LIKE 'skip_%'
 ORDER BY win, id
 LIMIT 5 OFFSET 1
SQL;

$summary161 = static fn (): array => SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveExceptWindowLimit($sql161, $currentTables161, $nextTables161);
$tests = [];

$tests['compound except window recursive limit next161 status dependencies'] = static function (TestRunner $t) use ($summary161): void {
    $plan = $summary161();
    $t->same('compound-except-window-recursive-limit-current-source-next161-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-before-compound-except-next161',
        'sqlite-window-arm-before-except-next161',
        'sqlite-compound-except-final-limit-yield-next161',
    ], $plan['dependencies']);
    $t->true(str_contains($plan['dependency_closure'], 'no new support component needed'));
};

$tests['compound except window recursive limit next161 compound metadata'] = static function (TestRunner $t) use ($summary161): void {
    $compound = $summary161()['compound'];
    $t->same(['UNION ALL', 'EXCEPT'], $compound['operators']);
    $t->same(3, $compound['armCount']);
    $t->same(['win', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->same(2, $compound['exceptArmIndex']);
};

$tests['compound except window recursive limit next161 current rows'] = static function (TestRunner $t) use ($summary161): void {
    $rows = $summary161()['currentRows'];
    $t->same([1, 2, 2, 3, 4], array_column($rows, 'id'));
    $t->same(['siteurl', 'seed_2', 'home', 'seed_3', 'seed_4'], array_column($rows, 'label'));
    $t->same([2, 2, 3, 3, 4], array_column($rows, 'win'));
};

$tests['compound except window recursive limit next161 next rows'] = static function (TestRunner $t) use ($summary161): void {
    $rows = $summary161()['nextRows'];
    $t->same([2, 5, 3, 4, 1], array_column($rows, 'id'));
    $t->same(['seed_2', 'skip_cache', 'seed_3', 'plugin_alpha', 'siteurl'], array_column($rows, 'label'));
    $t->same([2, 2, 3, 3, 4], array_column($rows, 'win'));
};

$tests['compound except window recursive limit next161 prelimit rows show except before final limit'] = static function (TestRunner $t) use ($summary161): void {
    $plan = $summary161();
    $t->same(['seed', 'siteurl', 'seed_2', 'home', 'seed_3', 'seed_4', 'seed_5'], array_column($plan['currentPreLimitRows'], 'label'));
    $t->same(['seed', 'seed_2', 'skip_cache', 'seed_3', 'plugin_alpha', 'siteurl', 'seed_4', 'home', 'seed_5'], array_column($plan['nextPreLimitRows'], 'label'));
};

$tests['compound except window recursive limit next161 recursive queue metadata'] = static function (TestRunner $t) use ($summary161): void {
    $recursive = $summary161()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same([1, 2, 3, 4, 5], array_column($recursive['currentRows'], 'id'));
    $t->same(5, $recursive['currentTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound except window recursive limit next161 window metadata'] = static function (TestRunner $t) use ($summary161): void {
    $windows = $summary161()['windows'];
    $t->same(['row_number', 'dense_rank'], $windows['functions']);
    $t->same(['win', 'win'], array_column($windows['current'], 'alias'));
    $t->same([0, 0], array_column($windows['current'], 'argumentCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound except window recursive limit next161 except diagnostics'] = static function (TestRunner $t) use ($summary161): void {
    $except = $summary161()['except'];
    $t->same(['seed', 'seed_5'], $except['currentExcludedLabels']);
    $t->same(['seed', 'seed_4', 'home', 'seed_5'], $except['nextExcludedLabels']);
    $t->same(['seed_4', 'home'], $except['changedExcludedLabels']);
    $t->same(['skip_cache'], $except['survivingSkipLabels']);
};

$tests['compound except window recursive limit next161 yield boundary'] = static function (TestRunner $t) use ($summary161): void {
    $boundary = $summary161()['yieldBoundary'];
    $t->same(1, $boundary['current']['offset']);
    $t->same(5, $boundary['current']['limit']);
    $t->same(7, $boundary['current']['preLimitCount']);
    $t->same(['seed'], array_column($boundary['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed_5'], array_column($boundary['current']['truncatedAfterLimit'], 'label'));
    $t->same(9, $boundary['next']['preLimitCount']);
    $t->same(['seed'], array_column($boundary['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed_4', 'home', 'seed_5'], array_column($boundary['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound except window recursive limit next161 current next boundary delta'] = static function (TestRunner $t) use ($summary161): void {
    $boundary = $summary161()['boundary'];
    $t->same('siteurl', $boundary['currentFirst']['label']);
    $t->same('seed_2', $boundary['nextFirst']['label']);
    $t->same('seed_4', $boundary['currentLast']['label']);
    $t->same('siteurl', $boundary['nextLast']['label']);
    $t->same(['skip_cache', 'plugin_alpha'], $boundary['gainedLabels']);
    $t->same(['home', 'seed_4'], $boundary['lostLabels']);
};

$tests['compound except window recursive limit next161 changed signatures and reasons'] = static function (TestRunner $t) use ($summary161): void {
    $plan = $summary161();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"skip_cache"', $changed);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->true(in_array('recursive-window-before-compound-except', $plan['replanReasons'], true));
    $t->true(in_array('compound-except-before-final-limit', $plan['replanReasons'], true));
    $t->true(in_array('limited-compound-except-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-except-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-exhausted-before-except', $plan['replanReasons'], true));
};

$tests['compound except window recursive limit next161 rejects missing recursive'] = static function (TestRunner $t) use ($currentTables161): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveExceptWindowLimit(
        "SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY weight) AS win FROM wp_options EXCEPT SELECT option_id, option_name, 1 FROM wp_options WHERE option_name LIKE 'skip_%' ORDER BY win LIMIT 2 OFFSET 1",
        $currentTables161,
        $currentTables161,
    ));
};

$tests['compound except window recursive limit next161 rejects missing except'] = static function (TestRunner $t) use ($currentTables161): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveExceptWindowLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 20) UNION ALL SELECT id + 1, label, score - 2 FROM q WHERE id < 3 LIMIT 2) SELECT id, label, row_number() OVER (ORDER BY score) AS win FROM q UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY weight) AS win FROM wp_options ORDER BY win LIMIT 2 OFFSET 1",
        $currentTables161,
        $currentTables161,
    ));
};

$tests['compound except window recursive limit next161 rejects missing final offset'] = static function (TestRunner $t) use ($currentTables161): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveExceptWindowLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 20) UNION ALL SELECT id + 1, label, score - 2 FROM q WHERE id < 3 LIMIT 2) SELECT id, label, row_number() OVER (ORDER BY score) AS win FROM q EXCEPT SELECT option_id, option_name, 1 FROM wp_options WHERE option_name LIKE 'skip_%' ORDER BY win LIMIT 2",
        $currentTables161,
        $currentTables161,
    ));
};

foreach (range(1, 48) as $case) {
    $tests['compound except window recursive limit next161 generated boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 4 + ($case % 4);
        $finalLimit = 3 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 20, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 60 + $case],
                ['option_id' => 21, 'option_name' => 'skip_cache_' . $case, 'autoload' => 'no', 'weight' => 58 + $case],
                ['option_id' => 22, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 56 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', " . (20 + $case) . ") UNION ALL SELECT id + 1, 'seed_' || (id + 1), score - 2 FROM q WHERE id < 9 LIMIT {$recursiveLimit}) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS win FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (ORDER BY weight DESC, option_id) AS win FROM wp_options EXCEPT SELECT option_id, option_name, 1 FROM wp_options WHERE option_name LIKE 'skip_%' ORDER BY win, id LIMIT {$finalLimit} OFFSET 1";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['win']));
        $t->true($rows[0]['win'] <= $rows[count($rows) - 1]['win']);
        $t->true($rows[0]['label'] !== '');
    };
}

return $tests;
