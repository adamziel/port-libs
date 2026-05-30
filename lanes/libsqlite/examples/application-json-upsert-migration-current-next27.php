<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonMutation.php';
require_once __DIR__ . '/../src/SQLiteUpsertDoUpdateWherePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonUpsertMigrationPlan.php';

use PortLibs\LibSqlite\SQLiteJsonUpsertMigrationPlan;

$current = [
    [
        'option_id' => 1,
        'option_name' => 'widget_text',
        'option_value' => '{"version":1,"widgets":{"hero":{"title":"Old"}},"source":"current"}',
        'autoload' => 'yes',
        'migration_generation' => 2,
    ],
    [
        'option_id' => 2,
        'option_name' => 'theme_mods_twentytwentyfour',
        'option_value' => '{"version":3,"mods":{"color":"blue"},"source":"current"}',
        'autoload' => 'no',
        'migration_generation' => 4,
    ],
];

$incoming = [
    [
        'option_id' => 10,
        'option_name' => 'widget_text',
        'option_value' => '{"version":5,"widgets":{"hero":{"title":"Imported"}},"source":"import"}',
        'autoload' => 'no',
        'migration_generation' => 7,
    ],
    [
        'option_id' => 11,
        'option_name' => 'rewrite_rules',
        'option_value' => '{"version":1,"rules":{"post":"index.php?p=$matches[1]"},"source":"import"}',
        'autoload' => 'yes',
        'migration_generation' => 1,
    ],
];

$result = SQLiteJsonUpsertMigrationPlan::execute(
    $current,
    $incoming,
    [
        '$.migrated' => 1,
        '$.source' => ['excluded_json' => '$.source'],
        '$.previous_source' => ['current_json' => '$.source'],
        '$.incoming_version' => ['excluded_json' => '$.version'],
        '$.previous_generation' => ['current_column' => 'migration_generation'],
        '$.autoload_after' => ['excluded_column' => 'autoload'],
        '$.wp_import' => ['json' => '{"tool":"data-liberation","batch":27}'],
    ],
    static fn (array $currentRow, array $excluded): bool => (int) $excluded['migration_generation'] >= (int) $currentRow['migration_generation'],
);

$payload = [
    'applicationUse' => 'Preview copied wp_options INSERT ... ON CONFLICT DO UPDATE JSON migration rows using current-row JSON values, excluded JSON values, and RETURNING-style decoded output without ext/sqlite.',
    'changes' => $result['changes'],
    'returningNames' => array_column($result['returning_rows'], 'option_name'),
    'decodedReturning' => array_map(
        static fn (array $row): array => [
            'name' => $row['option_name'],
            'autoload' => $row['autoload'],
            'source' => $row['decoded_option_value']['source'],
            'previousSource' => $row['decoded_option_value']['previous_source'],
            'incomingVersion' => $row['decoded_option_value']['incoming_version'],
            'importBatch' => $row['decoded_option_value']['wp_import']['batch'],
        ],
        $result['decoded_returning'],
    ),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['changes'] === 2);
    assert($payload['returningNames'] === ['widget_text', 'rewrite_rules']);
    assert($payload['decodedReturning'][0]['previousSource'] === 'current');
    assert($payload['decodedReturning'][1]['previousSource'] === null);
}

return $payload;
