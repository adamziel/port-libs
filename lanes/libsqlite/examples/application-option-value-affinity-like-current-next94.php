<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingAffinityLikeCurrentSourceNextPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'public_flag', 'key_value' => 1],
    ['setting_id' => 2, 'key_name' => 'module_alpha', 'key_value' => 'module_Alpha'],
    ['setting_id' => 3, 'key_name' => 'module_percent', 'key_value' => 'module_100%_enabled'],
    ['setting_id' => 4, 'key_name' => 'module_blob', 'key_value' => new SQLiteBlobValue('module_blob')],
    ['setting_id' => 5, 'key_name' => 'module_removed', 'key_value' => 'module_removed'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'public_flag', 'key_value' => '1'],
    ['setting_id' => 2, 'key_name' => 'module_alpha', 'key_value' => 'module_alpha'],
    ['setting_id' => 3, 'key_name' => 'module_percent', 'key_value' => 'module_100%_enabled'],
    ['setting_id' => 4, 'key_name' => 'module_blob', 'key_value' => new SQLiteBlobValue('module_blob')],
    ['setting_id' => 6, 'key_name' => 'module_new', 'key_value' => 'module_new'],
];

$summary = [
    'scenario' => 'copied app_settings key_value LIKE current-source to next-source affinity scan current-next94',
    'applicationUse' => 'Copied application setting imports can invalidate LIKE/GLOB value cursors when text-affinity coercion, UTF-16 scan encoding, or matched rowsets change, without treating BLOB payloads as text and without ext/sqlite.',
    'moduleValuePlan' => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValuePlan(
        $currentRows,
        $nextRows,
        'key_value',
        'module%',
        'LIKE',
        'NOCASE',
        null,
        false,
        'UTF-16LE',
        'UTF-16BE',
        'main.app_settings',
        'main.app_settings',
        41,
        42,
    ),
    'numericValuePlan' => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValuePlan(
        $currentRows,
        $nextRows,
        'key_value',
        '1%',
        'LIKE',
        'BINARY',
        null,
        true,
        'UTF-16LE',
        'UTF-16LE',
        'main.app_settings',
        'main.app_settings',
        41,
        41,
    ),
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['moduleValuePlan']['currentRowids'] === [2, 3, 5]);
    assert($summary['moduleValuePlan']['nextRowids'] === [2, 3, 6]);
    assert($summary['moduleValuePlan']['changedTextRowids'] === [2]);
    assert($summary['moduleValuePlan']['changedEncodingRowids'] === [2, 3]);
    assert($summary['numericValuePlan']['changedStorageRowids'] === [1]);
    echo "application-option-value-affinity-like-current-next94 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
