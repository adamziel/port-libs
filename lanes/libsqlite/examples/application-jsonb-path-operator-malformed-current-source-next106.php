<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    [
        'setting_id' => 1,
        'key_name' => 'feature_cache_settings',
        'key_value' => '{"feature":{"name":"cache","enabled":true,"priority":7,"limits":{"daily":25}}}',
        'load_policy' => 'yes',
    ],
    [
        'setting_id' => 2,
        'key_name' => 'feature_forms_settings',
        'key_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'feature' => [
                'name' => 'forms',
                'enabled' => false,
                'priority' => 3,
                'limits' => ['daily' => 10],
            ],
        ])),
        'load_policy' => 'no',
    ],
];

$nextRows = [
    $currentRows[0],
    [
        'setting_id' => 2,
        'key_name' => 'feature_forms_settings',
        'key_value' => new SQLiteBlobValue("\xcc" . '{"feature":{"name":"forms"}}'),
        'load_policy' => 'no',
    ],
    [
        'setting_id' => 3,
        'key_name' => 'feature_empty_settings',
        'key_value' => '{"feature":{"enabled":false}}',
        'load_policy' => 'no',
    ],
];

$textPlan = SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare(
    $currentRows,
    $nextRows,
    '$.feature.name',
    '->>',
);
$valuePlan = SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare(
    $currentRows,
    $nextRows,
    '$.feature.limits',
    '->',
);

echo json_encode([
    'applicationUse' => 'Copied app_settings import diagnostics can keep the current JSONB path operator source stable while a next statement source contains malformed JSONB blobs, distinguishing malformed JSONB from missing JSON paths before native SELECT execution aborts.',
    'textOperator' => [
        'path' => $textPlan['path'],
        'operator' => $textPlan['operator'],
        'currentSignature' => $textPlan['currentSignature'],
        'nextSignature' => $textPlan['nextSignature'],
        'currentMalformedRowids' => $textPlan['currentMalformedRowids'],
        'nextMalformedRowids' => $textPlan['nextMalformedRowids'],
        'nextMissingPathRowids' => $textPlan['nextMissingPathRowids'],
        'reprepareRequired' => $textPlan['reprepareRequired'],
        'reprepareReason' => $textPlan['reprepareReason'],
        'statementWouldAbort' => $textPlan['statementWouldAbort'],
    ],
    'valueOperator' => [
        'path' => $valuePlan['path'],
        'operator' => $valuePlan['operator'],
        'currentSignature' => $valuePlan['currentSignature'],
        'nextMalformedRowids' => $valuePlan['nextMalformedRowids'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
