<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 28],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 24],
    ['option_id' => 4, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 18],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 36],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 17],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 34)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 4
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       lead(weight, 1, weight) OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       lead(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       weight AS metric
  FROM wp_options
 WHERE option_name = 'theme_mods'
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$summary = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareExceptLeadLimitOffset($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound select window recursive limit except-lead-limit-offset status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-window-recursive-limit-current-source-except-lead-limit-offset-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-offset-except-lead-limit-offset',
        'sqlite-select-sql-compound-except-window-except-lead-limit-offset',
        'sqlite-select-sql-compound-tail-limit-except-lead-limit-offset',
        'sqlite-current-source-except-lead-limit-offset',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit except-lead-limit-offset compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL', 'EXCEPT'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['hasExcept']);
};

$tests['compound select window recursive limit except-lead-limit-offset current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([7, 1, 2, 3, 2, 4], array_column($rows, 'id'));
    $t->same(['seed:2:3:4:5:6:7', 'siteurl', 'seed:2', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([30, 28, 26, 22, 18, 18], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit except-lead-limit-offset next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([5, 7, 1, 2, 3, 2], array_column($rows, 'id'));
    $t->same(['plugin_alpha', 'seed:2:3:4:5:6:7', 'siteurl', 'seed:2', 'seed:2:3', 'home'], array_column($rows, 'label'));
    $t->same([30, 30, 28, 26, 22, 18], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit except-lead-limit-offset prelimit rows'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['rewrite_rules', 'seed:2:3:4:5:6:7', 'siteurl'], array_slice(array_column($plan['currentPreLimitRows'], 'label'), 0, 3));
    $t->same(['theme_mods', 'plugin_alpha', 'seed:2:3:4:5:6:7'], array_slice(array_column($plan['nextPreLimitRows'], 'label'), 0, 3));
    $t->same(true, in_array('theme_mods', array_column($plan['nextPreLimitRows'], 'label'), true));
};

$tests['compound select window recursive limit except-lead-limit-offset recursive trace'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit except-lead-limit-offset window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['lead'], $windows['functions']);
    $t->same(['lead', 'lead'], array_column($windows['current'], 'function'));
    $t->same(['metric', 'metric'], array_column($windows['current'], 'alias'));
    $t->same([3, 3], array_column($windows['current'], 'argumentCount'));
    $t->same([1, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit except-lead-limit-offset limit trace'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['limitTrace'];
    $t->same(9, $trace['current']['preLimitCount']);
    $t->same(11, $trace['next']['preLimitCount']);
    $t->same(['rewrite_rules'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['theme_mods'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['seed:2:3:4', 'rewrite_rules', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit except-lead-limit-offset source classes'] = static function (TestRunner $t) use ($summary): void {
    $classes = $summary()['sourceClasses'];
    $t->same(['recursive' => 4, 'table' => 2], $classes['current']);
    $t->same(['recursive' => 3, 'table' => 3], $classes['next']);
};

$tests['compound select window recursive limit except-lead-limit-offset boundary delta'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['boundary'];
    $t->same('seed:2:3:4:5:6:7', $boundary['currentFirst']['label']);
    $t->same('plugin_alpha', $boundary['nextFirst']['label']);
    $t->same('seed:2:3:4', $boundary['currentLast']['label']);
    $t->same('home', $boundary['nextLast']['label']);
    $t->contains('"label":"plugin_alpha"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"seed:2:3:4"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit except-lead-limit-offset changed signatures reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->contains('"label":"seed:2:3:4"', $changed);
    $t->true(in_array('limited-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-skipped-anchor', $plan['replanReasons'], true));
    $t->true(in_array('window-values-before-except', $plan['replanReasons'], true));
    $t->true(in_array('compound-except-after-window', $plan['replanReasons'], true));
    $t->true(in_array('compound-tail-limit-offset', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit except-lead-limit-offset rejects missing except'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareExceptLeadLimitOffset(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 34) UNION ALL SELECT id + 1, label, weight - 4 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, lead(weight, 1, weight) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(weight, 1, weight) OVER (ORDER BY weight DESC) FROM wp_options ORDER BY metric LIMIT 2 OFFSET 1",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound select window recursive limit except-lead-limit-offset rejects missing lead'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareExceptLeadLimitOffset(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 34) UNION ALL SELECT id + 1, label, weight - 4 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight DESC) FROM wp_options EXCEPT SELECT option_id, option_name, weight FROM wp_options ORDER BY metric LIMIT 2 OFFSET 1",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound select window recursive limit except-lead-limit-offset rejects missing final offset'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareExceptLeadLimitOffset(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 34) UNION ALL SELECT id + 1, label, weight - 4 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, lead(weight, 1, weight) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, lead(weight, 1, weight) OVER (ORDER BY weight DESC) FROM wp_options EXCEPT SELECT option_id, option_name, weight FROM wp_options ORDER BY metric LIMIT 2",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound select window recursive limit except-lead-limit-offset generated except boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 4 + ($case % 4);
        $finalLimit = 3 + ($case % 4);
        $tables = [
            'wp_options' => [
                ['option_id' => 10, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 40 + $case],
                ['option_id' => 11, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 36 + $case],
                ['option_id' => 12, 'option_name' => 'theme_mods_' . $case, 'autoload' => 'yes', 'weight' => 18 + $case],
                ['option_id' => 13, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'weight' => 42 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed_{$case}', " . (34 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 4 FROM q WHERE id < 9 LIMIT {$recursiveLimit} OFFSET 1) SELECT id, label, lead(weight, 1, weight) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, lead(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, weight AS metric FROM wp_options WHERE option_name = 'theme_mods_{$case}' ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['metric']));
        $t->true($rows[0]['metric'] >= $rows[count($rows) - 1]['metric']);
        $t->same(false, in_array('theme_mods_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
