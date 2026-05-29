<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptionsnamedWindowRecursiveLimitOffset = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 32],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 24],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 18],
];
$nextOptionsnamedWindowRecursiveLimitOffset = [
    ...$currentOptionsnamedWindowRecursiveLimitOffset,
    ['option_id' => 4, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 34],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 21],
];
$currentTablesnamedWindowRecursiveLimitOffset = ['wp_options' => $currentOptionsnamedWindowRecursiveLimitOffset];
$nextTablesnamedWindowRecursiveLimitOffset = ['wp_options' => $nextOptionsnamedWindowRecursiveLimitOffset];

$sqlnamedWindowRecursiveLimitOffset = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 31)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 3
      FROM q
     WHERE id < 7
     LIMIT 4 OFFSET 1
)
SELECT id,
       label,
       dense_rank() OVER qwin AS bucket
  FROM q
 WINDOW qwin AS (ORDER BY weight DESC)
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER optwin AS bucket
  FROM wp_options
 WHERE autoload = 'yes'
 WINDOW optwin AS (PARTITION BY autoload ORDER BY weight DESC, option_id)
 ORDER BY bucket, id
 LIMIT 5 OFFSET 1
SQL;

$summarynamedWindowRecursiveLimitOffset = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNamedWindowRecursiveLimitOffset($sqlnamedWindowRecursiveLimitOffset, $currentTablesnamedWindowRecursiveLimitOffset, $nextTablesnamedWindowRecursiveLimitOffset);
$tests = [];

$tests['compound select window recursive limit named-window-recursive-limit-offset status dependencies'] = static function (TestRunner $t) use ($summarynamedWindowRecursiveLimitOffset): void {
    $plan = $summarynamedWindowRecursiveLimitOffset();
    $t->same('compound-select-window-recursive-limit-current-source-named-window-recursive-limit-offset-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-compound-named-window-recursive-limit-offset',
        'sqlite-select-sql-recursive-limit-offset-named-window-recursive-limit-offset',
        'sqlite-select-sql-compound-tail-limit-named-window-recursive-limit-offset',
        'sqlite-current-source-named-window-recursive-limit-offset',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit named-window-recursive-limit-offset compound metadata'] = static function (TestRunner $t) use ($summarynamedWindowRecursiveLimitOffset): void {
    $compound = $summarynamedWindowRecursiveLimitOffset()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['bucket', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound select window recursive limit named-window-recursive-limit-offset named windows'] = static function (TestRunner $t) use ($summarynamedWindowRecursiveLimitOffset): void {
    $plan = $summarynamedWindowRecursiveLimitOffset();
    $t->same(['qwin', 'optwin'], $plan['namedWindows']);
    $t->same(['dense_rank', 'row_number'], $plan['windows']['functions']);
    $t->same(['bucket', 'bucket'], array_column($plan['windows']['current'], 'alias'));
    $t->same([0, 1], array_column($plan['windows']['current'], 'partitionCount'));
    $t->same([1, 2], array_column($plan['windows']['current'], 'orderCount'));
};

$tests['compound select window recursive limit named-window-recursive-limit-offset current rows'] = static function (TestRunner $t) use ($summarynamedWindowRecursiveLimitOffset): void {
    $rows = $summarynamedWindowRecursiveLimitOffset()['currentRows'];
    $t->same([2, 2, 3, 4, 5], array_column($rows, 'id'));
    $t->same(['seed:2', 'home', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3, 4], array_column($rows, 'bucket'));
};

$tests['compound select window recursive limit named-window-recursive-limit-offset next rows'] = static function (TestRunner $t) use ($summarynamedWindowRecursiveLimitOffset): void {
    $rows = $summarynamedWindowRecursiveLimitOffset()['nextRows'];
    $t->same([4, 1, 3, 2, 4], array_column($rows, 'id'));
    $t->same(['plugin_alpha', 'siteurl', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3, 3], array_column($rows, 'bucket'));
};

$tests['compound select window recursive limit named-window-recursive-limit-offset prelimit boundary'] = static function (TestRunner $t) use ($summarynamedWindowRecursiveLimitOffset): void {
    $plan = $summarynamedWindowRecursiveLimitOffset();
    $t->same(['siteurl', 'seed:2', 'home', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], array_column($plan['currentPreLimitRows'], 'label'));
    $t->same(['seed:2', 'plugin_alpha', 'siteurl', 'seed:2:3', 'home', 'seed:2:3:4', 'seed:2:3:4:5', 'rewrite_rules'], array_column($plan['nextPreLimitRows'], 'label'));
};

$tests['compound select window recursive limit named-window-recursive-limit-offset recursive trace'] = static function (TestRunner $t) use ($summarynamedWindowRecursiveLimitOffset): void {
    $recursive = $summarynamedWindowRecursiveLimitOffset()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound select window recursive limit named-window-recursive-limit-offset limit trace'] = static function (TestRunner $t) use ($summarynamedWindowRecursiveLimitOffset): void {
    $trace = $summarynamedWindowRecursiveLimitOffset()['limitTrace'];
    $t->same(6, $trace['current']['preLimitCount']);
    $t->same(8, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same([], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['seed:2:3:4:5', 'rewrite_rules'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit named-window-recursive-limit-offset boundary delta'] = static function (TestRunner $t) use ($summarynamedWindowRecursiveLimitOffset): void {
    $boundary = $summarynamedWindowRecursiveLimitOffset()['boundary'];
    $t->same('seed:2', $boundary['currentFirst']['label']);
    $t->same('plugin_alpha', $boundary['nextFirst']['label']);
    $t->same('seed:2:3:4:5', $boundary['currentLast']['label']);
    $t->same('seed:2:3:4', $boundary['nextLast']['label']);
    $t->contains('"label":"plugin_alpha"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"seed:2"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit named-window-recursive-limit-offset changed signatures reasons'] = static function (TestRunner $t) use ($summarynamedWindowRecursiveLimitOffset): void {
    $plan = $summarynamedWindowRecursiveLimitOffset();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->contains('"label":"seed:2"', $changed);
    $t->true(in_array('compound-named-window-arm-expansion', $plan['replanReasons'], true));
    $t->true(in_array('limited-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-skipped-anchor', $plan['replanReasons'], true));
    $t->true(in_array('compound-tail-limit-offset', $plan['replanReasons'], true));
    $t->true(in_array('window-values-before-compound-tail-limit', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit named-window-recursive-limit-offset rejects missing named window'] = static function (TestRunner $t) use ($currentTablesnamedWindowRecursiveLimitOffset): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNamedWindowRecursiveLimitOffset(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 31) UNION ALL SELECT id + 1, label, weight - 3 FROM q WHERE id < 7 LIMIT 4 OFFSET 1) SELECT id, label, dense_rank() OVER (ORDER BY weight DESC) AS bucket FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight DESC) FROM wp_options ORDER BY bucket LIMIT 2 OFFSET 1",
        $currentTablesnamedWindowRecursiveLimitOffset,
        $currentTablesnamedWindowRecursiveLimitOffset,
    ));
};

$tests['compound select window recursive limit named-window-recursive-limit-offset rejects missing recursive'] = static function (TestRunner $t) use ($currentTablesnamedWindowRecursiveLimitOffset): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNamedWindowRecursiveLimitOffset(
        "SELECT option_id AS id, option_name AS label, row_number() OVER optwin AS bucket FROM wp_options WINDOW optwin AS (ORDER BY weight DESC) UNION ALL SELECT option_id, option_name, row_number() OVER optwin FROM wp_options WINDOW optwin AS (ORDER BY weight DESC) ORDER BY bucket LIMIT 2 OFFSET 1",
        $currentTablesnamedWindowRecursiveLimitOffset,
        $currentTablesnamedWindowRecursiveLimitOffset,
    ));
};

$tests['compound select window recursive limit named-window-recursive-limit-offset rejects missing row number'] = static function (TestRunner $t) use ($currentTablesnamedWindowRecursiveLimitOffset): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNamedWindowRecursiveLimitOffset(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 31) UNION ALL SELECT id + 1, label, weight - 3 FROM q WHERE id < 7 LIMIT 4 OFFSET 1) SELECT id, label, dense_rank() OVER qwin AS bucket FROM q WINDOW qwin AS (ORDER BY weight DESC) UNION ALL SELECT option_id, option_name, dense_rank() OVER optwin FROM wp_options WINDOW optwin AS (ORDER BY weight DESC) ORDER BY bucket LIMIT 2 OFFSET 1",
        $currentTablesnamedWindowRecursiveLimitOffset,
        $currentTablesnamedWindowRecursiveLimitOffset,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound select window recursive limit named-window-recursive-limit-offset generated named window boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 3 + ($case % 4);
        $finalLimit = 3 + ($case % 3);
        $finalOffset = 1 + ($case % 2);
        $tables = [
            'wp_options' => [
                ['option_id' => 10, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 40 + $case],
                ['option_id' => 11, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 33 + $case],
                ['option_id' => 12, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 29 + $case],
                ['option_id' => 13, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'weight' => 50 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed_{$case}', " . (35 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 2 FROM q WHERE id < 9 LIMIT {$recursiveLimit} OFFSET 1) SELECT id, label, dense_rank() OVER qwin AS bucket FROM q WINDOW qwin AS (ORDER BY weight DESC) UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER optwin AS bucket FROM wp_options WHERE autoload = 'yes' WINDOW optwin AS (PARTITION BY autoload ORDER BY weight DESC, option_id) ORDER BY bucket, id LIMIT {$finalLimit} OFFSET {$finalOffset}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(min($finalLimit, $recursiveLimit + 3 - $finalOffset), count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['bucket']));
        $t->true($rows[0]['bucket'] <= $rows[count($rows) - 1]['bucket']);
        $t->same(false, in_array('transient_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;
