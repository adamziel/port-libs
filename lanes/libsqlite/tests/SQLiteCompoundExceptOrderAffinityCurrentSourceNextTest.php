<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => '1'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 1.5],
    ['option_id' => 4, 'option_name' => 'transient_cache', 'autoload' => 'no', 'weight' => null],
    ['option_id' => 5, 'option_name' => 'Plugin_Flag', 'autoload' => 'yes', 'weight' => '02'],
];
$currentNetwork = [
    ['option_id' => 10, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'weight' => 1.0],
    ['option_id' => 11, 'option_name' => 'HOME', 'autoload' => 'yes', 'weight' => 1],
    ['option_id' => 12, 'option_name' => 'network_home', 'autoload' => 'yes', 'weight' => 3],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'network_home', 'autoload' => 'yes', 'weight' => 3],
    ['option_id' => 7, 'option_name' => 'new_plugin_flag', 'autoload' => 'yes', 'weight' => '2'],
    ['option_id' => 8, 'option_name' => 'zero_text', 'autoload' => 'yes', 'weight' => '0'],
];
$nextNetwork = [
    ...$currentNetwork,
    ['option_id' => 13, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 1.5],
    ['option_id' => 14, 'option_name' => 'plugin_flag', 'autoload' => 'yes', 'weight' => 2],
    ['option_id' => 15, 'option_name' => 'zero_text', 'autoload' => 'yes', 'weight' => 0],
];

$sql = <<<'SQL'
SELECT option_name COLLATE NOCASE AS name,
       weight AS class_value
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_name AS name,
       weight AS class_value
  FROM network_options
 WHERE autoload = 'yes'
 ORDER BY class_value ASC NULLS LAST, name COLLATE NOCASE DESC
SQL;

$currentTables = ['wp_options' => $currentOptions, 'network_options' => $currentNetwork];
$nextTables = ['wp_options' => $nextOptions, 'network_options' => $nextNetwork];
$summary = static fn (): array => SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan::compare($sql, $currentTables, $nextTables);

$tests = [];

$tests['compound except order affinity current source status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-except-order-affinity-current-source-next-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-except-affinity', $plan['dependencies'], true));
    $t->true(in_array('sqlite-select-sql-compound-tail-order', $plan['dependencies'], true));
    $t->true(in_array('sqlite-select-result-storage-class-order', $plan['dependencies'], true));
    $t->true(in_array('sqlite-current-source-next', $plan['dependencies'], true));
};

$tests['compound except order affinity current source compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['EXCEPT'], $compound['operators']);
    $t->same([1], $compound['exceptArmIndexes']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['name' => 'NOCASE'], $compound['leftCollations']);
};

$tests['compound except order affinity current source tail order metadata'] = static function (TestRunner $t) use ($summary): void {
    $order = $summary()['compound']['orderBy'];
    $t->same('class_value', $order[0]['column']);
    $t->same('ASC', $order[0]['direction']);
    $t->same('LAST', $order[0]['nulls']);
    $t->same('name', $order[1]['column']);
    $t->same('NOCASE', strtoupper((string) $order[1]['collation']));
    $t->same('DESC', $order[1]['direction']);
};

$tests['compound except order affinity current source current rows sorted by storage affinity'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['blogname', 'Plugin_Flag', 'home'], array_column($rows, 'name'));
    $t->same([1.5, '02', '1'], array_column($rows, 'class_value'));
};

$tests['compound except order affinity current source next rows sorted after current source delta'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['zero_text', 'Plugin_Flag', 'home', 'new_plugin_flag'], array_column($rows, 'name'));
    $t->same(['0', '02', '1', '2'], array_column($rows, 'class_value'));
};

$tests['compound except order affinity current source removed rows preserve nocase and numeric matching'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['siteurl'], array_column($plan['except']['currentRemoved'], 'name'));
    $t->same(['siteurl', 'blogname', 'network_home'], array_column($plan['except']['nextRemoved'], 'name'));
};

$tests['compound except order affinity current source pre order trace keeps EXCEPT result before tail sort'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['orderTrace'];
    $t->same(['home', 'blogname', 'Plugin_Flag'], array_column($trace['currentPreOrder'], 'name'));
    $t->same(['home', 'Plugin_Flag', 'new_plugin_flag', 'zero_text'], array_column($trace['nextPreOrder'], 'name'));
};

$tests['compound except order affinity current source order keys expose storage classes'] = static function (TestRunner $t) use ($summary): void {
    $keys = $summary()['orderTrace']['nextKeys'];
    $t->contains('class_value=text:0:dir=ASC', $keys[0]);
    $t->contains('class_value=text:02:dir=ASC', $keys[1]);
    $t->contains('class_value=text:1:dir=ASC', $keys[2]);
    $t->contains('class_value=text:2:dir=ASC', $keys[3]);
    $t->contains('name=text:zero_text:dir=DESC:collate=NOCASE', $keys[0]);
};

$tests['compound except order affinity current source affinity classes'] = static function (TestRunner $t) use ($summary): void {
    $affinity = $summary()['affinity'];
    $t->true(in_array('numeric:1.5', $affinity['currentClasses'], true));
    $t->true(in_array('text:02', $affinity['currentClasses'], true));
    $t->true(in_array('text:2', $affinity['nextClasses'], true));
    $t->true(in_array('text:0', $affinity['nextClasses'], true));
    $t->true(in_array('numeric:3', $affinity['nextRemovedClasses'], true));
};

$tests['compound except order affinity current source changed signatures'] = static function (TestRunner $t) use ($summary): void {
    $changed = implode("\n", $summary()['changedSignatures']);
    $t->contains('"name":"blogname"', $changed);
    $t->contains('"name":"new_plugin_flag"', $changed);
    $t->contains('"class_value":"2"', $changed);
};

$tests['compound except order affinity current source replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->true(in_array('compound-except-order-rowset-changed', $reasons, true));
    $t->true(in_array('compound-tail-order-by', $reasons, true));
    $t->true(in_array('order-affinity-class-changed', $reasons, true));
    $t->true(in_array('except-removal-set-changed', $reasons, true));
};

$tests['compound except order affinity current source rejects missing except'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan::compare(
        'SELECT option_name AS name FROM wp_options UNION SELECT option_name AS name FROM network_options ORDER BY name',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound except order affinity current source rejects missing tail order'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan::compare(
        'SELECT option_name AS name FROM wp_options EXCEPT SELECT option_name AS name FROM network_options',
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 48) as $case) {
    $tests['compound except order affinity current source generated order affinity case ' . $case] = static function (TestRunner $t) use ($case): void {
        $leftWeight = $case % 3 === 0 ? (string) $case : $case;
        $rightWeight = $case % 3 === 0 ? $case : (float) $case;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'option_' . $case, 'autoload' => 'yes', 'weight' => $leftWeight],
                ['option_id' => 2, 'option_name' => 'local_' . $case, 'autoload' => 'yes', 'weight' => 'z' . $case],
            ],
            'network_options' => [
                ['option_id' => 10, 'option_name' => strtoupper('option_' . $case), 'autoload' => 'yes', 'weight' => $rightWeight],
            ],
        ];
        $sql = "SELECT option_name COLLATE NOCASE AS name, weight AS class_value FROM wp_options EXCEPT SELECT option_name AS name, weight AS class_value FROM network_options ORDER BY class_value ASC, name COLLATE NOCASE DESC";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        if ($case % 3 === 0) {
            $t->same(['option_' . $case, 'local_' . $case], array_column($rows, 'name'));
        } else {
            $t->same(['local_' . $case], array_column($rows, 'name'));
        }
        $t->true(isset($rows[0]['class_value']));
    };
}

return $tests;
