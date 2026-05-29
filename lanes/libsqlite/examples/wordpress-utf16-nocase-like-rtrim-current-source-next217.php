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

$currentRows = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache alpha', 'UTF-16LE'),
    $row(4, 'PLUGIN_CACHE alpha  ', 'UTF-16BE'),
];
$nextRows = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'Plugin_Cache', 'UTF-16LE'),
    $row(3, 'plugin_cache alpha', 'UTF-16BE'),
    $row(4, 'PLUGIN_CACHE alpha', 'UTF-16LE'),
    $row(10, 'plugin_cache later', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedPatternSpacePlan(
    $currentRows,
    $nextRows,
    $enc('plugin!_cache %', 'UTF-16LE'),
    'UTF-16LE',
    $enc('plugin!_cache%', 'UTF-16BE'),
    'UTF-16BE',
);

$payload = [
    'status' => $plan['status'],
    'currentPattern' => $plan['currentPattern'],
    'nextPattern' => $plan['nextPattern'],
    'currentPrefix' => $plan['currentPrefix'],
    'nextPrefix' => $plan['nextPrefix'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentPatternSpaceFilteredRowids' => $plan['currentPatternSpaceFilteredRowids'],
    'preparedPatternSpacesRemainSignificant' => $plan['preparedPatternSpacesRemainSignificant'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'wordpressUse' => 'Copied wp_options migrations that bind UTF-16 LIKE patterns must keep a decoded space before % significant even though rtrim(option_name) strips trailing spaces from the left expression.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['status'] === 'utf16-nocase-like-rtrim-current-source-next217');
    assert($payload['currentPrefix'] === 'plugin_cache ');
    assert($payload['nextPrefix'] === 'plugin_cache');
    assert($payload['currentMatchedRowids'] === [3, 4]);
    assert($payload['nextMatchedRowids'] === [1, 2, 3, 4, 10]);
    assert($payload['currentPatternSpaceFilteredRowids'] === [1, 2]);
    assert($payload['preparedPatternSpacesRemainSignificant'] === true);
    assert(in_array('prepared-pattern-space-count', $payload['invalidationReasons'], true));
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next217 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
