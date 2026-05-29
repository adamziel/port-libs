<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions170 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 40],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 35],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 26],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 31],
];
$nextOptions170 = [
    ...$currentOptions170,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 44],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 24],
];
$currentTables170 = ['wp_options' => $currentOptions170];
$nextTables170 = ['wp_options' => $nextOptions170];

$sql170 = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 42)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 4
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 2
)
SELECT id,
       label,
       dense_rank() OVER (ORDER BY weight DESC) AS bucket
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY weight DESC, option_id) AS bucket
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       2 AS bucket
  FROM wp_options
 WHERE option_name IN ('home', 'rewrite_rules')
 ORDER BY bucket, id
 LIMIT 5 OFFSET 1
SQL;

$summary170 = static fn (): array => SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan::compareNext170($sql170, $currentTables170, $nextTables170);
$tests = [];

$tests['compound select window recursive limit current source next170 status dependencies'] = static function (TestRunner $t) use ($summary170): void {
    $plan = $summary170();
    $t->same('compound-except-window-recursive-limit-current-source-next170-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-offset-exhaustion-next170',
        'sqlite-select-sql-window-before-except-next170',
        'sqlite-select-sql-compound-except-tail-next170',
        'sqlite-select-sql-compound-final-limit-next170',
        'sqlite-current-source-next170',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next170 compound metadata'] = static function (TestRunner $t) use ($summary170): void {
    $compound = $summary170()['compound'];
    $t->same(['UNION ALL', 'EXCEPT'], $compound['operators']);
    $t->same([2], $compound['exceptArmIndexes']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['bucket', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound select window recursive limit current source next170 current rows'] = static function (TestRunner $t) use ($summary170): void {
    $rows = $summary170()['currentRows'];
    $t->same([3, 4, 3, 5, 6], array_column($rows, 'id'));
    $t->same(['seed:2:3', 'seed:2:3:4', 'rewrite_rules', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], array_column($rows, 'label'));
    $t->same([1, 2, 3, 3, 4], array_column($rows, 'bucket'));
};

$tests['compound select window recursive limit current source next170 next rows'] = static function (TestRunner $t) use ($summary170): void {
    $rows = $summary170()['nextRows'];
    $t->same([5, 1, 4, 2, 5], array_column($rows, 'id'));
    $t->same(['plugin_alpha', 'siteurl', 'seed:2:3:4', 'home', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3, 3], array_column($rows, 'bucket'));
};

$tests['compound select window recursive limit current source next170 recursive offset trace'] = static function (TestRunner $t) use ($summary170): void {
    $recursive = $summary170()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed', 'seed:2'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $recursive['currentEmittedLabels']);
    $t->same(7, $recursive['currentTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['currentOffsetRemaining']);
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound select window recursive limit current source next170 window metadata'] = static function (TestRunner $t) use ($summary170): void {
    $windows = $summary170()['windows']['current'];
    $t->same(['dense_rank', 'row_number'], array_column($windows, 'function'));
    $t->same(['bucket', 'bucket'], array_column($windows, 'alias'));
    $t->same([0, 1], array_column($windows, 'partitionCount'));
    $t->same([1, 2], array_column($windows, 'orderCount'));
};

$tests['compound select window recursive limit current source next170 except trace'] = static function (TestRunner $t) use ($summary170): void {
    $trace = $summary170()['exceptTrace'];
    $t->same(['siteurl', 'seed:2:3', 'seed:2:3:4', 'rewrite_rules', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $trace['currentPreLimitLabels']);
    $t->same(['seed:2:3', 'plugin_alpha', 'siteurl', 'seed:2:3:4', 'home', 'seed:2:3:4:5', 'rewrite_rules', 'seed:2:3:4:5:6', 'theme_mods', 'seed:2:3:4:5:6:7'], $trace['nextPreLimitLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'rewrite_rules', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $trace['currentAdmittedLabels']);
    $t->same(['plugin_alpha', 'siteurl', 'seed:2:3:4', 'home', 'seed:2:3:4:5'], $trace['nextAdmittedLabels']);
    $t->true(in_array('rewrite_rules', $trace['removedBeforeLimit'], true));
};

$tests['compound select window recursive limit current source next170 limit trace'] = static function (TestRunner $t) use ($summary170): void {
    $trace = $summary170()['limitTrace'];
    $t->same(7, $trace['current']['preLimitCount']);
    $t->same(10, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6:7'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['rewrite_rules', 'seed:2:3:4:5:6', 'theme_mods', 'seed:2:3:4:5:6:7'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit current source next170 boundary and reasons'] = static function (TestRunner $t) use ($summary170): void {
    $plan = $summary170();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->contains('"label":"seed:2:3:4:5:6"', $changed);
    $t->same('seed:2:3', $plan['boundary']['currentFirst']['label']);
    $t->same('plugin_alpha', $plan['boundary']['nextFirst']['label']);
    $t->same('seed:2:3:4:5:6', $plan['boundary']['currentLast']['label']);
    $t->same('seed:2:3:4:5', $plan['boundary']['nextLast']['label']);
    $t->true(in_array('limited-except-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-except-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-offset-skipped-anchor', $plan['replanReasons'], true));
    $t->true(in_array('window-before-except-compound-tail', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next170 rejects missing except'] = static function (TestRunner $t) use ($currentTables170): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan::compareNext170(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 42) UNION ALL SELECT id + 1, label, weight - 4 FROM q WHERE id < 8 LIMIT 5 OFFSET 2) SELECT id, label, dense_rank() OVER (ORDER BY weight DESC) AS bucket FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight DESC) FROM wp_options ORDER BY bucket LIMIT 2 OFFSET 1",
        $currentTables170,
        $currentTables170,
    ));
};

$tests['compound select window recursive limit current source next170 rejects missing final limit'] = static function (TestRunner $t) use ($currentTables170): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan::compareNext170(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 42) UNION ALL SELECT id + 1, label, weight - 4 FROM q WHERE id < 8 LIMIT 5 OFFSET 2) SELECT id, label, dense_rank() OVER (ORDER BY weight DESC) AS bucket FROM q EXCEPT SELECT option_id, option_name, 2 FROM wp_options ORDER BY bucket",
        $currentTables170,
        $currentTables170,
    ));
};

$tests['compound select window recursive limit current source next170 rejects missing window'] = static function (TestRunner $t) use ($currentTables170): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan::compareNext170(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 42) UNION ALL SELECT id + 1, label, weight - 4 FROM q WHERE id < 8 LIMIT 5 OFFSET 2) SELECT id, label, weight AS bucket FROM q EXCEPT SELECT option_id, option_name, 2 FROM wp_options ORDER BY bucket LIMIT 2 OFFSET 1",
        $currentTables170,
        $currentTables170,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit current source next170 generated except boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $cteLimit = 4 + ($case % 3);
        $finalLimit = 3 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 10, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 50 + $case],
                ['option_id' => 11, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 42 + $case],
                ['option_id' => 12, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'weight' => 35 + $case],
                ['option_id' => 13, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'weight' => 60 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed_{$case}', " . (48 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 3 FROM q WHERE id < 9 LIMIT {$cteLimit} OFFSET 2) SELECT id, label, dense_rank() OVER (ORDER BY weight DESC) AS bucket FROM q UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY weight DESC, option_id) AS bucket FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, 2 AS bucket FROM wp_options WHERE option_name = 'home_{$case}' ORDER BY bucket, id LIMIT {$finalLimit} OFFSET 1";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['bucket']));
        $t->same(false, in_array('home_' . $case, array_column($rows, 'label'), true));
        $t->true($rows[0]['bucket'] <= $rows[count($rows) - 1]['bucket']);
    };
}

return $tests;
