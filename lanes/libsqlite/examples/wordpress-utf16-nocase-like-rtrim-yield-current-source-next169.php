<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$code = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};
$row = static function (int $id, string $name, string $encoding) use ($enc, $code): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => $enc($name, $encoding),
        'text_encoding' => $code($encoding),
    ];
};

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameYieldReplayPlan(
    [
        $row(1, 'Plugin_Cache', 'UTF-16LE'),
        $row(2, 'plugin_cache_alpha  ', 'UTF-16BE'),
        $row(3, 'plugin_cache_beta', 'UTF-16LE'),
        $row(4, 'plugin_cache_tab' . "\t", 'UTF-16BE'),
    ],
    [
        $row(1, 'Plugin_Cache', 'UTF-16BE'),
        $row(2, 'plugin_cache_alpha', 'UTF-16BE'),
        $row(3, 'plugin_cache_beta  ', 'UTF-16LE'),
        $row(4, 'plugin_cache_tab' . "\t", 'UTF-16BE'),
        $row(7, 'PLUGIN_CACHE_GAMMA', 'UTF-16LE'),
        $row(8, 'plugin_cache_early', 'UTF-8'),
    ],
    $enc('plugin\\_cache%', 'UTF-16LE'),
    2,
    $enc('plugin\\_cache%', 'UTF-16BE'),
    3,
    $enc('\\', 'UTF-16LE'),
    2,
    $enc('\\', 'UTF-16BE'),
    3,
    ['key' => 'plugin_cache_alpha', 'rowid' => 2],
    3,
    'stable',
    'stable',
    169,
    169,
);

$payload = [
    'status' => $plan['status'],
    'yieldMode' => $plan['yieldMode'],
    'yieldedRowids' => $plan['yieldedRowids'],
    'deferredRowids' => $plan['deferredRowids'],
    'highWaterToken' => $plan['highWaterToken'],
    'safeToContinueYield' => $plan['safeToContinueYield'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($payload['status'] === 'utf16-nocase-like-rtrim-current-source-next169');
    assert($payload['yieldMode'] === 'continue-yield-page');
    assert($payload['yieldedRowids'] === [3, 8, 7]);
    assert($payload['deferredRowids'] === [4]);
    assert($payload['highWaterToken'] === ['key' => 'plugin_cache_gamma', 'rowid' => 7]);
    assert($payload['safeToContinueYield'] === true);
    echo "wordpress-utf16-nocase-like-rtrim-yield-current-source-next169 self-test passed\n";

    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
