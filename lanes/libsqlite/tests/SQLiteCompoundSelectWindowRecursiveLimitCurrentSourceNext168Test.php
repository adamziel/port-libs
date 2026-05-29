<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions168 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 20],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 18],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 14],
];
$nextOptions168 = [
    ...$currentOptions168,
    ['option_id' => 4, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 28],
    ['option_id' => 5, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 17],
];
$currentTables168 = ['wp_options' => $currentOptions168];
$nextTables168 = ['wp_options' => $nextOptions168];

$sql168 = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 40)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 3
      FROM q
     WHERE id < 9
     LIMIT 1,5
)
SELECT id,
       label,
       sum(weight) OVER (
           ORDER BY id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       first_value(weight) OVER (
           PARTITION BY autoload
           ORDER BY weight DESC, option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS metric
  FROM wp_options
 ORDER BY metric DESC, id
 LIMIT 1,4
SQL;

$summary168 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext168($sql168, $currentTables168, $nextTables168);
$tests = [];

$tests['compound select window recursive limit next168 status dependencies'] = static function (TestRunner $t) use ($summary168): void {
    $plan = $summary168();
    $t->same('compound-select-window-recursive-limit-current-source-next168-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-comma-limit-next168',
        'sqlite-window-arm-before-compound-comma-limit-next168',
        'sqlite-compound-final-comma-limit-current-source-next168',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit next168 compound metadata'] = static function (TestRunner $t) use ($summary168): void {
    $compound = $summary168()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['armCount']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same(4, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['usesCommaLimit']);
};

$tests['compound select window recursive limit next168 current rows'] = static function (TestRunner $t) use ($summary168): void {
    $rows = $summary168()['currentRows'];
    $t->same([3, 4, 5, 6], array_column($rows, 'id'));
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], array_column($rows, 'label'));
    $t->same([65, 59, 53, 25], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit next168 next rows'] = static function (TestRunner $t) use ($summary168): void {
    $rows = $summary168()['nextRows'];
    $t->same([3, 4, 5, 4], array_column($rows, 'id'));
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'plugin_alpha'], array_column($rows, 'label'));
    $t->same([65, 59, 53, 28], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit next168 prelimit rows'] = static function (TestRunner $t) use ($summary168): void {
    $plan = $summary168();
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], array_slice(array_column($plan['currentPreLimitRows'], 'label'), 0, 4));
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'plugin_alpha'], array_slice(array_column($plan['nextPreLimitRows'], 'label'), 0, 5));
    $t->same(8, count($plan['currentPreLimitRows']));
    $t->same(10, count($plan['nextPreLimitRows']));
};

$tests['compound select window recursive limit next168 recursive comma trace'] = static function (TestRunner $t) use ($summary168): void {
    $recursive = $summary168()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['currentOffsetRemaining']);
};

$tests['compound select window recursive limit next168 window metadata'] = static function (TestRunner $t) use ($summary168): void {
    $windows = $summary168()['windows'];
    $t->same(['sum', 'first_value'], $windows['functions']);
    $t->same(['sum', 'first_value'], array_column($windows['current'], 'function'));
    $t->same(['metric', 'metric'], array_column($windows['current'], 'alias'));
    $t->same([1, 1], array_column($windows['current'], 'argumentCount'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit next168 limit trace'] = static function (TestRunner $t) use ($summary168): void {
    $trace = $summary168()['limitTrace'];
    $t->same(8, $trace['current']['preLimitCount']);
    $t->same(10, $trace['next']['preLimitCount']);
    $t->same(['seed:2'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl', 'home', 'active_plugins'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['seed:2:3:4:5:6', 'siteurl', 'home', 'theme_mods', 'active_plugins'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit next168 boundary changes'] = static function (TestRunner $t) use ($summary168): void {
    $boundary = $summary168()['boundary'];
    $t->same('seed:2:3', $boundary['currentFirst']['label']);
    $t->same('seed:2:3', $boundary['nextFirst']['label']);
    $t->same('seed:2:3:4:5:6', $boundary['currentLast']['label']);
    $t->same('plugin_alpha', $boundary['nextLast']['label']);
    $t->same(['plugin_alpha'], $boundary['gainedLabels']);
    $t->same(['seed:2:3:4:5:6'], $boundary['lostLabels']);
};

$tests['compound select window recursive limit next168 changed signatures reasons'] = static function (TestRunner $t) use ($summary168): void {
    $plan = $summary168();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->contains('"label":"seed:2:3:4:5:6"', $changed);
    $t->true(in_array('recursive-comma-limit-offset-skipped-anchor', $plan['replanReasons'], true));
    $t->true(in_array('window-values-before-compound-union-all', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-comma-limit-offset', $plan['replanReasons'], true));
    $t->true(in_array('recursive-anchor-skipped-by-comma-limit', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit next168 rejects offset form'] = static function (TestRunner $t) use ($currentTables168): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext168(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 40) UNION ALL SELECT id + 1, label, weight - 3 FROM q WHERE id < 9 LIMIT 5 OFFSET 1) SELECT id, label, sum(weight) OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, weight FROM wp_options ORDER BY metric LIMIT 4 OFFSET 1",
        $currentTables168,
        $currentTables168,
    ));
};

$tests['compound select window recursive limit next168 rejects missing window'] = static function (TestRunner $t) use ($currentTables168): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext168(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 40) UNION ALL SELECT id + 1, label, weight - 3 FROM q WHERE id < 9 LIMIT 1,5) SELECT id, label, weight AS metric FROM q UNION ALL SELECT option_id, option_name, weight FROM wp_options ORDER BY metric LIMIT 1,4",
        $currentTables168,
        $currentTables168,
    ));
};

$tests['compound select window recursive limit next168 rejects non compound'] = static function (TestRunner $t) use ($currentTables168): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext168(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 40) UNION ALL SELECT id + 1, label, weight - 3 FROM q WHERE id < 9 LIMIT 1,5) SELECT id, label, sum(weight) OVER (ORDER BY id) AS metric FROM q ORDER BY metric LIMIT 1,4",
        $currentTables168,
        $currentTables168,
    ));
};

foreach (range(1, 53) as $case) {
    $tests['compound select window recursive limit next168 generated comma boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveCount = 3 + ($case % 4);
        $finalCount = 2 + ($case % 4);
        $tables = [
            'wp_options' => [
                ['option_id' => 10, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 40 + $case],
                ['option_id' => 11, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 38 + $case],
                ['option_id' => 12, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'weight' => 22 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed_{$case}', " . (30 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 2 FROM q WHERE id < 9 LIMIT 1,{$recursiveCount}) SELECT id, label, sum(weight) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, first_value(weight) OVER (PARTITION BY autoload ORDER BY weight DESC, option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS metric FROM wp_options ORDER BY metric DESC, id LIMIT 1,{$finalCount}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalCount, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['metric']));
        $t->true($rows[0]['metric'] >= $rows[count($rows) - 1]['metric']);
        $t->same(false, in_array('seed_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
