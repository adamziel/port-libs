<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowFilterCurrentSourcePlan;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 10, 'enabled' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 20, 'enabled' => 1],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'bytes' => 30, 'enabled' => 0],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'bytes' => 40, 'enabled' => 1],
];
$nextOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 10, 'enabled' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 25, 'enabled' => 1],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'bytes' => 40, 'enabled' => 1],
    ['option_id' => 5, 'option_name' => 'new_plugin_flag', 'autoload' => 'no', 'bytes' => 50, 'enabled' => 1],
];
$currentStage = [
    ['stage_id' => 10, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 12, 'enabled' => 1],
    ['stage_id' => 11, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'bytes' => 24, 'enabled' => 1],
    ['stage_id' => 12, 'option_name' => 'orphan_stage', 'autoload' => 'no', 'bytes' => 18, 'enabled' => 0],
];
$nextStage = [
    ['stage_id' => 10, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 12, 'enabled' => 1],
    ['stage_id' => 11, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'bytes' => 28, 'enabled' => 1],
    ['stage_id' => 13, 'option_name' => 'new_plugin_flag', 'autoload' => 'no', 'bytes' => 42, 'enabled' => 1],
];

$sql = <<<'SQL'
SELECT option_id AS id,
       option_name AS name,
       autoload,
       sum(bytes) FILTER (
           WHERE enabled
             AND EXISTS (
                 SELECT 1 FROM wp_options_stage
                  WHERE wp_options_stage.autoload = wp_options.autoload
                    AND wp_options_stage.enabled = 1
             )
       ) OVER (
           PARTITION BY autoload
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS filtered_bytes
  FROM wp_options
 WHERE EXISTS (
       SELECT 1 FROM wp_options_stage
        WHERE wp_options_stage.autoload = wp_options.autoload
          AND wp_options_stage.option_name = wp_options.option_name
       )
UNION ALL
SELECT stage_id AS id,
       option_name AS name,
       autoload,
       sum(bytes) FILTER (
           WHERE enabled
             AND bytes > (
                 SELECT avg(bytes) FROM wp_options
                  WHERE wp_options.autoload = wp_options_stage.autoload
             )
       ) OVER (
           PARTITION BY autoload
           ORDER BY stage_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS filtered_bytes
  FROM wp_options_stage
 WHERE EXISTS (
       SELECT 1 FROM wp_options
        WHERE wp_options.option_name = wp_options_stage.option_name
       )
 ORDER BY id
SQL;

$currentTables = ['wp_options' => $currentOptions, 'wp_options_stage' => $currentStage];
$nextTables = ['wp_options' => $nextOptions, 'wp_options_stage' => $nextStage];
$summary = static fn (): array => SQLiteCompoundWindowFilterCurrentSourcePlan::compare($sql, $currentTables, $nextTables);

$tests = [];

$tests['compound correlated window filter current source next126 status and arms'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-window-filter-current-source-ready', $plan['status']);
    $t->same(2, $plan['compound']['currentArms']);
    $t->same(2, $plan['compound']['nextArms']);
    $t->same(['UNION ALL'], $plan['compound']['operators']);
    $t->same(['id'], $plan['compound']['orderColumns']);
};

$tests['compound correlated window filter current source next126 current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([1, 10], array_column($rows, 'id'));
    $t->same(['siteurl', 'siteurl'], array_column($rows, 'name'));
    $t->same(['yes', 'yes'], array_column($rows, 'autoload'));
    $t->same([10, null], array_column($rows, 'filtered_bytes'));
};

$tests['compound correlated window filter current source next126 next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([1, 5, 10, 13], array_column($rows, 'id'));
    $t->same(['siteurl', 'new_plugin_flag', 'siteurl', 'new_plugin_flag'], array_column($rows, 'name'));
    $t->same(['yes', 'no', 'yes', 'no'], array_column($rows, 'autoload'));
    $t->same([10, 50, null, null], array_column($rows, 'filtered_bytes'));
};

$tests['compound correlated window filter current source next126 window diagnostics'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['window-filter-source', 'correlated-window-filter-source'], array_values(array_intersect($summary()['replanReasons'], ['window-filter-source', 'correlated-window-filter-source'])));
    $t->same(['filtered_bytes', 'filtered_bytes'], $windows['filteredAliases']);
    $t->same(['filtered_bytes', 'filtered_bytes'], $windows['correlatedFilters']);
    $t->same(['sum', 'sum'], array_column($windows['current'], 'function'));
    $t->same(['ROWS', 'ROWS'], array_column($windows['current'], 'frameUnit'));
};

$tests['compound correlated window filter current source next126 changed signatures'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(true, in_array('compound-rowset-changed', $plan['replanReasons'], true));
    $t->same(2, count($plan['changedSignatures']));
    $t->same(true, str_contains(implode("\n", $plan['changedSignatures']), 'new_plugin_flag'));
};

foreach (range(1, 24) as $offset) {
    $tests['compound correlated window filter current source next126 generated current next delta ' . $offset] = static function (TestRunner $t) use ($offset): void {
        $currentTables = [
            'wp_options' => [
                ['option_id' => $offset, 'option_name' => 'plugin_' . $offset, 'autoload' => 'yes', 'bytes' => 10 + $offset, 'enabled' => 1],
                ['option_id' => $offset + 1, 'option_name' => 'skip_' . $offset, 'autoload' => 'no', 'bytes' => 20 + $offset, 'enabled' => 0],
            ],
            'wp_options_stage' => [
                ['stage_id' => 100 + $offset, 'option_name' => 'plugin_' . $offset, 'autoload' => 'yes', 'bytes' => 30 + $offset, 'enabled' => 1],
            ],
        ];
        $nextTables = $currentTables;
        $nextTables['wp_options'][] = ['option_id' => 200 + $offset, 'option_name' => 'plugin_next_' . $offset, 'autoload' => 'yes', 'bytes' => 40 + $offset, 'enabled' => 1];
        $nextTables['wp_options_stage'][] = ['stage_id' => 300 + $offset, 'option_name' => 'plugin_next_' . $offset, 'autoload' => 'yes', 'bytes' => 50 + $offset, 'enabled' => 1];

        $sql = "SELECT option_id AS id, option_name AS name, sum(bytes) FILTER (WHERE enabled AND EXISTS (SELECT 1 FROM wp_options_stage WHERE wp_options_stage.option_name = wp_options.option_name)) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS filtered_bytes FROM wp_options WHERE option_name IN (SELECT option_name FROM wp_options_stage) UNION ALL SELECT stage_id AS id, option_name AS name, sum(bytes) FILTER (WHERE enabled AND EXISTS (SELECT 1 FROM wp_options WHERE wp_options.option_name = wp_options_stage.option_name)) OVER (ORDER BY stage_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS filtered_bytes FROM wp_options_stage ORDER BY id";
        $plan = SQLiteCompoundWindowFilterCurrentSourcePlan::compare($sql, $currentTables, $nextTables);

        $t->same([$offset, 100 + $offset], array_column($plan['currentRows'], 'id'));
        $t->same([$offset, 100 + $offset, 200 + $offset, 300 + $offset], array_column($plan['nextRows'], 'id'));
    };
}

$tests['compound correlated window filter current source next126 rejects non compound select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundWindowFilterCurrentSourcePlan::compare(
        'SELECT option_id FROM wp_options',
        $currentTables,
        $currentTables,
    ));
};

return $tests;
