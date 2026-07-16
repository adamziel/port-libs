<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions141 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 9, 'class_value' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 7, 'class_value' => '1'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 6, 'class_value' => 2.5],
    ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 5, 'class_value' => '2'],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'yes', 'weight' => 4, 'class_value' => 3],
    ['option_id' => 6, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 3, 'class_value' => '3'],
];
$currentNetwork141 = [
    ['option_id' => 20, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 9, 'class_value' => 1],
    ['option_id' => 21, 'option_name' => 'transient_cache', 'autoload' => 'yes', 'weight' => 4, 'class_value' => 3],
];
$nextOptions141 = [
    ...$currentOptions141,
    ['option_id' => 7, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'weight' => 11, 'class_value' => '4'],
    ['option_id' => 8, 'option_name' => 'network_banner', 'autoload' => 'yes', 'weight' => 8, 'class_value' => 4],
];
$nextNetwork141 = [
    ...$currentNetwork141,
    ['option_id' => 22, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 6, 'class_value' => 2.5],
    ['option_id' => 23, 'option_name' => 'network_banner', 'autoload' => 'yes', 'weight' => 8, 'class_value' => 4],
];

$currentTables141 = ['wp_options' => $currentOptions141, 'network_options' => $currentNetwork141];
$nextTables141 = ['wp_options' => $nextOptions141, 'network_options' => $nextNetwork141];

$sql141 = <<<'SQL'
SELECT option_name AS name,
       class_value,
       sum(weight) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_name AS name,
       class_value,
       sum(weight) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM network_options
 WHERE autoload = 'yes'
 ORDER BY frame_weight DESC, name
 LIMIT 3 OFFSET 1
SQL;

$summary141 = static fn (): array => SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan::compareWindowExceptLimit($sql141, $currentTables141, $nextTables141);
$tests = [];

$tests['compound window except limit current source next141 status dependencies'] = static function (TestRunner $t) use ($summary141): void {
    $plan = $summary141();
    $t->same('compound-window-except-limit-current-source-next141-ready', $plan['status']);
    $t->same([
        'sqlite-compound-except-affinity',
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-select-sql-compound-final-limit',
        'sqlite-current-source-next141',
    ], $plan['dependencies']);
};

$tests['compound window except limit current source next141 compound metadata'] = static function (TestRunner $t) use ($summary141): void {
    $compound = $summary141()['compound'];
    $t->same(['EXCEPT'], $compound['operators']);
    $t->same([1], $compound['exceptArmIndexes']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['frame_weight', 'name'], $compound['orderColumns']);
    $t->same(3, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound window except limit current source next141 current rows'] = static function (TestRunner $t) use ($summary141): void {
    $rows = $summary141()['currentRows'];
    $t->same(['home', 'blogname', 'theme_mods'], array_column($rows, 'name'));
    $t->same(['1', 2.5, '2'], array_column($rows, 'class_value'));
    $t->same([13, 11, 9], array_column($rows, 'frame_weight'));
};

$tests['compound window except limit current source next141 next rows'] = static function (TestRunner $t) use ($summary141): void {
    $rows = $summary141()['nextRows'];
    $t->same(['network_banner', 'home', 'blogname'], array_column($rows, 'name'));
    $t->same([4, '1', 2.5], array_column($rows, 'class_value'));
    $t->same([15, 13, 11], array_column($rows, 'frame_weight'));
};

$tests['compound window except limit current source next141 window metadata'] = static function (TestRunner $t) use ($summary141): void {
    $windows = $summary141()['windows']['current'];
    $t->same(['sum', 'sum'], array_column($windows, 'function'));
    $t->same(['frame_weight', 'frame_weight'], array_column($windows, 'alias'));
    $t->same(['ROWS', 'ROWS'], array_column($windows, 'frameUnit'));
    $t->same([0, 0], array_column($windows, 'preceding'));
    $t->same([1, 1], array_column($windows, 'following'));
};

$tests['compound window except limit current source next141 except removed rows'] = static function (TestRunner $t) use ($summary141): void {
    $except = $summary141()['except'];
    $t->same([], array_column($except['currentRemoved'], 'name'));
    $t->same(['siteurl'], array_column($except['nextRemoved'], 'name'));
    $t->true(in_array('numeric:17', $except['nextRemovedClasses'], true));
};

$tests['compound window except limit current source next141 limit trace'] = static function (TestRunner $t) use ($summary141): void {
    $trace = $summary141()['limitTrace'];
    $t->same(6, $trace['current']['preLimitCount']);
    $t->same(7, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'name'));
    $t->same(['plugin_beta'], array_column($trace['next']['skippedBeforeOffset'], 'name'));
    $t->same(['transient_cache', 'plugin_alpha'], array_column($trace['current']['truncatedAfterLimit'], 'name'));
    $t->same(['theme_mods', 'transient_cache', 'plugin_alpha'], array_column($trace['next']['truncatedAfterLimit'], 'name'));
};

$tests['compound window except limit current source next141 affinity boundary'] = static function (TestRunner $t) use ($summary141): void {
    $affinity = $summary141()['affinity'];
    $t->true(in_array('string:1', $affinity['currentClasses'], true));
    $t->true(in_array('numeric:2.5', $affinity['nextClasses'], true));
    $t->true(in_array('string:2', $affinity['changedClasses'], true));
    $t->same('string:2', $affinity['boundaryClasses']['currentLast']);
    $t->same('numeric:2.5', $affinity['boundaryClasses']['nextLast']);
};

$tests['compound window except limit current source next141 changed signatures and reasons'] = static function (TestRunner $t) use ($summary141): void {
    $plan = $summary141();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"name":"theme_mods"', $changed);
    $t->contains('"name":"network_banner"', $changed);
    $t->true(in_array('limited-except-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-except-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('except-removal-set-changed', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-limit', $plan['replanReasons'], true));
    $t->true(in_array('compound-window-arm-source', $plan['replanReasons'], true));
    $t->true(in_array('affinity-class-boundary-changed', $plan['replanReasons'], true));
};

$tests['compound window except limit current source next141 rejects missing except'] = static function (TestRunner $t) use ($currentTables141): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan::compareWindowExceptLimit(
        'SELECT option_name AS name, sum(weight) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS frame_weight FROM wp_options UNION SELECT option_name AS name, weight AS frame_weight FROM network_options ORDER BY frame_weight LIMIT 1',
        $currentTables141,
        $currentTables141,
    ));
};

$tests['compound window except limit current source next141 rejects missing limit'] = static function (TestRunner $t) use ($currentTables141): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan::compareWindowExceptLimit(
        'SELECT option_name AS name, sum(weight) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS frame_weight FROM wp_options EXCEPT SELECT option_name AS name, weight AS frame_weight FROM network_options ORDER BY frame_weight',
        $currentTables141,
        $currentTables141,
    ));
};

$tests['compound window except limit current source next141 rejects missing window'] = static function (TestRunner $t) use ($currentTables141): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan::compareWindowExceptLimit(
        'SELECT option_name AS name, weight AS frame_weight FROM wp_options EXCEPT SELECT option_name AS name, weight AS frame_weight FROM network_options ORDER BY frame_weight LIMIT 1',
        $currentTables141,
        $currentTables141,
    ));
};

foreach (range(1, 48) as $case) {
    $tests['compound window except limit current source next141 generated boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $limit = 2 + ($case % 3);
        $offset = $case % 2;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 20 + $case, 'class_value' => $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 18 + $case, 'class_value' => (string) $case],
                ['option_id' => 3, 'option_name' => 'theme_' . $case, 'autoload' => 'yes', 'weight' => 16 + $case, 'class_value' => $case + 0.5],
                ['option_id' => 4, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 14 + $case, 'class_value' => (string) ($case + 1)],
            ],
            'network_options' => [
                ['option_id' => 20, 'option_name' => 'theme_' . $case, 'autoload' => 'yes', 'weight' => 16 + $case, 'class_value' => $case + 0.5],
            ],
        ];
        $sql = "SELECT option_name AS name, class_value, sum(weight) OVER (ORDER BY weight DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_weight FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_name AS name, class_value, sum(weight) OVER (ORDER BY weight DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_weight FROM network_options WHERE autoload = 'yes' ORDER BY frame_weight DESC, name LIMIT {$limit} OFFSET {$offset}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(min($limit, 4 - $offset), count($rows));
        $t->true(isset($rows[0]['name'], $rows[0]['class_value'], $rows[0]['frame_weight']));
        $t->true($rows[0]['frame_weight'] >= $rows[count($rows) - 1]['frame_weight']);
    };
}

return $tests;
