<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'hits' => 5],
    ['option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'hits' => 2],
    ['option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'hits' => 7],
];

$sql = "INSERT INTO wp_options(option_name, option_value, autoload, hits) VALUES "
    . "('siteurl','https://new.test','no',9),"
    . "('new_plugin','enabled','no',1),"
    . "('home','https://home-new.test','yes',4),"
    . "('runtime_cache','warm','no',2) "
    . 'ON CONFLICT(option_name) DO NOTHING '
    . "RETURNING option_name AS name, option_value, option_name || ':' || hits AS import_key";

$result = SQLiteUpsertReturningSql::execute($sql, ['wp_options' => $options], [['option_name']]);

if (($argv[1] ?? null) === '--self-test') {
    assert($result['changes'] === 2);
    assert(array_column($result['returning'], 'name') === ['new_plugin', 'runtime_cache']);
    assert(array_column($result['skipped_rows'], 'option_name') === ['siteurl', 'home']);
    assert($result['after'][0]['option_value'] === 'https://old.test');
    echo "application-upsert-do-nothing-returning-current self-test passed\n";
    return;
}

echo json_encode([
    'applicationUse' => 'Preview copied wp_options INSERT ... ON CONFLICT DO NOTHING RETURNING so idempotent imports can emit only newly admitted option rows without ext/sqlite.',
    'changes' => $result['changes'],
    'returning' => $result['returning'],
    'skippedNames' => array_column($result['skipped_rows'], 'option_name'),
    'afterNames' => array_column($result['after'], 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
