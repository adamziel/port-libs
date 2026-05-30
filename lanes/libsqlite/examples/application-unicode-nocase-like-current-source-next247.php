<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current = [
    ['option_id' => 1, 'option_name_bytes' => $enc('plugin_café_main', 'UTF-16LE'), 'text_encoding' => 2],
    ['option_id' => 2, 'option_name_bytes' => $enc('PLUGIN_CAFÉ_MAIN', 'UTF-16BE'), 'text_encoding' => 3],
    ['option_id' => 3, 'option_name' => 'plugin_cafÉ_main'],
    ['option_id' => 4, 'option_name' => 'plugin_cafe_plain'],
];

$next = [
    ['option_id' => 1, 'option_name_bytes' => $enc('plugin_café_main_v2', 'UTF-16BE'), 'text_encoding' => 3],
    ['option_id' => 2, 'option_name_bytes' => $enc('PLUGIN_CAFÉ_MAIN', 'UTF-16BE'), 'text_encoding' => 3],
    ['option_id' => 5, 'option_name' => 'plugin_café_new'],
    ['option_id' => 3, 'option_name' => 'plugin_cafÉ_main'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUnicodeNoCaseLikePlan($current, $next, 'plugin!_café%', '!');

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'encoding-collation-affinity-like-current-source-next247');
    assert($plan['currentMatchedRowids'] === [1]);
    assert($plan['nextMatchedRowids'] === [1, 5]);
    assert($plan['rangeRejectedReason'] === 'non_ascii_prefix_requires_residual_scan');
    assert($plan['changedEncodingRowids'] === [1]);
    assert($plan['cursorInvalidated'] === true);
    echo "application-unicode-nocase-like-current-source-next247 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-unicode-nocase-like-current-source-next247',
    'applicationUse' => 'Copied wp_options scans with accented plugin slugs keep LIKE residuals authoritative across UTF-16LE/UTF-16BE source switches because SQLite NOCASE folds ASCII only.',
    'pattern' => $plan['pattern'],
    'prefixIsAscii' => $plan['prefixIsAscii'],
    'indexUsable' => $plan['indexUsable'],
    'rangeRejectedReason' => $plan['rangeRejectedReason'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'changedEncodingRowids' => $plan['changedEncodingRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
