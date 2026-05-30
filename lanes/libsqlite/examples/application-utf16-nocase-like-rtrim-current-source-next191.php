<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$currentRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_config', 'UTF-16LE'),
    $row(4, 'plugin_other', 'UTF-8'),
    $row(6, "plugin_cache\t", 'UTF-16LE'),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16LE'),
    $row(6, "plugin_cache\t", 'UTF-16LE'),
    $row(9, 'plugin_cache_new', 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePreparedPatternRebindPlan(
    $currentRows,
    $nextRows,
    $enc('plugin!_%', 'UTF-16LE'),
    'UTF-16LE',
    $enc('plugin!_cache%', 'UTF-16BE'),
    'UTF-16BE',
    $enc('!', 'UTF-16LE'),
    'UTF-16LE',
    $enc('!', 'UTF-16BE'),
    'UTF-16BE',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next191');
    assert($plan['currentMatchedRowids'] === [1, 2, 6, 3, 4]);
    assert($plan['nextMatchedRowids'] === [1, 2, 6, 9]);
    assert($plan['matchedExitedRowids'] === [3, 4]);
    assert($plan['matchedEnteredRowids'] === [9]);
    assert($plan['mustReprepareForPatternChange'] === true);
    assert(in_array('decoded-pattern-or-escape', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next191 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentPattern' => $plan['currentPattern'],
    'nextPattern' => $plan['nextPattern'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'matchedExitedRowids' => $plan['matchedExitedRowids'],
    'matchedEnteredRowids' => $plan['matchedEnteredRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
