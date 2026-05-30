<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
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
    $row(1, 'Plugin_Alpha', 'UTF-16LE'),
    $row(2, 'plugin_beta', 'UTF-16BE'),
    $row(3, 'theme_alpha', 'UTF-16LE'),
];

$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-16BE'),
    $row(2, 'plugin_beta', 'UTF-16BE'),
    $row(4, 'PLUGIN_added', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeCurrentSourceNextPlan::keyValueRowKeyLikePlan(
    $currentRows,
    $nextRows,
    'plugin%',
    null,
    false,
    'main.app_settings@cookie125',
    'main.app_settings@cookie126',
    125,
    126,
    1,
    2,
    'UTF-16LE',
    'UTF-16BE',
);

$summary = [
    'scenario' => 'application-utf16-nocase-like-range-bytes',
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'changedEncodingRowids' => $plan['changedEncodingRowids'],
    'rangeBytesChanged' => $plan['rangeBytesChanged'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'applicationUse' => 'Copied app_settings tables stored as UTF-16LE or UTF-16BE can re-evaluate key_name LIKE scans on a NOCASE index after source, schema, collation, or database-encoding changes without reusing stale current-source cursor bounds.',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['currentRowids'] !== [1, 2]
        || $summary['nextRowids'] !== [4, 1, 2]
        || $summary['enteredRowids'] !== [4]
        || $summary['changedEncodingRowids'] !== [1]
        || $summary['rangeBytesChanged'] !== true
    ) {
        fwrite(STDERR, "application-utf16-nocase-like-range-bytes self-test failed\n");
        exit(1);
    }

    echo "application-utf16-nocase-like-range-bytes self-test passed\n";
}

return $summary;
