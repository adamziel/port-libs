<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimGlobCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$current = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, "plugin_cache\t", 'UTF-16LE'),
    $row(4, 'Plugin_Cache', 'UTF-8'),
];

$next = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, 'plugin_cache', 'UTF-16LE'),
    $row(5, 'plugin_cache_new', 'UTF-16LE'),
    $row(4, 'Plugin_Cache', 'UTF-8'),
];

$plan = SQLiteUtf16RtrimGlobCurrentSourceNextPlan::keyValueRowKeyPlan(
    $current,
    $next,
    'plugin_cache',
    'main.app_settings@cookie124',
    'main.app_settings@cookie125',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentCandidateRowids'] === [1, 2, 3]);
    assert($plan['currentResidualRejectedRowids'] === [2, 3]);
    assert($plan['nextRowids'] === [1, 3]);
    assert(in_array('encoded-bytes', $plan['invalidationReasons'], true));
    echo "application-utf16-rtrim-glob-current-source-next125 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
