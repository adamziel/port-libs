<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'freshness' => 90],
    ['setting_id' => 2, 'key_name' => 'home', 'load_policy' => 'yes', 'freshness' => 80],
    ['setting_id' => 3, 'key_name' => 'site_title', 'load_policy' => 'yes', 'freshness' => 70],
    ['setting_id' => 4, 'key_name' => 'active_modules', 'load_policy' => 'no', 'freshness' => 60],
    ['setting_id' => 5, 'key_name' => 'route_rules', 'load_policy' => 'no', 'freshness' => 50],
];
$nextOptions = [
    ...$currentOptions,
    ['setting_id' => 6, 'key_name' => 'module_alpha', 'load_policy' => 'yes', 'freshness' => 95],
    ['setting_id' => 7, 'key_name' => 'module_beta', 'load_policy' => 'no', 'freshness' => 65],
];
$currentAudit = [
    ['key_name' => 'home', 'load_policy' => 'yes', 'source_rank' => 2],
    ['key_name' => 'route_rules', 'load_policy' => 'no', 'source_rank' => 2],
];
$currentTables = ['app_settings' => $currentOptions, 'app_settings_current' => $currentAudit];
$nextTables = ['app_settings' => $nextOptions, 'app_settings_current' => $currentAudit];

$sql = <<<'SQL'
SELECT key_name AS name,
       load_policy,
       row_number() OVER (
           PARTITION BY load_policy
           ORDER BY freshness DESC, setting_id
       ) AS source_rank
  FROM app_settings
EXCEPT
SELECT key_name AS name,
       load_policy,
       source_rank
  FROM app_settings_current
 ORDER BY source_rank DESC, name
SQL;

$summary = static fn (): array => SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan::compare($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound window except order current source status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-window-except-order-current-source-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-select-sql-compound-except',
        'sqlite-select-sql-compound-final-order',
        'sqlite-current-source-next-rowset-boundary',
    ], $plan['dependencies']);
};

$tests['compound window except order current source compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['EXCEPT'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['source_rank', 'name'], $compound['orderColumns']);
    $t->same(['DESC', 'ASC'], $compound['orderDirections']);
};

$tests['compound window except order current source current ordered rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['site_title', 'active_modules', 'base_url'], array_column($rows, 'name'));
    $t->same(['yes', 'no', 'yes'], array_column($rows, 'load_policy'));
    $t->same([3, 1, 1], array_column($rows, 'source_rank'));
};

$tests['compound window except order current source next ordered rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['site_title', 'home', 'route_rules', 'active_modules', 'base_url', 'module_alpha', 'module_beta'], array_column($rows, 'name'));
    $t->same([4, 3, 3, 2, 2, 1, 1], array_column($rows, 'source_rank'));
};

$tests['compound window except order current source window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['row_number'], array_column($windows, 'function'));
    $t->same(['source_rank'], array_column($windows, 'alias'));
    $t->same([1], array_column($windows, 'partitionCount'));
    $t->same([2], array_column($windows, 'orderCount'));
};

$tests['compound window except order current source except trace preserves stale current source removals'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['exceptTrace'];
    $t->same(['home', 'route_rules'], $trace['currentRemoved']);
    $t->same([], $trace['nextRemoved']);
    $t->same(['active_modules', 'base_url', 'site_title'], $trace['currentPreOrderNames']);
    $t->same(['active_modules', 'base_url', 'home', 'module_alpha', 'module_beta', 'route_rules', 'site_title'], $trace['nextPreOrderNames']);
};

$tests['compound window except order current source boundary shifts'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['boundary'];
    $t->same('site_title', $boundary['currentFirst']['name']);
    $t->same('site_title', $boundary['nextFirst']['name']);
    $t->same('base_url', $boundary['currentLast']['name']);
    $t->same('module_beta', $boundary['nextLast']['name']);
    $t->same(['site_title', 'active_modules', 'base_url'], $boundary['rankShiftNames']);
};

$tests['compound window except order current source changed signatures and reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->true(str_contains($changed, '"name":"module_alpha"'));
    $t->true(str_contains($changed, '"name":"route_rules"'));
    $t->true(in_array('ordered-except-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('preorder-except-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('window-before-except', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-order', $plan['replanReasons'], true));
};

$tests['compound window except order current source rejects non except compound'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan::compare(
        'SELECT key_name AS name, row_number() OVER (ORDER BY setting_id) AS source_rank FROM app_settings UNION ALL SELECT key_name, source_rank FROM app_settings_current ORDER BY source_rank',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound window except order current source rejects missing final order'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan::compare(
        'SELECT key_name AS name, row_number() OVER (ORDER BY setting_id) AS source_rank FROM app_settings EXCEPT SELECT key_name, source_rank FROM app_settings_current',
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound window except order current source generated stale audit boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'app_settings' => [
                ['setting_id' => 1, 'key_name' => 'load_policy_' . $case, 'load_policy' => 'yes', 'freshness' => 100 + $case],
                ['setting_id' => 2, 'key_name' => 'home_' . $case, 'load_policy' => 'yes', 'freshness' => 90 + $case],
                ['setting_id' => 3, 'key_name' => 'plugin_' . $case, 'load_policy' => 'yes', 'freshness' => 110 + $case],
                ['setting_id' => 4, 'key_name' => 'transient_' . $case, 'load_policy' => 'no', 'freshness' => 80 + $case],
                ['setting_id' => 5, 'key_name' => 'rewrite_' . $case, 'load_policy' => 'no', 'freshness' => 70 + $case],
            ],
            'app_settings_current' => [
                ['key_name' => 'load_policy_' . $case, 'load_policy' => 'yes', 'source_rank' => 1],
                ['key_name' => 'rewrite_' . $case, 'load_policy' => 'no', 'source_rank' => 2],
            ],
        ];
        $sql = "SELECT key_name AS name, load_policy, row_number() OVER (PARTITION BY load_policy ORDER BY freshness DESC, setting_id) AS source_rank FROM app_settings EXCEPT SELECT key_name AS name, load_policy, source_rank FROM app_settings_current ORDER BY source_rank DESC, name";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(['home_' . $case, 'load_policy_' . $case, 'plugin_' . $case, 'transient_' . $case], array_column($rows, 'name'));
        $t->same([4, 3, 2, 1], [count($rows), $rows[0]['source_rank'], $rows[1]['source_rank'], $rows[2]['source_rank']]);
        $t->same('yes', $rows[0]['load_policy']);
    };
}

return $tests;
