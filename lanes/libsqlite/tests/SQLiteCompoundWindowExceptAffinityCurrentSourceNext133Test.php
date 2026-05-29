<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowExceptAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => '1'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 1.5],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 2],
];
$currentNetwork = [
    ['option_id' => 10, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'weight' => 1.0],
    ['option_id' => 11, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 1],
    ['option_id' => 12, 'option_name' => 'network_home', 'autoload' => 'yes', 'weight' => 3],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'new_plugin_flag', 'autoload' => 'yes', 'weight' => '2'],
    ['option_id' => 6, 'option_name' => 'network_home', 'autoload' => 'yes', 'weight' => 3],
];
$nextNetwork = [
    ...$currentNetwork,
    ['option_id' => 13, 'option_name' => 'new_plugin_flag', 'autoload' => 'yes', 'weight' => 2],
    ['option_id' => 14, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 1.5],
];

$sql = <<<'SQL'
SELECT option_name COLLATE NOCASE AS name,
       weight AS class_value,
       sum(CAST(weight AS REAL)) OVER (
           PARTITION BY autoload
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_name AS name,
       weight AS class_value,
       sum(CAST(weight AS REAL)) OVER (
           PARTITION BY autoload
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS frame_weight
  FROM network_options
 WHERE autoload = 'yes'
 ORDER BY name COLLATE NOCASE, class_value
SQL;

$currentTables = ['wp_options' => $currentOptions, 'network_options' => $currentNetwork];
$nextTables = ['wp_options' => $nextOptions, 'network_options' => $nextNetwork];
$summary = static fn (): array => SQLiteCompoundWindowExceptAffinityCurrentSourceNextPlan::compareNext133($sql, $currentTables, $nextTables);

$tests = [];

$tests['compound window except affinity current source next133 status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-window-except-affinity-current-source-next133-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-except-affinity', $plan['dependencies'], true));
    $t->true(in_array('sqlite-window-arm-current-source', $plan['dependencies'], true));
};

$tests['compound window except affinity current source next133 compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['EXCEPT'], $compound['operators']);
    $t->same([1], $compound['exceptArmIndexes']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['name', 'class_value'], $compound['orderColumns']);
};

$tests['compound window except affinity current source next133 current rows keep storage class differences'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['blogname', 'home'], array_column($rows, 'name'));
    $t->same([1.5, '1'], array_column($rows, 'class_value'));
    $t->same([1.5, 1.0], array_column($rows, 'frame_weight'));
};

$tests['compound window except affinity current source next133 next rows include current source changes'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['home', 'new_plugin_flag'], array_column($rows, 'name'));
    $t->same(['1', '2'], array_column($rows, 'class_value'));
    $t->same([1.0, 2.0], array_column($rows, 'frame_weight'));
};

$tests['compound window except affinity current source next133 removed rows use nocase and numeric equality'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['siteurl'], array_column($plan['except']['currentRemoved'], 'name'));
    $t->same(['siteurl', 'blogname', 'network_home'], array_column($plan['except']['nextRemoved'], 'name'));
};

$tests['compound window except affinity current source next133 window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['frame_weight'], $windows['aliases']);
    $t->same(['sum', 'sum'], array_column($windows['current'], 'function'));
    $t->same([1, 1], array_column($windows['current'], 'partitionCount'));
    $t->same(['ROWS', 'ROWS'], array_column($windows['current'], 'frameUnit'));
};

$tests['compound window except affinity current source next133 affinity diagnostics'] = static function (TestRunner $t) use ($summary): void {
    $affinity = $summary()['affinity'];
    $t->true(in_array('string:1', $affinity['currentClasses'], true));
    $t->true(in_array('string:2', $affinity['changedClasses'], true));
    $t->true(in_array('numeric:1.5', $affinity['currentDuplicateClasses'], true));
    $t->true(in_array('numeric:2', $affinity['nextClasses'], true));
};

$tests['compound window except affinity current source next133 changed signatures identify source delta'] = static function (TestRunner $t) use ($summary): void {
    $changed = implode("\n", $summary()['changedSignatures']);
    $t->true(str_contains($changed, '"name":"blogname"'));
    $t->true(str_contains($changed, '"name":"new_plugin_flag"'));
    $t->true(str_contains($changed, '"class_value":"2"'));
};

$tests['compound window except affinity current source next133 replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->true(in_array('compound-except-rowset-changed', $reasons, true));
    $t->true(in_array('compound-window-arm-source', $reasons, true));
    $t->true(in_array('affinity-class-changed', $reasons, true));
    $t->true(in_array('except-removal-set-changed', $reasons, true));
};

$tests['compound window except affinity current source next133 rejects non except compound'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowExceptAffinityCurrentSourceNextPlan::compareNext133(
        'SELECT option_name AS name FROM wp_options UNION SELECT option_name AS name FROM network_options',
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 44) as $case) {
    $tests['compound window except affinity current source next133 generated except affinity case ' . $case] = static function (TestRunner $t) use ($case): void {
        $leftWeight = $case % 2 === 0 ? (string) $case : $case;
        $rightWeight = $case % 2 === 0 ? $case : (float) $case;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'option_' . $case, 'autoload' => 'yes', 'weight' => $leftWeight],
                ['option_id' => 2, 'option_name' => 'local_' . $case, 'autoload' => 'yes', 'weight' => $case + 0.25],
            ],
            'network_options' => [
                ['option_id' => 10, 'option_name' => strtoupper('option_' . $case), 'autoload' => 'yes', 'weight' => $rightWeight],
            ],
        ];
        $sql = "SELECT option_name COLLATE NOCASE AS name, weight AS class_value, sum(CAST(weight AS REAL)) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS frame_weight FROM wp_options EXCEPT SELECT option_name AS name, weight AS class_value, sum(CAST(weight AS REAL)) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS frame_weight FROM network_options ORDER BY name COLLATE NOCASE";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        if ($case % 2 === 0) {
            $t->same(['local_' . $case, 'option_' . $case], array_column($rows, 'name'));
        } else {
            $t->same(['local_' . $case], array_column($rows, 'name'));
        }
        $t->true(isset($rows[0]['frame_weight']));
    };
}

return $tests;
