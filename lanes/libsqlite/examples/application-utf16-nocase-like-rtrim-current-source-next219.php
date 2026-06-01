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
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$emoji = "\xf0\x9f\x98\x80";
$rocket = "\xf0\x9f\x9a\x80";
$currentRows = [
    $row(1, 'plugin_cacheA', 'UTF-16LE'),
    $row(2, 'Plugin_Cache' . $emoji, 'UTF-16BE'),
    $row(3, 'plugin_cache' . $emoji . 'x', 'UTF-16LE'),
    $row(4, 'plugin_cache  ', 'UTF-16BE'),
    $row(6, 'plugin_cache' . $rocket, 'UTF-16LE'),
];
$nextRows = [
    $row(1, 'plugin_cacheA', 'UTF-16BE'),
    $row(2, 'Plugin_Cache' . $emoji, 'UTF-16LE'),
    $row(3, 'plugin_cache' . $emoji, 'UTF-16BE'),
    $row(4, 'plugin_cache' . "\t", 'UTF-16LE'),
    $row(10, 'PLUGIN_CACHE' . $emoji, 'UTF-16LE'),
    $row(11, 'plugin_cache' . $emoji . 'x', 'UTF-16LE'),
    $row(6, 'plugin_cache' . $rocket, 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySupplementaryWildcardPlan(
    $currentRows,
    $nextRows,
);

$payload = [
    'status' => $plan['status'],
    'pattern' => $plan['pattern'],
    'prefix' => $plan['prefix'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentSupplementaryRowids' => $plan['currentSupplementaryRowids'],
    'nextSupplementaryRowids' => $plan['nextSupplementaryRowids'],
    'currentCodeUnitWildcardTrapRowids' => $plan['currentCodeUnitWildcardTrapRowids'],
    'nextCodeUnitWildcardTrapRowids' => $plan['nextCodeUnitWildcardTrapRowids'],
    'likeUnderscoreConsumesUnicodeCharacter' => $plan['likeUnderscoreConsumesUnicodeCharacter'],
    'utf16SurrogatePairIsOneLikeCharacter' => $plan['utf16SurrogatePairIsOneLikeCharacter'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'applicationUse' => 'Copied app_settings scans can keep UTF-16 supplementary-plane option names binary safe: one LIKE underscore wildcard consumes one decoded emoji character, while RTRIM and NOCASE remain SQLite-compatible ASCII operations.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['status'] === 'utf16-nocase-like-rtrim-current-source-next219');
    assert($payload['pattern'] === 'plugin!_cache_');
    assert($payload['prefix'] === 'plugin_cache');
    assert($payload['currentMatchedRowids'] === [1, 2, 6]);
    assert($payload['nextMatchedRowids'] === [4, 1, 2, 3, 10, 6]);
    assert($payload['currentCodeUnitWildcardTrapRowids'] === [2, 6]);
    assert($payload['nextCodeUnitWildcardTrapRowids'] === [2, 3, 6, 10]);
    assert($payload['likeUnderscoreConsumesUnicodeCharacter'] === true);
    assert($payload['utf16SurrogatePairIsOneLikeCharacter'] === true);
    assert(in_array('utf16-code-unit-wildcard-trap', $payload['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next219 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
