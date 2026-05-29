<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$current = [
    $row(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'PLUGIN_CACHE  ', 'UTF-8'),
    $row(4, 'plugin_cache_alpha', 'UTF-16BE'),
];
$next = [
    $row(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row(2, 'theme_cache_reused_rowid', 'UTF-16BE'),
    $row(3, 'PLUGIN_CACHE  ', 'UTF-8'),
    $row(4, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(5, 'plugin_cache_delta', 'UTF-16LE'),
];
$tokenBytes = SQLiteEncodingCollationSourceCursor::encodeText('plugin_cache', 'UTF-16BE');
$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameReusedRowidResumePlan(
    $current,
    $next,
    'plugin!_cache%',
    '!',
    [
        'key' => 'plugin_cache',
        'rowid' => 2,
        'bytesHex' => bin2hex($tokenBytes),
        'encoding' => 'UTF-16BE',
    ],
    'stable',
    'stable',
    188,
    188,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next188');
    assert($plan['rowidReuseDetected'] === true);
    assert($plan['deletedTokenResumeSafe'] === false);
    assert($plan['replayPlanMode'] === 'reprepare-from-range-start-after-rowid-reuse');
    assert($plan['nextRowidProbe']['key'] === 'theme_cache_reused_rowid');
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next188 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'rowidReuseDetected' => $plan['rowidReuseDetected'],
    'resumeUnsafeReasons' => $plan['resumeUnsafeReasons'],
    'nextRowidProbe' => $plan['nextRowidProbe'],
    'replayPlanMode' => $plan['replayPlanMode'],
    'replayPlanRowids' => $plan['replayPlanRowids'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
