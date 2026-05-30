<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingAffinityLikeCurrentSourceNextPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'blog_public', 'option_value' => 1],
    ['option_id' => 2, 'option_name' => 'plugin_alpha', 'option_value' => 'plugin_Alpha'],
    ['option_id' => 3, 'option_name' => 'plugin_percent', 'option_value' => 'plugin_100%_enabled'],
    ['option_id' => 4, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_blob')],
    ['option_id' => 5, 'option_name' => 'plugin_removed', 'option_value' => 'plugin_removed'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'blog_public', 'option_value' => '1'],
    ['option_id' => 2, 'option_name' => 'plugin_alpha', 'option_value' => 'plugin_alpha'],
    ['option_id' => 3, 'option_name' => 'plugin_percent', 'option_value' => 'plugin_100%_enabled'],
    ['option_id' => 4, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_blob')],
    ['option_id' => 6, 'option_name' => 'plugin_new', 'option_value' => 'plugin_new'],
];

$summary = [
    'scenario' => 'copied wp_options option_value LIKE current-source to next-source affinity scan current-next94',
    'applicationUse' => 'Copied Application option imports can invalidate LIKE/GLOB value cursors when text-affinity coercion, UTF-16 scan encoding, or matched rowsets change, without treating BLOB payloads as text and without ext/sqlite.',
    'pluginValuePlan' => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValuePlan(
        $currentRows,
        $nextRows,
        'option_value',
        'plugin%',
        'LIKE',
        'NOCASE',
        null,
        false,
        'UTF-16LE',
        'UTF-16BE',
        'main.wp_options',
        'main.wp_options',
        41,
        42,
    ),
    'numericValuePlan' => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValuePlan(
        $currentRows,
        $nextRows,
        'option_value',
        '1%',
        'LIKE',
        'BINARY',
        null,
        true,
        'UTF-16LE',
        'UTF-16LE',
        'main.wp_options',
        'main.wp_options',
        41,
        41,
    ),
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['pluginValuePlan']['currentRowids'] === [2, 3, 5]);
    assert($summary['pluginValuePlan']['nextRowids'] === [2, 3, 6]);
    assert($summary['pluginValuePlan']['changedTextRowids'] === [2]);
    assert($summary['pluginValuePlan']['changedEncodingRowids'] === [2, 3]);
    assert($summary['numericValuePlan']['changedStorageRowids'] === [1]);
    echo "application-option-value-affinity-like-current-next94 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
