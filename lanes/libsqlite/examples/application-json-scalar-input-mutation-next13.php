<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonMutation.php';
require_once __DIR__ . '/../src/SQLiteJsonPatch.php';
require_once __DIR__ . '/../src/SQLiteJsonRemove.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonRemove;

$decode = static function (string|SQLiteBlobValue|null $value): mixed {
    if ($value === null) {
        return null;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($value->bytes);
    }

    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
};

$optionValue = 1;

$report = [
    'option_name' => 'plugin_settings_version',
    'sourceSqlValue' => $optionValue,
    'jsonPatchPromotesScalarToObject' => $decode(SQLiteJsonPatch::patchSqlFunction('json_patch', $optionValue, '{"plugin":{"enabled":true}}')),
    'jsonSetRootReplacesScalar' => $decode(SQLiteJsonMutation::mutateSqlFunction('json_set', $optionValue, '$', '{"raw":true}')),
    'jsonSetNestedPathLeavesScalarUnchanged' => $decode(SQLiteJsonMutation::mutateSqlFunction('json_set', $optionValue, '$.plugin.enabled', true)),
    'jsonRemoveNestedPathLeavesScalarUnchanged' => $decode(SQLiteJsonRemove::removeSqlFunction('json_remove', $optionValue, '$.plugin')),
    'jsonRemoveRootReturnsSqlNull' => $decode(SQLiteJsonRemove::removeSqlFunction('json_remove', $optionValue, '$')),
    'jsonbPatchResultType' => SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $optionValue, '{"plugin":{"enabled":true}}') instanceof SQLiteBlobValue ? 'blob' : 'text',
    'applicationUse' => 'Preflight copied wp_options numeric option_value rows through SQLite JSON mutation functions without ext/sqlite, preserving scalar SQL JSON input behavior before plugin settings are promoted or cleaned up.',
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
