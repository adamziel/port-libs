<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'freshness' => 90],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'freshness' => 80],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'freshness' => 70],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'freshness' => 60],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'freshness' => 50],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'freshness' => 95],
    ['option_id' => 7, 'option_name' => 'plugin_beta', 'autoload' => 'no', 'freshness' => 65],
];
$currentAudit = [
    ['option_name' => 'home', 'autoload' => 'yes', 'source_rank' => 2],
    ['option_name' => 'rewrite_rules', 'autoload' => 'no', 'source_rank' => 2],
];
$currentTables = ['wp_options' => $currentOptions, 'wp_option_current' => $currentAudit];
$nextTables = ['wp_options' => $nextOptions, 'wp_option_current' => $currentAudit];

$sql = <<<'SQL'
SELECT option_name AS name,
       autoload,
       row_number() OVER (
           PARTITION BY autoload
           ORDER BY freshness DESC, option_id
       ) AS source_rank
  FROM wp_options
EXCEPT
SELECT option_name AS name,
       autoload,
       source_rank
  FROM wp_option_current
 ORDER BY source_rank DESC, name
SQL;

$summary = static fn (): array => SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan::compareNext143($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound window except order current source next143 status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-window-except-order-current-source-next143-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-select-sql-compound-except',
        'sqlite-select-sql-compound-final-order',
        'sqlite-current-source-next-rowset-boundary',
    ], $plan['dependencies']);
};

$tests['compound window except order current source next143 compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['EXCEPT'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['source_rank', 'name'], $compound['orderColumns']);
    $t->same(['DESC', 'ASC'], $compound['orderDirections']);
};

$tests['compound window except order current source next143 current ordered rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['blogname', 'active_plugins', 'siteurl'], array_column($rows, 'name'));
    $t->same(['yes', 'no', 'yes'], array_column($rows, 'autoload'));
    $t->same([3, 1, 1], array_column($rows, 'source_rank'));
};

$tests['compound window except order current source next143 next ordered rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['blogname', 'home', 'rewrite_rules', 'active_plugins', 'siteurl', 'plugin_alpha', 'plugin_beta'], array_column($rows, 'name'));
    $t->same([4, 3, 3, 2, 2, 1, 1], array_column($rows, 'source_rank'));
};

$tests['compound window except order current source next143 window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['row_number'], array_column($windows, 'function'));
    $t->same(['source_rank'], array_column($windows, 'alias'));
    $t->same([1], array_column($windows, 'partitionCount'));
    $t->same([2], array_column($windows, 'orderCount'));
};

$tests['compound window except order current source next143 except trace preserves stale current source removals'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['exceptTrace'];
    $t->same(['home', 'rewrite_rules'], $trace['currentRemoved']);
    $t->same([], $trace['nextRemoved']);
    $t->same(['siteurl', 'blogname', 'active_plugins'], $trace['currentPreOrderNames']);
    $t->same(['siteurl', 'home', 'blogname', 'active_plugins', 'rewrite_rules', 'plugin_alpha', 'plugin_beta'], $trace['nextPreOrderNames']);
};

$tests['compound window except order current source next143 boundary shifts'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['boundary'];
    $t->same('blogname', $boundary['currentFirst']['name']);
    $t->same('blogname', $boundary['nextFirst']['name']);
    $t->same('siteurl', $boundary['currentLast']['name']);
    $t->same('plugin_beta', $boundary['nextLast']['name']);
    $t->same(['blogname', 'active_plugins', 'siteurl'], $boundary['rankShiftNames']);
};

$tests['compound window except order current source next143 changed signatures and reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->true(str_contains($changed, '"name":"plugin_alpha"'));
    $t->true(str_contains($changed, '"name":"rewrite_rules"'));
    $t->true(in_array('ordered-except-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('preorder-except-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('window-before-except', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-order', $plan['replanReasons'], true));
};

$tests['compound window except order current source next143 rejects non except compound'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan::compareNext143(
        'SELECT option_name AS name, row_number() OVER (ORDER BY option_id) AS source_rank FROM wp_options UNION ALL SELECT option_name, source_rank FROM wp_option_current ORDER BY source_rank',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound window except order current source next143 rejects missing final order'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan::compareNext143(
        'SELECT option_name AS name, row_number() OVER (ORDER BY option_id) AS source_rank FROM wp_options EXCEPT SELECT option_name, source_rank FROM wp_option_current',
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound window except order current source next143 generated stale audit boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'freshness' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'freshness' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'freshness' => 110 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'freshness' => 80 + $case],
                ['option_id' => 5, 'option_name' => 'rewrite_' . $case, 'autoload' => 'no', 'freshness' => 70 + $case],
            ],
            'wp_option_current' => [
                ['option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'source_rank' => 1],
                ['option_name' => 'rewrite_' . $case, 'autoload' => 'no', 'source_rank' => 2],
            ],
        ];
        $sql = "SELECT option_name AS name, autoload, row_number() OVER (PARTITION BY autoload ORDER BY freshness DESC, option_id) AS source_rank FROM wp_options EXCEPT SELECT option_name AS name, autoload, source_rank FROM wp_option_current ORDER BY source_rank DESC, name";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(['home_' . $case, 'autoload_' . $case, 'plugin_' . $case, 'transient_' . $case], array_column($rows, 'name'));
        $t->same([4, 3, 2, 1], [count($rows), $rows[0]['source_rank'], $rows[1]['source_rank'], $rows[2]['source_rank']]);
        $t->same('yes', $rows[0]['autoload']);
    };
}

return $tests;
