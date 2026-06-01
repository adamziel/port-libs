<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan;

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
    $row(1, 'module_cache', 'UTF-8'),
    $row(2, 'Module_Cache', 'UTF-16LE'),
    $row(3, 'module_cache ', 'UTF-16BE'),
];
$next = [
    $row(1, 'module_cache', 'UTF-16LE'),
    $row(2, 'module_Cache', 'UTF-16LE'),
    $row(3, 'module_cache', 'UTF-16BE'),
    $row(4, 'module_cache_new', 'UTF-8'),
];

$plan = SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan(
    $current,
    $next,
    'module_*',
    'TEXT',
    'NOCASE',
);

if ($plan['rangeUsable'] !== false || $plan['currentRowids'] !== [1, 3] || $plan['nextRowids'] !== [1, 2, 3, 4]) {
    fwrite(STDERR, "Unexpected NOCASE/RTRIM GLOB affinity plan\n");
    exit(1);
}

echo json_encode([
    'status' => 'ok',
    'fallback' => $plan['fallbackReason'],
    'entered' => $plan['enteredRowids'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT) . "\n";
