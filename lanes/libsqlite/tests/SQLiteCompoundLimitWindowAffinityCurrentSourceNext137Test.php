<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 8, 'class_value' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 7, 'class_value' => '1'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 6, 'class_value' => 2.5],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 5, 'class_value' => '2'],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 4, 'class_value' => 3],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'no', 'weight' => 3, 'class_value' => '3'],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 7, 'option_name' => 'plugin_alpha', 'autoload' => 'no', 'weight' => 10, 'class_value' => '4'],
    ['option_id' => 8, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'weight' => 9, 'class_value' => 4],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
SELECT option_name AS name,
       class_value,
       sum(weight) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'yes'
UNION ALL
SELECT option_name AS name,
       class_value,
       first_value(weight) OVER (
           ORDER BY option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'no'
 ORDER BY frame_weight DESC, name
 LIMIT 4 OFFSET 1
SQL;

$summary = static fn (): array => SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan::compareNext137($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound limit window affinity current source next137 status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-limit-window-affinity-current-source-next137-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-compound-final-limit',
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-compound-affinity-storage-class-comparison',
        'sqlite-current-source-next-rowset-boundary',
    ], $plan['dependencies']);
};

$tests['compound limit window affinity current source next137 compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['frame_weight', 'name'], $compound['orderColumns']);
    $t->same(4, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound limit window affinity current source next137 current limited rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['home', 'blogname', 'active_plugins', 'rewrite_rules'], array_column($rows, 'name'));
    $t->same(['1', 2.5, '2', 3], array_column($rows, 'class_value'));
    $t->same([13, 6, 5, 4], array_column($rows, 'frame_weight'));
};

$tests['compound limit window affinity current source next137 next limited rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['siteurl', 'home', 'plugin_alpha', 'blogname'], array_column($rows, 'name'));
    $t->same([1, '1', '4', 2.5], array_column($rows, 'class_value'));
    $t->same([15, 13, 10, 6], array_column($rows, 'frame_weight'));
};

$tests['compound limit window affinity current source next137 window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['sum', 'first_value'], array_column($windows, 'function'));
    $t->same(['frame_weight', 'frame_weight'], array_column($windows, 'alias'));
    $t->same(['ROWS', 'ROWS'], array_column($windows, 'frameUnit'));
    $t->same([0, 0], array_column($windows, 'preceding'));
    $t->same([1, 1], array_column($windows, 'following'));
};

$tests['compound limit window affinity current source next137 limit trace records skipped and truncated rows'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['limitTrace'];
    $t->same(6, $trace['current']['preLimitCount']);
    $t->same(8, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'name'));
    $t->same(['plugin_beta'], array_column($trace['next']['skippedBeforeOffset'], 'name'));
    $t->same(['theme_mods'], array_column($trace['current']['truncatedAfterLimit'], 'name'));
    $t->same(['active_plugins', 'rewrite_rules', 'theme_mods'], array_column($trace['next']['truncatedAfterLimit'], 'name'));
};

$tests['compound limit window affinity current source next137 affinity classes expose boundary changes'] = static function (TestRunner $t) use ($summary): void {
    $affinity = $summary()['affinity'];
    $t->true(in_array('string:1', $affinity['currentClasses'], true));
    $t->true(in_array('numeric:2.5', $affinity['currentClasses'], true));
    $t->true(in_array('string:4', $affinity['nextClasses'], true));
    $t->true(in_array('numeric:3', $affinity['changedClasses'], true));
    $t->same('numeric:3', $affinity['boundaryClasses']['currentLast']);
    $t->same('numeric:2.5', $affinity['boundaryClasses']['nextLast']);
};

$tests['compound limit window affinity current source next137 changed signatures and reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->true(str_contains($changed, '"name":"plugin_alpha"'));
    $t->true(str_contains($changed, '"class_value":"4"'));
    $t->true(in_array('limited-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-limit', $plan['replanReasons'], true));
    $t->true(in_array('compound-window-arm-source', $plan['replanReasons'], true));
    $t->true(in_array('affinity-class-boundary-changed', $plan['replanReasons'], true));
};

$tests['compound limit window affinity current source next137 rejects non compound select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan::compareNext137(
        'SELECT option_name AS name FROM wp_options LIMIT 1',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound limit window affinity current source next137 rejects compound without final limit'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan::compareNext137(
        'SELECT option_name AS name, sum(weight) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS frame_weight FROM wp_options UNION ALL SELECT option_name AS name, weight AS frame_weight FROM wp_options ORDER BY frame_weight',
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 48) as $case) {
    $tests['compound limit window affinity current source next137 generated limit boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $limit = 2 + ($case % 4);
        $offset = $case % 3;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 10 + $case, 'class_value' => $case],
                ['option_id' => 2, 'option_name' => 'autoload_text_' . $case, 'autoload' => 'yes', 'weight' => 9 + $case, 'class_value' => (string) $case],
                ['option_id' => 3, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'weight' => 8 + $case, 'class_value' => $case + 0.5],
                ['option_id' => 4, 'option_name' => 'theme_' . $case, 'autoload' => 'no', 'weight' => 7 + $case, 'class_value' => (string) ($case + 1)],
                ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'no', 'weight' => 6 + $case, 'class_value' => $case + 1],
            ],
        ];
        $sql = "SELECT option_name AS name, class_value, sum(weight) OVER (ORDER BY weight DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_weight FROM wp_options WHERE autoload = 'yes' UNION ALL SELECT option_name AS name, class_value, first_value(weight) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_weight FROM wp_options WHERE autoload = 'no' ORDER BY frame_weight DESC, name LIMIT {$limit} OFFSET {$offset}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(min($limit, 5 - $offset), count($rows));
        $t->true(isset($rows[0]['name'], $rows[0]['class_value'], $rows[0]['frame_weight']));
        $t->true($rows[0]['frame_weight'] >= $rows[count($rows) - 1]['frame_weight']);
    };
}

return $tests;
