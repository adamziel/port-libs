<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

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

$escape = "\xef\xbc\x81";
$currentPattern = "plugin{$escape}{$escape}%";
$nextPattern = "plugin{$escape}{$escape}{$escape}_%";
$currentRows = [
    $row(1, "plugin{$escape}cache", 'UTF-16LE'),
    $row(2, "Plugin{$escape}Cache  ", 'UTF-16BE'),
    $row(3, "plugin{$escape}_cache", 'UTF-16LE'),
];
$nextRows = [
    $row(3, "plugin{$escape}_cache", 'UTF-16BE'),
    $row(9, "PLUGIN{$escape}_SETTINGS  ", 'UTF-16LE'),
    $row(10, "plugin{$escape}%literal", 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSelfEscapedEscapePlan(
    $currentRows,
    $nextRows,
    $enc($currentPattern, 'UTF-16LE'),
    'UTF-16LE',
    $enc($nextPattern, 'UTF-16BE'),
    'UTF-16BE',
    $enc($escape, 'UTF-16LE'),
    'UTF-16LE',
);

$payload = [
    'status' => $plan['status'],
    'currentPattern' => $plan['currentPattern'],
    'nextPattern' => $plan['nextPattern'],
    'escape' => $plan['escape'],
    'prefix' => $plan['prefix'],
    'nextPrefix' => $plan['nextPrefix'],
    'currentEscapedEscapeOffsets' => $plan['currentEscapedEscapeOffsets'],
    'nextEscapedWildcardOffsets' => $plan['nextEscapedWildcardOffsets'],
    'currentPrefixContainsEscapeLiteral' => $plan['currentPrefixContainsEscapeLiteral'],
    'nextPrefixContainsEscapedWildcardLiteral' => $plan['nextPrefixContainsEscapedWildcardLiteral'],
    'cursorInvalidated' => $plan['cursorInvalidated'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'applicationUse' => 'Copied wp_options scans can bind UTF-16 LIKE patterns where a non-ASCII ESCAPE character escapes itself before an escaped wildcard, preserving the literal prefix for current-source invalidation instead of reusing a stale wildcard range.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['status'] === 'utf16-nocase-like-rtrim-current-source-next213');
    assert($payload['escape'] === $escape);
    assert($payload['prefix'] === "plugin{$escape}");
    assert($payload['nextPrefix'] === "plugin{$escape}_");
    assert($payload['currentEscapedEscapeOffsets'] === [7]);
    assert($payload['nextEscapedWildcardOffsets'] === [9]);
    assert($payload['currentPrefixContainsEscapeLiteral'] === true);
    assert($payload['nextPrefixContainsEscapedWildcardLiteral'] === true);
    assert(in_array('escaped-wildcard-prefix', $payload['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next213 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
