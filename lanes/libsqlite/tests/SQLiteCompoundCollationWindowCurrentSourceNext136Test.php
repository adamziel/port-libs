<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundCollationWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'Home', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'Plugin_A', 'autoload' => 'no'],
];
$currentNetwork = [
    ['option_id' => 10, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'network_home', 'autoload' => 'yes'],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 4, 'option_name' => 'Theme_Mod', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'Cache_Flag', 'autoload' => 'yes'],
];
$nextNetwork = [
    ...$currentNetwork,
    ['option_id' => 12, 'option_name' => 'theme_mod', 'autoload' => 'yes'],
    ['option_id' => 13, 'option_name' => 'cache_flag', 'autoload' => 'yes'],
];

$sql = <<<'SQL'
SELECT option_name COLLATE NOCASE AS name,
       row_number() OVER (ORDER BY option_name COLLATE NOCASE) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_name AS name,
       row_number() OVER (ORDER BY option_name COLLATE NOCASE) AS rn
  FROM network_options
 WHERE autoload = 'yes'
 ORDER BY name COLLATE NOCASE, rn
SQL;

$currentTables = ['wp_options' => $currentOptions, 'network_options' => $currentNetwork];
$nextTables = ['wp_options' => $nextOptions, 'network_options' => $nextNetwork];
$summary = static fn (): array => SQLiteCompoundCollationWindowCurrentSourceNextPlan::compareNext136($sql, $currentTables, $nextTables);

$tests = [];

$tests['compound collation window current source next136 status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-collation-window-current-source-next136-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-left-collation-dedup', $plan['dependencies'], true));
    $t->true(in_array('sqlite-window-arm-before-compound', $plan['dependencies'], true));
    $t->true(in_array('sqlite-current-source-next136', $plan['dependencies'], true));
};

$tests['compound collation window current source next136 compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['name' => 'NOCASE'], $compound['leftCollations']);
    $t->same(['name' => 'NOCASE'], $compound['orderByCollations']);
};

$tests['compound collation window current source next136 current rowset'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['Home', 'network_home', 'siteurl'], $plan['currentNames']);
    $t->same([1, 2, 2], $plan['windows']['currentRowNumbers']);
    $t->same(["name=text:home\0rn=numeric:1"], $plan['compound']['currentDuplicateKeys']);
};

$tests['compound collation window current source next136 next rowset preserves window rank distinction'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['Cache_Flag', 'Home', 'network_home', 'siteurl', 'Theme_Mod', 'theme_mod'], $plan['nextNames']);
    $t->same([1, 2, 3, 4, 3, 4], $plan['windows']['nextRowNumbers']);
    $t->true(in_array("name=text:cache_flag\0rn=numeric:1", $plan['compound']['nextDuplicateKeys'], true));
};

$tests['compound collation window current source next136 suppresses only equal window rows'] = static function (TestRunner $t) use ($summary): void {
    $suppressed = $summary()['compound']['nextSuppressedRows'];
    $t->same(['home', 'cache_flag'], array_column($suppressed, 'name'));
    $t->same([2, 1], array_column($suppressed, 'rn'));
};

$tests['compound collation window current source next136 window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['rn'], $windows['aliases']);
    $t->same(['row_number', 'row_number'], array_column($windows['current'], 'function'));
    $t->same([0, 0], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 1], array_column($windows['current'], 'orderCount'));
};

$tests['compound collation window current source next136 changed signatures'] = static function (TestRunner $t) use ($summary): void {
    $changed = implode("\n", $summary()['changedSignatures']);
    $t->true(str_contains($changed, '"name":"Cache_Flag"'));
    $t->true(str_contains($changed, '"name":"Theme_Mod"'));
    $t->true(str_contains($changed, '"name":"theme_mod"'));
    $t->true(str_contains($changed, '"rn":4'));
};

$tests['compound collation window current source next136 replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->true(in_array('compound-window-rowset-changed', $reasons, true));
    $t->true(in_array('compound-left-collation', $reasons, true));
    $t->true(in_array('window-before-compound-source', $reasons, true));
    $t->true(in_array('compound-dedup-keyset-changed', $reasons, true));
};

$tests['compound collation window current source next136 rejects non distinct compound'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundCollationWindowCurrentSourceNextPlan::compareNext136(
        "SELECT option_name AS name FROM wp_options UNION ALL SELECT option_name AS name FROM network_options",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound collation window current source next136 generated case ' . $case] = static function (TestRunner $t) use ($case): void {
        $leftName = $case % 2 === 0 ? 'Plugin_' . $case : 'plugin_' . $case;
        $rightName = $case % 2 === 0 ? 'plugin_' . $case : 'PLUGIN_' . $case;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => $leftName, 'autoload' => 'yes'],
                ['option_id' => 2, 'option_name' => 'local_' . $case, 'autoload' => 'yes'],
            ],
            'network_options' => [
                ['option_id' => 10, 'option_name' => $rightName, 'autoload' => 'yes'],
                ['option_id' => 11, 'option_name' => 'network_' . $case, 'autoload' => 'yes'],
            ],
        ];
        $sql = "SELECT option_name COLLATE NOCASE AS name, row_number() OVER (ORDER BY option_name COLLATE NOCASE) AS rn FROM wp_options WHERE autoload = 'yes' UNION SELECT option_name AS name, row_number() OVER (ORDER BY option_name COLLATE NOCASE) AS rn FROM network_options WHERE autoload = 'yes' ORDER BY name COLLATE NOCASE, rn";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(['name', 'rn'], array_keys($rows[0]));
        $t->true(count($rows) >= 3);
        $t->true(count($rows) <= 4);
        $t->true(in_array('local_' . $case, array_column($rows, 'name'), true));
        $t->true(in_array('network_' . $case, array_column($rows, 'name'), true));
    };
}

return $tests;
