<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundHavingWindowCurrentSourceNextPlan;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 10, 'enabled' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 20, 'enabled' => 1],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'bytes' => 8, 'enabled' => 1],
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
SELECT autoload,
       sum(bytes) AS total_bytes,
       count(*) OVER (
           ORDER BY autoload
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS source_enabled
  FROM wp_options
 GROUP BY autoload
HAVING sum(bytes) >= (
       SELECT count(*) * 15 FROM wp_options_stage
        WHERE wp_options_stage.autoload = wp_options.autoload
          AND wp_options_stage.enabled = 1
       )
UNION ALL
SELECT autoload,
       count(*) AS total_bytes,
       count(*) OVER (
           ORDER BY autoload
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS source_enabled
  FROM wp_options_stage
 GROUP BY autoload
HAVING count(*) <= (
       SELECT count(*) FROM wp_options
        WHERE wp_options.autoload = wp_options_stage.autoload
          AND wp_options.enabled = 1
       )
 ORDER BY autoload, total_bytes DESC
SQL;

$currentTables = ['wp_options' => $currentOptions, 'wp_options_stage' => $currentStage];
$nextTables = ['wp_options' => $nextOptions, 'wp_options_stage' => $nextStage];
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
    $t->same(['autoload', 'total_bytes'], $compound['orderColumns']);
};

$tests['compound having window current source next128 current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['no', 'no', 'yes', 'yes'], array_column($rows, 'autoload'));
    $t->same([48, 2, 30, 1], array_column($rows, 'total_bytes'));
    $t->same([1, 1, 1, 1], array_column($rows, 'source_enabled'));
};

$tests['compound having window current source next128 next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['no', 'no', 'yes', 'yes'], array_column($rows, 'autoload'));
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
            'wp_options' => [
                ['option_id' => $offset, 'option_name' => 'option_' . $offset, 'autoload' => 'yes', 'bytes' => 10 + $offset, 'enabled' => 1],
                ['option_id' => $offset + 100, 'option_name' => 'no_' . $offset, 'autoload' => 'no', 'bytes' => 30 + $offset, 'enabled' => 1],
            ],
            'wp_options_stage' => [
                ['stage_id' => 200 + $offset, 'option_name' => 'option_' . $offset, 'autoload' => 'yes', 'bytes' => 5 + $offset, 'enabled' => 1],
            ],
        ];
        $nextTables = $currentTables;
        $nextTables['wp_options'][] = ['option_id' => 300 + $offset, 'option_name' => 'next_' . $offset, 'autoload' => 'no', 'bytes' => 20 + $offset, 'enabled' => 1];
        $nextTables['wp_options_stage'][] = ['stage_id' => 400 + $offset, 'option_name' => 'next_' . $offset, 'autoload' => 'no', 'bytes' => 7 + $offset, 'enabled' => 1];

        $sql = "SELECT autoload, sum(bytes) AS total_bytes, count(*) OVER (ORDER BY autoload ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS source_enabled FROM wp_options GROUP BY autoload HAVING sum(bytes) >= (SELECT count(*) * 10 FROM wp_options_stage WHERE wp_options_stage.autoload = wp_options.autoload) UNION ALL SELECT autoload, count(*) AS total_bytes, count(*) OVER (ORDER BY autoload ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS source_enabled FROM wp_options_stage GROUP BY autoload HAVING count(*) <= (SELECT count(*) FROM wp_options WHERE wp_options.autoload = wp_options_stage.autoload) ORDER BY autoload, total_bytes DESC";
        $plan = SQLiteCompoundHavingWindowCurrentSourceNextPlan::compareHavingWindow($sql, $currentTables, $nextTables);

        $t->same([$offset + 30, $offset + 10, 1], array_column($plan['currentRows'], 'total_bytes'));
        $t->same([50 + (2 * $offset), 1, $offset + 10, 1], array_column($plan['nextRows'], 'total_bytes'));
        $t->same([0, 1], $plan['having']['correlatedArms']);
    };
}

$tests['compound having window current source next128 rejects non compound select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundHavingWindowCurrentSourceNextPlan::compareHavingWindow(
        'SELECT autoload, sum(bytes) AS total_bytes FROM wp_options GROUP BY autoload HAVING sum(bytes) > 1',
        $currentTables,
        $currentTables,
    ));
};

return $tests;
