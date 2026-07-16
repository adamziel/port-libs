<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan;

$code = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};

$row = static function (int $id, string $name, string $value, string $nameEncoding, string $valueEncoding) use ($code): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $nameEncoding),
        'key_value_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($value, $valueEncoding),
        'name_text_encoding' => $code($nameEncoding),
        'value_text_encoding' => $code($valueEncoding),
    ];
};

$current = [
    $row(1, 'plugin_cache', '10', 'UTF-8', 'UTF-8'),
    $row(2, 'Plugin_Cache   ', '11', 'UTF-16LE', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', '9.5', 'UTF-16BE', 'UTF-16LE'),
    $row(4, 'plugin_cache_future', '15e0', 'UTF-16LE', 'UTF-8'),
];

$next = [
    $row(1, 'plugin_cache  ', '10.0', 'UTF-16BE', 'UTF-16LE'),
    $row(2, 'Plugin_Cache', '11', 'UTF-16BE', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', '8', 'UTF-16BE', 'UTF-16LE'),
    $row(4, 'plugin_cache_future', '16', 'UTF-16LE', 'UTF-8'),
    $row(5, 'plugin_cache_new', '12', 'UTF-16LE', 'UTF-8'),
];

$plan = SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyValuePlan(
    $current,
    $next,
    'plugin_*',
    '9.5',
    '14',
    'main.app_settings@144',
    'main.app_settings@145',
    31,
    32,
    8,
    9,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [1, 3, 4]);
    assert($plan['currentAffinityMatchedRowids'] === [1, 3]);
    assert($plan['nextAffinityMatchedRowids'] === [1, 5]);
    assert($plan['enteredAffinityMatchedRowids'] === [5]);
    assert(in_array('affinity-rowset', $plan['invalidationReasons'], true));
    assert(in_array('affinity-value', $plan['invalidationReasons'], true));
    echo "application-utf16-rtrim-glob-affinity-current-source-next145 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-utf16-rtrim-glob-affinity-current-source-next145',
    'applicationUse' => 'Copied app_settings plugin settings can scan a UTF-16 rtrim(key_name) COLLATE NOCASE GLOB range while applying byte-sensitive GLOB residuals and NUMERIC affinity to key_value before reusing or invalidating a current-source cursor.',
    'pattern' => $plan['pattern'],
    'numericRange' => $plan['numericRange'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'currentAffinityMatchedRowids' => $plan['currentAffinityMatchedRowids'],
    'nextAffinityMatchedRowids' => $plan['nextAffinityMatchedRowids'],
    'enteredAffinityMatchedRowids' => $plan['enteredAffinityMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
