<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundHavingWindowCurrentSourceNextPlan;

$currentSettings = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'bytes' => 10, 'enabled' => 1],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'load_policy' => 'yes', 'bytes' => 20, 'enabled' => 1],
    ['setting_id' => 3, 'key_name' => 'route_rules', 'load_policy' => 'no', 'bytes' => 8, 'enabled' => 1],
    ['setting_id' => 4, 'key_name' => 'module_registry', 'load_policy' => 'no', 'bytes' => 40, 'enabled' => 1],
];
$nextSettings = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'bytes' => 10, 'enabled' => 1],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'load_policy' => 'yes', 'bytes' => 25, 'enabled' => 1],
    ['setting_id' => 4, 'key_name' => 'module_registry', 'load_policy' => 'no', 'bytes' => 40, 'enabled' => 1],
    ['setting_id' => 5, 'key_name' => 'new_module_flag', 'load_policy' => 'no', 'bytes' => 50, 'enabled' => 1],
];
$currentStage = [
    ['stage_id' => 10, 'key_name' => 'base_url', 'load_policy' => 'yes', 'bytes' => 12, 'enabled' => 1],
    ['stage_id' => 11, 'key_name' => 'module_cache', 'load_policy' => 'no', 'bytes' => 24, 'enabled' => 1],
    ['stage_id' => 12, 'key_name' => 'orphan_stage', 'load_policy' => 'no', 'bytes' => 18, 'enabled' => 0],
];
$nextStage = [
    ['stage_id' => 10, 'key_name' => 'base_url', 'load_policy' => 'yes', 'bytes' => 12, 'enabled' => 1],
    ['stage_id' => 11, 'key_name' => 'module_cache', 'load_policy' => 'no', 'bytes' => 28, 'enabled' => 1],
    ['stage_id' => 13, 'key_name' => 'new_module_flag', 'load_policy' => 'no', 'bytes' => 42, 'enabled' => 1],
];

$sql = <<<'SQL'
SELECT load_policy,
       sum(bytes) AS total_bytes,
       count(*) OVER (
           ORDER BY load_policy
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS source_enabled
  FROM app_settings
 GROUP BY load_policy
HAVING sum(bytes) >= (
       SELECT count(*) * 15 FROM app_settings_stage
        WHERE app_settings_stage.load_policy = app_settings.load_policy
          AND app_settings_stage.enabled = 1
       )
UNION ALL
SELECT load_policy,
       count(*) AS total_bytes,
       count(*) OVER (
           ORDER BY load_policy
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS source_enabled
  FROM app_settings_stage
 GROUP BY load_policy
HAVING count(*) <= (
       SELECT count(*) FROM app_settings
        WHERE app_settings.load_policy = app_settings_stage.load_policy
          AND app_settings.enabled = 1
       )
 ORDER BY load_policy, total_bytes DESC
SQL;

$currentTables = ['app_settings' => $currentSettings, 'app_settings_stage' => $currentStage];
$nextTables = ['app_settings' => $nextSettings, 'app_settings_stage' => $nextStage];
$summary = static fn (): array => SQLiteCompoundHavingWindowCurrentSourceNextPlan::compareHavingWindow($sql, $currentTables, $nextTables);

$tests = [];

$tests['compound having window current source next128 status and dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-having-window-current-source-next128', $plan['status']);
    $t->same(true, in_array('sqlite-compound-having-window-current-source-next128', $plan['dependencies'], true));
};

$tests['compound having window current source next128 compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(['load_policy', 'total_bytes'], $compound['orderColumns']);
};

$tests['compound having window current source next128 current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['no', 'no', 'yes', 'yes'], array_column($rows, 'load_policy'));
    $t->same([48, 2, 30, 1], array_column($rows, 'total_bytes'));
    $t->same([1, 1, 1, 1], array_column($rows, 'source_enabled'));
};

$tests['compound having window current source next128 next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['no', 'no', 'yes', 'yes'], array_column($rows, 'load_policy'));
    $t->same([90, 2, 35, 1], array_column($rows, 'total_bytes'));
    $t->same([1, 1, 1, 1], array_column($rows, 'source_enabled'));
};

$tests['compound having window current source next128 having diagnostics'] = static function (TestRunner $t) use ($summary): void {
    $having = $summary()['having'];
    $t->same([0, 1], $having['arms']);
    $t->same([0, 1], $having['correlatedArms']);
    $t->same(['predicate', 'predicate'], array_column($having['current'], 'type'));
    $t->same(['bytes', ''], array_column($having['current'], 'valueColumn'));
};

$tests['compound having window current source next128 window diagnostics'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['source_enabled', 'source_enabled'], $windows['aliases']);
    $t->same(['count', 'count'], array_column($windows['current'], 'function'));
    $t->same(['ROWS', 'ROWS'], array_column($windows['current'], 'frameUnit'));
};

$tests['compound having window current source next128 replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->same(true, in_array('compound-rowset-changed', $reasons, true));
    $t->same(true, in_array('having-aggregate-source', $reasons, true));
    $t->same(true, in_array('correlated-having-source', $reasons, true));
};

$tests['compound having window current source next128 changed signatures'] = static function (TestRunner $t) use ($summary): void {
    $changed = $summary()['changedSignatures'];
    $t->same(4, count($changed));
    $t->same(true, str_contains(implode("\n", $changed), '"total_bytes":90'));
    $t->same(true, str_contains(implode("\n", $changed), '"total_bytes":48'));
};

foreach (range(1, 36) as $offset) {
    $tests['compound having window current source next128 generated current next aggregate gate ' . $offset] = static function (TestRunner $t) use ($offset): void {
        $currentTables = [
            'app_settings' => [
                ['setting_id' => $offset, 'key_name' => 'setting_' . $offset, 'load_policy' => 'yes', 'bytes' => 10 + $offset, 'enabled' => 1],
                ['setting_id' => $offset + 100, 'key_name' => 'no_' . $offset, 'load_policy' => 'no', 'bytes' => 30 + $offset, 'enabled' => 1],
            ],
            'app_settings_stage' => [
                ['stage_id' => 200 + $offset, 'key_name' => 'setting_' . $offset, 'load_policy' => 'yes', 'bytes' => 5 + $offset, 'enabled' => 1],
            ],
        ];
        $nextTables = $currentTables;
        $nextTables['app_settings'][] = ['setting_id' => 300 + $offset, 'key_name' => 'next_' . $offset, 'load_policy' => 'no', 'bytes' => 20 + $offset, 'enabled' => 1];
        $nextTables['app_settings_stage'][] = ['stage_id' => 400 + $offset, 'key_name' => 'next_' . $offset, 'load_policy' => 'no', 'bytes' => 7 + $offset, 'enabled' => 1];

        $sql = "SELECT load_policy, sum(bytes) AS total_bytes, count(*) OVER (ORDER BY load_policy ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS source_enabled FROM app_settings GROUP BY load_policy HAVING sum(bytes) >= (SELECT count(*) * 10 FROM app_settings_stage WHERE app_settings_stage.load_policy = app_settings.load_policy) UNION ALL SELECT load_policy, count(*) AS total_bytes, count(*) OVER (ORDER BY load_policy ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS source_enabled FROM app_settings_stage GROUP BY load_policy HAVING count(*) <= (SELECT count(*) FROM app_settings WHERE app_settings.load_policy = app_settings_stage.load_policy) ORDER BY load_policy, total_bytes DESC";
        $plan = SQLiteCompoundHavingWindowCurrentSourceNextPlan::compareHavingWindow($sql, $currentTables, $nextTables);

        $t->same([$offset + 30, $offset + 10, 1], array_column($plan['currentRows'], 'total_bytes'));
        $t->same([50 + (2 * $offset), 1, $offset + 10, 1], array_column($plan['nextRows'], 'total_bytes'));
        $t->same([0, 1], $plan['having']['correlatedArms']);
    };
}

$tests['compound having window current source next128 rejects non compound select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundHavingWindowCurrentSourceNextPlan::compareHavingWindow(
        'SELECT load_policy, sum(bytes) AS total_bytes FROM app_settings GROUP BY load_policy HAVING sum(bytes) > 1',
        $currentTables,
        $currentTables,
    ));
};

return $tests;
