<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingNumericAffinityCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'retry_limit', 'key_value' => '10'],
    ['setting_id' => 2, 'key_name' => 'retry_limit_int', 'key_value' => 10],
    ['setting_id' => 3, 'key_name' => 'retry_limit_decimal', 'key_value' => '10.0'],
    ['setting_id' => 4, 'key_name' => 'retry_limit_text', 'key_value' => '10x'],
    ['setting_id' => 5, 'key_name' => 'retry_limit_legacy', 'key_value' => 'Ten '],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'retry_limit', 'key_value' => 10],
    ['setting_id' => 2, 'key_name' => 'retry_limit_int', 'key_value' => '10'],
    ['setting_id' => 3, 'key_name' => 'retry_limit_decimal', 'key_value' => '10.5'],
    ['setting_id' => 4, 'key_name' => 'retry_limit_text', 'key_value' => '10x'],
    ['setting_id' => 5, 'key_name' => 'retry_limit_legacy', 'key_value' => 'Ten'],
    ['setting_id' => 6, 'key_name' => 'retry_limit_new', 'key_value' => '0010'],
];

$numericPlan = SQLiteEncodingNumericAffinityCurrentSourceNextPlan::keyValueRowValueComparisonPlan(
    $currentRows,
    $nextRows,
    'key_value',
    10,
    '=',
    'NUMERIC',
    'NONE',
    'BINARY',
    'UTF-16LE',
    'UTF-16BE',
    'main.app_settings@cookie107',
    'main.app_settings@cookie108',
    107,
    108,
);

$rtrimPlan = SQLiteEncodingNumericAffinityCurrentSourceNextPlan::keyValueRowValueComparisonPlan(
    $currentRows,
    $nextRows,
    'key_value',
    'Ten',
    '=',
    'NUMERIC',
    'NONE',
    'RTRIM',
    'UTF-16LE',
    'UTF-16BE',
    'main.app_settings@cookie107',
    'main.app_settings@cookie108',
    107,
    108,
);

echo json_encode([
    'applicationUse' => 'Preview app_settings numeric-affinity comparisons across current/next schema sources before rebuilding UTF-16 key-value cursors.',
    'numericProbe' => 10,
    'numericCurrentRowids' => $numericPlan['currentRowids'],
    'numericNextRowids' => $numericPlan['nextRowids'],
    'numericEnteredRowids' => $numericPlan['enteredRowids'],
    'numericExitedRowids' => $numericPlan['exitedRowids'],
    'changedStorageRowids' => $numericPlan['changedStorageRowids'],
    'changedCoercedStorageRowids' => $numericPlan['changedCoercedStorageRowids'],
    'changedBytesRowids' => $numericPlan['changedBytesRowids'],
    'invalidationReasons' => $numericPlan['invalidationReasons'],
    'rtrimTextFallbackCurrentRowids' => $rtrimPlan['currentRowids'],
    'rtrimTextFallbackNextRowids' => $rtrimPlan['nextRowids'],
    'dependencies' => $numericPlan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
