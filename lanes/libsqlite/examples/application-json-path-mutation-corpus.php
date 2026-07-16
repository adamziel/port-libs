<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settings = [
    'plugin' => [
        'enabled' => false,
        'rules' => [
            ['name' => 'cache', 'priority' => 10],
            ['name' => 'seo', 'priority' => 20],
        ],
    ],
];

$jsonb = new SQLiteBlobValue(SQLiteJsonB::encode($settings));
$mutated = SQLiteJsonMutation::mutateSqlFunctionArguments('JSONB_SET', [
    $jsonb,
    '$.plugin.enabled',
    true,
    '$.plugin.rules[#]',
    new SQLiteJsonSubtypeValue('{"name":"media","priority":30}'),
    '$.plugin.rules[#-1].source',
    'runtime',
]);

if (!$mutated instanceof SQLiteBlobValue) {
    throw new RuntimeException('Expected jsonb_set() to return JSONB');
}

$text = SQLiteJsonMutation::mutateSqlFunctionArguments('JSON_SET', [
    '{"plugin":{"enabled":false,"rules":[{"name":"cache","priority":10}]}}',
    '$.plugin.rules[#]',
    new SQLiteJsonSubtypeValue('{"name":"seo","priority":20}'),
    '$.plugin.rules[#-1].source',
    'runtime',
]);

echo json_encode([
    'applicationUse' => 'Apply SQLite json_set/jsonb_set path edits to copied wp_options plugin settings without requiring ext/sqlite.',
    'jsonbDecodedAfter' => SQLiteJsonB::decode($mutated->bytes),
    'jsonTextAfter' => $text,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
