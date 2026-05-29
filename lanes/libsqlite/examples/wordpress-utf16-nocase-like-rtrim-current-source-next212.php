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
$currentPattern = "plugin{$escape}_%";
$nextPattern = "plugin{$escape}%%";
$currentRows = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row(3, 'plugin%cache', 'UTF-16LE'),
    $row(4, 'plugin_cache_extra', 'UTF-8'),
];
$nextRows = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'Plugin_Cache', 'UTF-16LE'),
    $row(3, 'plugin%cache', 'UTF-16BE'),
    $row(9, 'PLUGIN%NEW  ', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameUnicodeEscapePlan(
    $currentRows,
    $nextRows,
    $enc($currentPattern, 'UTF-16LE'),
    'UTF-16LE',
    $enc($nextPattern, 'UTF-16BE'),
    'UTF-16BE',
    $enc($escape, 'UTF-16LE'),
    'UTF-16LE',
    $enc($escape, 'UTF-16BE'),
    'UTF-16BE',
);

$payload = [
    'status' => $plan['status'],
    'currentPattern' => $plan['currentPattern'],
    'nextPattern' => $plan['nextPattern'],
    'escape' => $plan['currentEscape'],
    'prefix' => $plan['prefix'],
    'nextPrefix' => $plan['nextPrefix'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentAsciiEquivalentMatchedRowids' => $plan['currentAsciiEquivalentMatchedRowids'],
    'nextAsciiEquivalentMatchedRowids' => $plan['nextAsciiEquivalentMatchedRowids'],
    'unicodeEscapeCharacter' => $plan['unicodeEscapeCharacter'],
    'cursorInvalidated' => $plan['cursorInvalidated'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'wordpressUse' => 'Copied wp_options scans can bind UTF-16 LIKE patterns with a non-ASCII ESCAPE character and still plan the same NOCASE/RTRIM prefix range plus residual match that SQLite would use after decoding prepared statement bytes.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['status'] === 'utf16-nocase-like-rtrim-current-source-next212');
    assert($payload['escape'] === $escape);
    assert($payload['prefix'] === 'plugin_');
    assert($payload['nextPrefix'] === 'plugin%');
    assert($payload['currentMatchedRowids'] === [1, 2, 4]);
    assert($payload['nextMatchedRowids'] === [3, 9]);
    assert($payload['currentAsciiEquivalentMatchedRowids'] === [1, 2, 4]);
    assert($payload['nextAsciiEquivalentMatchedRowids'] === [3, 9]);
    assert($payload['unicodeEscapeCharacter'] === true);
    assert(in_array('unicode-escape-character', $payload['invalidationReasons'], true));
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next212 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
