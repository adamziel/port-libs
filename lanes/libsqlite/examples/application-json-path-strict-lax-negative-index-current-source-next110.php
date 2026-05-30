<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan;

$currentRows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => '{"plugins":[{"slug":"seo"},{"slug":"cache"},{"slug":"forms"}],"meta":{"version":1}}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'theme_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugins' => [['slug' => 'blocks'], ['slug' => 'patterns']],
            'meta' => ['version' => 2],
        ])),
    ],
];

$nextRows = [
    $currentRows[0],
    [
        'option_id' => 2,
        'option_name' => 'theme_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugins' => [['slug' => 'blocks'], ['slug' => 'patterns'], ['slug' => 'stylebook']],
            'meta' => ['version' => 3],
        ])),
    ],
];

$plan = SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare($currentRows, $nextRows, [
    '$.plugins[#-1].slug',
    'strict $.plugins[#-1].slug',
    'lax $.plugins[#-1].slug',
    '$.plugins[-1].slug',
]);

echo json_encode([
    'surface' => $plan['surface'],
    'validPaths' => $plan['validPaths'],
    'invalidPaths' => $plan['invalidPaths'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentLastSlugs' => [
        $plan['current']['rows'][1]['paths']['$.plugins[#-1].slug']['value'],
        $plan['current']['rows'][2]['paths']['$.plugins[#-1].slug']['value'],
    ],
    'nextLastSlugs' => [
        $plan['next']['rows'][1]['paths']['$.plugins[#-1].slug']['value'],
        $plan['next']['rows'][2]['paths']['$.plugins[#-1].slug']['value'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
