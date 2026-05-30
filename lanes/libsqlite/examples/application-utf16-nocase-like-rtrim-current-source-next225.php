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
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16LE'),
    $row(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(5, 'plugin_cache_beta', 'UTF-8'),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row(5, 'plugin_cache_beta', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceBytePlan(
    $currentRows,
    $nextRows,
    'plugin!_cache%',
    '!',
    'stable',
    'stable',
    225,
    225,
);

$payload = [
    'status' => $plan['status'],
    'pattern' => $plan['pattern'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'changedEncodingRowids' => $plan['changedEncodingRowids'],
    'changedSourceByteRowids' => $plan['changedSourceByteRowids'],
    'stableDecodedChangedSourceRowids' => $plan['stableDecodedChangedSourceRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'applicationUse' => 'Copied wp_options cursors must restart when UTF-16 source bytes or endian encoding change, even when decoded NOCASE/RTRIM LIKE results are unchanged.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['status'] === 'utf16-nocase-like-rtrim-current-source-next225');
    assert($payload['currentMatchedRowids'] === [1, 2, 3, 5]);
    assert($payload['nextMatchedRowids'] === [1, 2, 3, 5]);
    assert($payload['changedEncodingRowids'] === [1, 2, 3, 5]);
    assert($payload['changedSourceByteRowids'] === [1, 2, 3, 5]);
    assert($payload['stableDecodedChangedSourceRowids'] === [1, 2, 3, 5]);
    assert($payload['invalidationReasons'] === ['text-encoding', 'source-bytes', 'utf16-byte-order']);
    echo "application-utf16-nocase-like-rtrim-current-source-next225 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
