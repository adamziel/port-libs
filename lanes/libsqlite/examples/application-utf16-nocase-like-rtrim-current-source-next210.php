<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$row = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$currentRows = [
    $row(1, "Plugin\0Cache  ", 'UTF-16LE'),
    $row(2, "plugin\0cache_more", 'UTF-16BE'),
    $row(3, "plugin\0other", 'UTF-8'),
];
$nextRows = [
    $row(1, "Plugin\0Cache", 'UTF-16BE'),
    $row(2, "plugin\0cache_more", 'UTF-16LE'),
    $row(3, "plugin\0other", 'UTF-8'),
    $row(4, "PLUGIN\0CACHE_NEW", 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulPlan(
    $currentRows,
    $nextRows,
    "plugin\0cache%",
    null,
    'copied-app-settings@209',
    'copied-app-settings@210',
    209,
    210,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-nexttwoOneZero');
    assert($plan['prefixHex'] === '706c7567696e006361636865');
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 2, 4]);
    assert($plan['nextTextAfterNul'][4] === 'CACHE_NEW');
    echo "application-utf16-nocase-like-rtrim-current-source-nexttwoOneZero self-test passed\n";
}

return $plan;
