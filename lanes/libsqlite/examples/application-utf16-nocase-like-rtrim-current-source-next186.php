<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$current = [
    $row(1, 'Plugin_Cache', 2),
    $row(2, 'plugin_cache_alpha', 2),
    $row(3, 'plugin_cache' . "\t", 3),
];
$next = [
    $row(1, 'Plugin_Cache', 3),
    $row(2, 'plugin_cache_alpha  ', 3),
    $row(4, 'plugin_cache_beta', 2),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyResumeBoundaryPlan(
    $current,
    $next,
    'plugin!_cache%',
    '!',
    'main.app_settings@185',
    'main.app_settings@186',
    185,
    186,
    ['key' => 'plugin_cache', 'rowid' => 1],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next186');
    assert($plan['currentResumeRowids'] === [2, 3]);
    assert($plan['nextResumeRowids'] === [2, 4]);
    assert($plan['resumeBoundaryChangedRowids'] === [3, 4]);
    assert($plan['byteOrderOnlyChangedRowids'] === [1]);
    assert($plan['mustReopenSourceCursor'] === true);
    echo "application-utf16-nocase-like-rtrim-current-source-next186 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'pattern' => $plan['pattern'],
    'currentResumeRowids' => $plan['currentResumeRowids'],
    'nextResumeRowids' => $plan['nextResumeRowids'],
    'resumeBoundaryChangedRowids' => $plan['resumeBoundaryChangedRowids'],
    'byteOrderOnlyChangedRowids' => $plan['byteOrderOnlyChangedRowids'],
    'mustReopenSourceCursor' => $plan['mustReopenSourceCursor'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
