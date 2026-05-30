<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$code = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $code($encoding),
];

$current = [
    $row(1, 'plugin_cache%enabled', 'UTF-16LE'),
    $row(2, 'Plugin_Cache%Enabled', 'UTF-16BE'),
    $row(3, 'plugin_cache_enabled', 'UTF-8'),
    $row(4, 'plugin_cache%beta', 'UTF-16LE'),
];

$next = [
    $row(1, 'plugin_cache%enabled', 'UTF-16BE'),
    $row(2, 'Plugin_Cache%Enabled2', 'UTF-16LE'),
    $row(4, 'plugin_cache_beta', 'UTF-16BE'),
    $row(5, 'PLUGIN_CACHE%NEW', 'UTF-8'),
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationNonAsciiEscapeLikePlan($current, $next);

if (
    $plan['status'] !== 'encoding-collation-affinity-like-current-source-next248'
    || $plan['prefix'] !== 'plugin_cache%'
    || $plan['escape'] !== 'é'
    || $plan['currentMatchedRowids'] !== [4, 1, 2]
    || $plan['nextMatchedRowids'] !== [1, 2, 5]
    || $plan['changedResidualRowids'] !== [4]
    || !in_array('like-residual-result', $plan['invalidationReasons'], true)
) {
    fwrite(STDERR, "application-nonascii-escape-like-current-source-next248 self-test failed\n");
    exit(1);
}

echo json_encode([
    'status' => $plan['status'],
    'patternHex' => $plan['patternHex'],
    'escapeHex' => $plan['escapeHex'],
    'prefix' => $plan['prefix'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'changedResidualRowids' => $plan['changedResidualRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
