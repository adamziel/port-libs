<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = SQLiteSelectSql::execute(
    'SELECT option_id, json_group_array(option_name ORDER BY option_id) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS CURRENT ROW) AS current_option FROM wp_options ORDER BY option_id',
    [
        'wp_options' => [
            ['option_id' => 1, 'autoload' => 'yes', 'enabled' => 1, 'option_name' => 'siteurl'],
            ['option_id' => 2, 'autoload' => 'yes', 'enabled' => 0, 'option_name' => 'disabled_yes'],
            ['option_id' => 3, 'autoload' => 'no', 'enabled' => 1, 'option_name' => 'rewrite_rules'],
        ],
    ],
);

$expected = [
    ['option_id' => 1, 'current_option' => '["siteurl"]'],
    ['option_id' => 2, 'current_option' => '[]'],
    ['option_id' => 3, 'current_option' => '["rewrite_rules"]'],
];

if ($rows !== $expected) {
    fwrite(STDERR, "application-json-aggregate-filter-window-frame-current-source-next117 self-test failed\n");
    var_export($rows);
    fwrite(STDERR, "\n");
    exit(1);
}

echo "application-json-aggregate-filter-window-frame-current-source-next117 self-test passed\n";
