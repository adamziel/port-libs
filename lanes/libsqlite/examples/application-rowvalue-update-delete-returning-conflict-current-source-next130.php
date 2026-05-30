<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'option_value' => 'feed'],
];

$plan = SQLiteUpdateDeleteReturningSql::execute(
    "UPDATE OR REPLACE wp_options SET (option_name, status, option_value) = ('siteurl', option_name || ':migrated', option_value || ':next') WHERE option_id IN (2, 3) RETURNING option_id, option_name, status ORDER BY option_id",
    ['wp_options' => $options],
    'option_id',
    [['option_name']],
);

if (($argv[1] ?? null) === '--self-test') {
    assert(array_column($plan['returning'], 'option_id') === [2, 3]);
    assert(array_column($plan['deleted_conflict_rows'], 'option_id') === [1, 2]);
    assert(array_column($plan['tables']['wp_options'], 'option_id') === [3]);
    assert($plan['tables']['wp_options'][0]['option_name'] === 'siteurl');
    echo "application-rowvalue-update-delete-returning-conflict-current-source-next130 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
