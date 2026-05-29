<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWordPressCurrentSmokePlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
];

$stagedRows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://current.example', 'autoload' => 'yes'],
    ['option_name' => 'blog_public', 'option_value' => '1', 'autoload' => 'yes'],
    ['option_name' => 'home', 'option_value' => 'https://duplicate.example', 'autoload' => 'yes', 'option_id' => 8],
    ['option_name' => 'stylesheet', 'option_value' => 'twentytwentyfive', 'autoload' => 'yes'],
];

$smoke = SQLiteWordPressCurrentSmokePlan::optionImport($currentRows, $stagedRows, [
    'fail_on_error' => false,
    'statement_prefix' => 'wp_current_smoke',
]);

if (in_array('--self-test', $argv, true)) {
    if ($smoke['status'] !== 'partial_errors' || $smoke['applied_count'] !== 3 || $smoke['wp_error_codes'] !== ['sqlite_constraint']) {
        fwrite(STDERR, "wordpress-current-smoke-option-import self-test failed\n");
        exit(1);
    }

    echo "wordpress-current-smoke-option-import self-test passed\n";
    exit(0);
}

echo json_encode([
    'wordpressUse' => 'Smoke the current pure-PHP SQLite wp_options import path without ext/sqlite: BEGIN IMMEDIATE admission, per-row yields, WP-style error shaping, and statement-only rollback for a duplicate option_name.',
    'status' => $smoke['status'],
    'appliedCount' => $smoke['applied_count'],
    'errorCodes' => $smoke['wp_error_codes'],
    'finalOptionNames' => $smoke['final_option_names'],
    'dirtyPages' => $smoke['dirty_pages'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
