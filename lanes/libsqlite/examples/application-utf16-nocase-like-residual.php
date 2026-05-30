<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encoding === 'UTF-16LE' ? 2 : 3,
    ];
};

$currentRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache_extra', 'UTF-16BE'),
    ['setting_id' => 3, 'key_name_bytes' => "\xff", 'text_encoding' => 2],
];

$nextRows = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache_extra_v2', 'UTF-16BE'),
    $row(4, 'PLUGIN_CACHE_NEW', 'UTF-16LE'),
    ['setting_id' => 5, 'key_name_bytes' => "\x3d\xd8", 'text_encoding' => 2],
];

$plan = SQLiteUtf16NocaseLikeCurrentSourceNextPlan::keyValueRowKeyResidualPlan(
    $currentRows,
    $nextRows,
    'plugin!_cache%',
    '!',
    'main.app_settings@140',
    'main.app_settings@141',
    'UTF-16LE',
    'UTF-16BE',
);

$summary = [
    'scenario' => 'application-utf16-nocase-like-residual',
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'currentMalformedRowids' => $plan['currentMalformedRowids'],
    'nextMalformedRowids' => $plan['nextMalformedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'applicationUse' => 'A copied UTF-16 app_settings table can continue a NOCASE key_name LIKE prefix scan over valid plugin keys while malformed current/next text bytes are isolated into cursor invalidation diagnostics.',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['currentRowids'] !== [1, 2]
        || $summary['nextRowids'] !== [1, 2, 4]
        || $summary['enteredRowids'] !== [4]
        || $summary['currentMalformedRowids'] !== [3]
        || $summary['nextMalformedRowids'] !== [5]
    ) {
        fwrite(STDERR, "application-utf16-nocase-like-residual self-test failed\n");
        exit(1);
    }

    echo "application-utf16-nocase-like-residual self-test passed\n";
}

return $summary;
