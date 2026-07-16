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
        'setting_id' => 1,
        'key_name' => 'display_banner',
        'key_value' => '{"version":1,"widgets":{"hero":{"title":"Old"}},"source":"current"}',
        'load_policy' => 'yes',
        'migration_generation' => 2,
    ],
    [
        'setting_id' => 2,
        'key_name' => 'theme_palette',
        'key_value' => '{"version":3,"mods":{"color":"blue"},"source":"current"}',
        'load_policy' => 'no',
        'migration_generation' => 4,
    ],
];

$incoming = [
    [
        'setting_id' => 10,
        'key_name' => 'display_banner',
        'key_value' => '{"version":5,"widgets":{"hero":{"title":"Imported"}},"source":"import"}',
        'load_policy' => 'no',
        'migration_generation' => 7,
    ],
    [
        'setting_id' => 11,
        'key_name' => 'route_rules',
        'key_value' => '{"version":1,"rules":{"post":"index.php?p=$matches[1]"},"source":"import"}',
        'load_policy' => 'yes',
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
        '$.load_policy_after' => ['excluded_column' => 'load_policy'],
        '$.app_import' => ['json' => '{"tool":"data-liberation","batch":27}'],
    ],
    static fn (array $currentRow, array $excluded): bool => (int) $excluded['migration_generation'] >= (int) $currentRow['migration_generation'],
);

$payload = [
    'applicationUse' => 'Preview app_settings INSERT ... ON CONFLICT DO UPDATE JSON migration rows using current-row JSON values, excluded JSON values, and RETURNING-style decoded output without ext/sqlite.',
    'changes' => $result['changes'],
    'returningKeys' => array_column($result['returning_rows'], 'key_name'),
    'decodedReturning' => array_map(
        static fn (array $row): array => [
            'name' => $row['key_name'],
            'load_policy' => $row['load_policy'],
            'source' => $row['decoded_key_value']['source'],
            'previousSource' => $row['decoded_key_value']['previous_source'],
            'incomingVersion' => $row['decoded_key_value']['incoming_version'],
            'importBatch' => $row['decoded_key_value']['app_import']['batch'],
        ],
        $result['decoded_returning'],
    ),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['changes'] === 2);
    assert($payload['returningKeys'] === ['display_banner', 'route_rules']);
    assert($payload['decodedReturning'][0]['previousSource'] === 'current');
    assert($payload['decodedReturning'][1]['previousSource'] === null);
}

return $payload;
