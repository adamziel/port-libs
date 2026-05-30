<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingNumericAffinityCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    ['option_id' => 1, 'option_name' => 'retry_limit', 'option_value' => '10'],
    ['option_id' => 2, 'option_name' => 'retry_limit_int', 'option_value' => 10],
    ['option_id' => 3, 'option_name' => 'retry_limit_decimal', 'option_value' => '10.0'],
    ['option_id' => 4, 'option_name' => 'retry_limit_text', 'option_value' => '10x'],
    ['option_id' => 5, 'option_name' => 'retry_limit_legacy', 'option_value' => 'Ten '],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'retry_limit', 'option_value' => 10],
    ['option_id' => 2, 'option_name' => 'retry_limit_int', 'option_value' => '10'],
    ['option_id' => 3, 'option_name' => 'retry_limit_decimal', 'option_value' => '10.5'],
    ['option_id' => 4, 'option_name' => 'retry_limit_text', 'option_value' => '10x'],
    ['option_id' => 5, 'option_name' => 'retry_limit_legacy', 'option_value' => 'Ten'],
    ['option_id' => 6, 'option_name' => 'retry_limit_new', 'option_value' => '0010'],
];

$numericPlan = SQLiteEncodingNumericAffinityCurrentSourceNextPlan::keyValueRowValueComparisonPlan(
    $currentRows,
    $nextRows,
    'option_value',
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
    'option_value',
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
