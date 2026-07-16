<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions160 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 16],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 14],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 9],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 7],
];
$nextOptions160 = [
    ...$currentOptions160,
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 18],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 8],
];
$currentTables160 = ['wp_options' => $currentOptions160];
$nextTables160 = ['wp_options' => $nextOptions160];

$sql160 = <<<'SQL'
WITH RECURSIVE staged(id, label, weight) AS (
    VALUES (1, 'seed', 22)
    UNION ALL
    SELECT id + 1, 'seed:' || (id + 1), weight - 3
      FROM staged
     WHERE id < 7
     LIMIT 1, 4
)
SELECT id,
       label,
       row_number() OVER (ORDER BY id) AS rank
  FROM staged
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY weight DESC, option_id) AS rank
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY rank, id
 LIMIT 5 OFFSET 1
SQL;

$summary160 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCommaLimitWindow($sql160, $currentTables160, $nextTables160);
$tests = [];

$tests['compound select window recursive limit current source recursive-comma-limit-window status dependencies'] = static function (TestRunner $t) use ($summary160): void {
    $plan = $summary160();
    $t->same('compound-select-window-recursive-limit-current-source-recursive-comma-limit-window-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-cte-comma-limit-offset',
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-select-sql-compound-final-limit',
        'sqlite-current-source-recursive-comma-limit-window',
    ], $plan['dependencies']);
};

$tests['compound select window recursive limit current source recursive-comma-limit-window compound metadata'] = static function (TestRunner $t) use ($summary160): void {
    $compound = $summary160()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['rank', 'id'], $compound['orderColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound select window recursive limit current source recursive-comma-limit-window current rows'] = static function (TestRunner $t) use ($summary160): void {
    $rows = $summary160()['currentRows'];
    $t->same([2, 2, 3, 3, 4], array_column($rows, 'id'));
    $t->same(['seed:2', 'home', 'seed:3', 'blogname', 'seed:4'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3, 3], array_column($rows, 'rank'));
};

$tests['compound select window recursive limit current source recursive-comma-limit-window next rows'] = static function (TestRunner $t) use ($summary160): void {
    $rows = $summary160()['nextRows'];
    $t->same([5, 1, 3, 2, 4], array_column($rows, 'id'));
    $t->same(['rewrite_rules', 'siteurl', 'seed:3', 'home', 'seed:4'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3, 3], array_column($rows, 'rank'));
};

$tests['compound select window recursive limit current source recursive-comma-limit-window recursive comma limit trace'] = static function (TestRunner $t) use ($summary160): void {
    $recursive = $summary160()['recursive'];
    $t->same('staged', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:3', 'seed:4', 'seed:5'], $recursive['currentEmittedLabels']);
    $t->same(['seed:2', 'seed:3', 'seed:4', 'seed:5'], array_column($recursive['currentRows'], 'label'));
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit current source recursive-comma-limit-window window metadata'] = static function (TestRunner $t) use ($summary160): void {
    $windows = $summary160()['windows']['current'];
    $t->same(['row_number', 'row_number'], array_column($windows, 'function'));
    $t->same(['rank', 'rank'], array_column($windows, 'alias'));
    $t->same([0, 0], array_column($windows, 'partitionCount'));
    $t->same([1, 2], array_column($windows, 'orderCount'));
};

$tests['compound select window recursive limit current source recursive-comma-limit-window limit trace'] = static function (TestRunner $t) use ($summary160): void {
    $trace = $summary160()['limitTrace'];
    $t->same(7, $trace['current']['preLimitCount']);
    $t->same(9, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:5'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['blogname', 'seed:5', 'theme_mods'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit current source recursive-comma-limit-window changed signatures reasons'] = static function (TestRunner $t) use ($summary160): void {
    $plan = $summary160();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"rewrite_rules"', $changed);
    $t->contains('"label":"siteurl"', $changed);
    $t->true(in_array('limited-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-comma-limit-offset-skipped-anchor', $plan['replanReasons'], true));
    $t->true(in_array('window-before-compound-final-limit', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source recursive-comma-limit-window rejects missing comma recursive limit'] = static function (TestRunner $t) use ($currentTables160): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCommaLimitWindow(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed', 22) UNION ALL SELECT id + 1, 'seed:' || (id + 1), weight - 3 FROM staged WHERE id < 7 LIMIT 4) SELECT id, label, row_number() OVER (ORDER BY id) AS rank FROM staged UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight DESC) FROM wp_options LIMIT 2",
        $currentTables160,
        $currentTables160,
    ));
};

$tests['compound select window recursive limit current source recursive-comma-limit-window rejects non compound'] = static function (TestRunner $t) use ($currentTables160): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCommaLimitWindow(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed', 22) UNION ALL SELECT id + 1, 'seed:' || (id + 1), weight - 3 FROM staged WHERE id < 7 LIMIT 1, 4) SELECT id, label, row_number() OVER (ORDER BY id) AS rank FROM staged LIMIT 2",
        $currentTables160,
        $currentTables160,
    ));
};

$tests['compound select window recursive limit current source recursive-comma-limit-window rejects missing final limit'] = static function (TestRunner $t) use ($currentTables160): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCommaLimitWindow(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed', 22) UNION ALL SELECT id + 1, 'seed:' || (id + 1), weight - 3 FROM staged WHERE id < 7 LIMIT 1, 4) SELECT id, label, row_number() OVER (ORDER BY id) AS rank FROM staged UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight DESC) FROM wp_options ORDER BY rank",
        $currentTables160,
        $currentTables160,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source recursive-comma-limit-window generated comma limit boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveOffset = 1 + ($case % 2);
        $recursiveCount = 3 + ($case % 3);
        $finalLimit = 3 + ($case % 4);
        $finalOffset = $case % 2;
        $tables = [
            'wp_options' => [
                ['option_id' => 10, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 30 + $case],
                ['option_id' => 11, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 26 + $case],
                ['option_id' => 12, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 22 + $case],
                ['option_id' => 13, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'weight' => 40 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed_{$case}', " . (20 + $case) . ") UNION ALL SELECT id + 1, 'seed_{$case}:' || (id + 1), weight - 1 FROM staged WHERE id < 8 LIMIT {$recursiveOffset}, {$recursiveCount}) SELECT id, label, row_number() OVER (ORDER BY id) AS rank FROM staged UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY weight DESC, option_id) AS rank FROM wp_options WHERE autoload = 'yes' ORDER BY rank, id LIMIT {$finalLimit} OFFSET {$finalOffset}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(min($finalLimit, $recursiveCount + 3 - $finalOffset), count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['rank']));
        $t->true($rows[0]['rank'] <= $rows[count($rows) - 1]['rank']);
    };
}

return $tests;
