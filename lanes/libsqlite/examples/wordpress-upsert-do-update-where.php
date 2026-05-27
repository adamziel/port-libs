<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$existing = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'hits' => 5, 'touched' => 'old'],
    ['option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'hits' => 2, 'touched' => 'old'],
];

$incoming = [
    ['option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'no', 'hits' => 3, 'touched' => 'import'],
    ['option_name' => 'home', 'option_value' => 'https://skip.test', 'autoload' => 'no', 'hits' => 1, 'touched' => 'skip'],
    ['option_name' => 'blogname', 'option_value' => 'Imported Site', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'insert'],
];

$result = SQLiteUpsertDoUpdateWherePlan::execute(
    $existing,
    $incoming,
    ['option_name'],
    [
        'option_value' => static fn (array $current, array $excluded): mixed => $excluded['option_value'],
        'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
        'hits' => static fn (array $current, array $excluded): int => (int) $current['hits'] + (int) $excluded['hits'],
        'touched' => static fn (array $current, array $excluded): mixed => $excluded['touched'],
    ],
    static fn (array $current, array $excluded): bool => (int) $current['hits'] >= 5,
);

echo json_encode([
    'changes' => $result['changes'],
    'updated' => array_column($result['updated_rows'], 'option_name'),
    'inserted' => array_column($result['inserted_rows'], 'option_name'),
    'skipped' => array_column($result['skipped_rows'], 'option_name'),
    'siteurl_hits' => $result['after'][0]['hits'],
], JSON_PRETTY_PRINT) . PHP_EOL;
