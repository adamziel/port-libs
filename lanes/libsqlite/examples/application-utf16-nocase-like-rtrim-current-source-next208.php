<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encoding = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row = static fn (int $id, string $name, int|string $encodingName): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encodingName),
    'text_encoding' => $encoding($encodingName),
];
$escape = static fn (string $text, int|string $encodingName, bool $bom = false): string => ($bom ? match ($encodingName) {
    'UTF-16LE', 2 => "\xff\xfe",
    'UTF-16BE', 3 => "\xfe\xff",
    default => "\xef\xbb\xbf",
} : '') . $enc($text, $encodingName);

$current = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin%cache', 'UTF-8'),
];
$next = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16LE'),
    $row(4, 'PLUGIN_CACHE_NEW', 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePreparedEscapePlan(
    $current,
    $next,
    'plugin!_cache%',
    $escape('!', 'UTF-16LE'),
    'UTF-16LE',
    $escape('~', 'UTF-16BE', true),
    'UTF-16BE',
);

$summary = [
    'scenario' => 'application-utf16-nocase-like-rtrim-current-source-next208',
    'applicationUse' => 'Copied wp_options imports can decode a prepared UTF-16 ESCAPE parameter before reusing an RTRIM/NOCASE LIKE cursor, avoiding stale range/residual behavior when the next source uses a BOM-prefixed escape byte string.',
    'status' => $plan['status'],
    'currentEscape' => $plan['currentEscape'],
    'nextEscape' => $plan['nextEscape'],
    'nextEscapeHadBom' => $plan['nextEscapeHadBom'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assert($summary['status'] === 'utf16-nocase-like-rtrim-current-source-next208');
    assert($summary['currentEscape'] === '!');
    assert($summary['nextEscape'] === '~');
    assert($summary['nextEscapeHadBom'] === true);
    assert($summary['currentMatchedRowids'] === [1, 2]);
    assert($summary['nextMatchedRowids'] === [1, 2, 4]);
    assert(in_array('prepared-escape-bom', $summary['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next208 self-test passed\n";
}

return $summary;
