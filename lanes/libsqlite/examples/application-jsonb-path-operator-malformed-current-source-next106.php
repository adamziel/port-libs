<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => '{"plugin":{"name":"cache","enabled":true,"priority":7,"limits":{"daily":25}}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'name' => 'forms',
                'enabled' => false,
                'priority' => 3,
                'limits' => ['daily' => 10],
            ],
        ])),
        'autoload' => 'no',
    ],
];

$nextRows = [
    $currentRows[0],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => new SQLiteBlobValue("\xcc" . '{"plugin":{"name":"forms"}}'),
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"plugin":{"enabled":false}}',
        'autoload' => 'no',
    ],
];

$textPlan = SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare(
    $currentRows,
    $nextRows,
    '$.plugin.name',
    '->>',
);
$valuePlan = SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare(
    $currentRows,
    $nextRows,
    '$.plugin.limits',
    '->',
);

echo json_encode([
    'applicationUse' => 'Copied wp_options import diagnostics can keep the current JSONB path operator source stable while a next statement source contains malformed JSONB blobs, distinguishing malformed JSONB from missing JSON paths before native SELECT execution aborts.',
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
