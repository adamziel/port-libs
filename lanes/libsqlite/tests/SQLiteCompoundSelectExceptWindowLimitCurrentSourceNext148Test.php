<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectExceptWindowLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions148 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'freshness' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'freshness' => 90],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'freshness' => 80],
    ['option_id' => 4, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'freshness' => 70],
    ['option_id' => 5, 'option_name' => 'active_plugins', 'autoload' => 'no', 'freshness' => 60],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'freshness' => 50],
];
$currentNetwork148 = [
    ['option_name' => 'home', 'autoload' => 'yes', 'source_rank' => 2],
    ['option_name' => 'active_plugins', 'autoload' => 'no', 'source_rank' => 2],
];
$currentStale148 = [
    ['option_name' => 'theme_mods', 'autoload' => 'yes', 'source_rank' => 5],
];
$nextOptions148 = [
    ...$currentOptions148,
    ['option_id' => 7, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'freshness' => 110],
    ['option_id' => 8, 'option_name' => 'plugin_beta', 'autoload' => 'no', 'freshness' => 85],
    ['option_id' => 9, 'option_name' => 'network_banner', 'autoload' => 'yes', 'freshness' => 75],
];
$nextNetwork148 = [
    ...$currentNetwork148,
    ['option_name' => 'siteurl', 'autoload' => 'yes', 'source_rank' => 2],
];
$nextStale148 = [
    ...$currentStale148,
    ['option_name' => 'network_banner', 'autoload' => 'yes', 'source_rank' => 4],
];

$currentTables148 = [
    'wp_options' => $currentOptions148,
    'network_current' => $currentNetwork148,
    'stale_option_audit' => $currentStale148,
];
$nextTables148 = [
    'wp_options' => $nextOptions148,
    'network_current' => $nextNetwork148,
    'stale_option_audit' => $nextStale148,
];

$sql148 = <<<'SQL'
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
  FROM network_current
EXCEPT
SELECT option_name AS name,
       autoload,
       source_rank
  FROM stale_option_audit
 ORDER BY source_rank DESC, name
 LIMIT 1, 4
SQL;

$summary148 = static fn (): array => SQLiteCompoundSelectExceptWindowLimitCurrentSourceNextPlan::compareExceptWindowLimitSources($sql148, $currentTables148, $nextTables148);
$tests = [];

$tests['compound select except window limit current source next148 status dependencies'] = static function (TestRunner $t) use ($summary148): void {
    $plan = $summary148();
    $t->same('compound-select-except-window-limit-current-source-next148-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-select-sql-chained-except',
        'sqlite-select-sql-compound-comma-limit',
        'sqlite-current-source-next148',
    ], $plan['dependencies']);
};

$tests['compound select except window limit current source next148 compound metadata'] = static function (TestRunner $t) use ($summary148): void {
    $compound = $summary148()['compound'];
    $t->same(['EXCEPT', 'EXCEPT'], $compound['operators']);
    $t->same([1, 2], $compound['exceptArmIndexes']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['source_rank', 'name'], $compound['orderColumns']);
    $t->same(4, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound select except window limit current source next148 current rows'] = static function (TestRunner $t) use ($summary148): void {
    $rows = $summary148()['currentRows'];
    $t->same(['blogname', 'rewrite_rules', 'siteurl'], array_column($rows, 'name'));
    $t->same(['yes', 'no', 'yes'], array_column($rows, 'autoload'));
    $t->same([3, 3, 1, 1], [count($rows), $rows[0]['source_rank'], $rows[1]['source_rank'], $rows[2]['source_rank']]);
};

$tests['compound select except window limit current source next148 next rows'] = static function (TestRunner $t) use ($summary148): void {
    $rows = $summary148()['nextRows'];
    $t->same(['network_banner', 'blogname', 'active_plugins', 'home'], array_column($rows, 'name'));
    $t->same(['yes', 'yes', 'no', 'yes'], array_column($rows, 'autoload'));
    $t->same([4, 5, 4, 3, 3], [count($rows), $rows[0]['source_rank'], $rows[1]['source_rank'], $rows[2]['source_rank'], $rows[3]['source_rank']]);
};

$tests['compound select except window limit current source next148 prelimit rows'] = static function (TestRunner $t) use ($summary148): void {
    $plan = $summary148();
    $t->same(['theme_mods', 'blogname', 'rewrite_rules', 'siteurl'], array_column($plan['currentPreLimitRows'], 'name'));
    $t->same(['theme_mods', 'network_banner', 'blogname', 'active_plugins', 'home', 'rewrite_rules', 'plugin_alpha', 'plugin_beta'], array_column($plan['nextPreLimitRows'], 'name'));
};

$tests['compound select except window limit current source next148 window metadata'] = static function (TestRunner $t) use ($summary148): void {
    $windows = $summary148()['windows']['current'];
    $t->same(['row_number'], array_column($windows, 'function'));
    $t->same(['source_rank'], array_column($windows, 'alias'));
    $t->same([1], array_column($windows, 'partitionCount'));
    $t->same([2], array_column($windows, 'orderCount'));
    $t->same([null], array_column($windows, 'frameUnit'));
};

$tests['compound select except window limit current source next148 chained except trace'] = static function (TestRunner $t) use ($summary148): void {
    $trace = $summary148()['exceptTrace'];
    $t->same(['home', 'active_plugins'], $trace['currentRemovedNames']);
    $t->same(['siteurl'], $trace['nextRemovedNames']);
    $t->same([6, 4], array_column($trace['current'], 'beforeCount'));
    $t->same([4, 4], array_column($trace['current'], 'afterCount'));
    $t->same([9, 8], array_column($trace['next'], 'beforeCount'));
    $t->same([8, 8], array_column($trace['next'], 'afterCount'));
};

$tests['compound select except window limit current source next148 limit trace'] = static function (TestRunner $t) use ($summary148): void {
    $trace = $summary148()['limitTrace'];
    $t->same(4, $trace['current']['preLimitCount']);
    $t->same(8, $trace['next']['preLimitCount']);
    $t->same(['theme_mods'], array_column($trace['current']['skippedBeforeOffset'], 'name'));
    $t->same(['theme_mods'], array_column($trace['next']['skippedBeforeOffset'], 'name'));
    $t->same([], array_column($trace['current']['truncatedAfterLimit'], 'name'));
    $t->same(['rewrite_rules', 'plugin_alpha', 'plugin_beta'], array_column($trace['next']['truncatedAfterLimit'], 'name'));
};

$tests['compound select except window limit current source next148 boundary changes'] = static function (TestRunner $t) use ($summary148): void {
    $boundary = $summary148()['boundary'];
    $t->same('blogname', $boundary['currentFirst']['name']);
    $t->same('network_banner', $boundary['nextFirst']['name']);
    $t->same('siteurl', $boundary['currentLast']['name']);
    $t->same('home', $boundary['nextLast']['name']);
    $t->same(['rewrite_rules', 'siteurl', 'network_banner', 'active_plugins', 'home'], $boundary['admittedNamesChanged']);
};

$tests['compound select except window limit current source next148 changed signatures and reasons'] = static function (TestRunner $t) use ($summary148): void {
    $plan = $summary148();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"name":"siteurl"', $changed);
    $t->contains('"name":"network_banner"', $changed);
    $t->true(in_array('limited-chained-except-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-chained-except-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('chained-except-removal-trace-changed', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-comma-limit', $plan['replanReasons'], true));
    $t->true(in_array('window-before-chained-except', $plan['replanReasons'], true));
};

$tests['compound select except window limit current source next148 rejects single except'] = static function (TestRunner $t) use ($currentTables148): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectExceptWindowLimitCurrentSourceNextPlan::compareExceptWindowLimitSources(
        'SELECT option_name AS name, autoload, row_number() OVER (ORDER BY option_id) AS source_rank FROM wp_options EXCEPT SELECT option_name, autoload, source_rank FROM network_current ORDER BY source_rank LIMIT 1',
        $currentTables148,
        $currentTables148,
    ));
};

$tests['compound select except window limit current source next148 rejects missing limit'] = static function (TestRunner $t) use ($currentTables148): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectExceptWindowLimitCurrentSourceNextPlan::compareExceptWindowLimitSources(
        'SELECT option_name AS name, autoload, row_number() OVER (ORDER BY option_id) AS source_rank FROM wp_options EXCEPT SELECT option_name, autoload, source_rank FROM network_current EXCEPT SELECT option_name, autoload, source_rank FROM stale_option_audit ORDER BY source_rank',
        $currentTables148,
        $currentTables148,
    ));
};

$tests['compound select except window limit current source next148 rejects missing window'] = static function (TestRunner $t) use ($currentTables148): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectExceptWindowLimitCurrentSourceNextPlan::compareExceptWindowLimitSources(
        'SELECT option_name AS name, autoload, option_id AS source_rank FROM wp_options EXCEPT SELECT option_name, autoload, source_rank FROM network_current EXCEPT SELECT option_name, autoload, source_rank FROM stale_option_audit ORDER BY source_rank LIMIT 1',
        $currentTables148,
        $currentTables148,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select except window limit current source next148 generated chained except boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'freshness' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'freshness' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'freshness' => 110 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'freshness' => 80 + $case],
                ['option_id' => 5, 'option_name' => 'rewrite_' . $case, 'autoload' => 'no', 'freshness' => 70 + $case],
            ],
            'network_current' => [
                ['option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'source_rank' => 1],
            ],
            'stale_option_audit' => [
                ['option_name' => 'rewrite_' . $case, 'autoload' => 'no', 'source_rank' => 2],
            ],
        ];
        $sql = "SELECT option_name AS name, autoload, row_number() OVER (PARTITION BY autoload ORDER BY freshness DESC, option_id) AS source_rank FROM wp_options EXCEPT SELECT option_name AS name, autoload, source_rank FROM network_current EXCEPT SELECT option_name AS name, autoload, source_rank FROM stale_option_audit ORDER BY source_rank DESC, name LIMIT 1, 3";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(['autoload_' . $case, 'transient_' . $case], array_column($rows, 'name'));
        $t->same([2, 2, 1], [count($rows), $rows[0]['source_rank'], $rows[1]['source_rank']]);
        $t->same(['yes', 'no'], array_column($rows, 'autoload'));
    };
}

return $tests;
