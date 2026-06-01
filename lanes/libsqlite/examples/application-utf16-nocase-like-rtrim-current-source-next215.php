<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$row = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$current = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, "plugin_cache\0shadow", 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-16LE'),
];
$next = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, "plugin_cache\0shadow", 'UTF-16LE'),
    $row(3, 'plugin_cache_extra', 'UTF-16BE'),
    $row(4, "plugin_cache\0later", 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulTokenPlan(
    $current,
    $next,
    'plugin!_cache%',
    '!',
    ['key' => "plugin_cache\0shadow", 'rowid' => 2],
    'copied-app-settings@before-embedded-nul-token',
    'copied-app-settings@after-embedded-nul-token',
    214,
    215,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-nexttwoOneFive');
    assert($plan['currentEmbeddedNulRowids'] === [2]);
    assert($plan['nextEmbeddedNulRowids'] === [2, 4]);
    assert(in_array('source-or-schema-changed', $plan['candidateTokenUnsafeReasons'], true));
    assert($plan['embeddedNulNotCStringTerminator'] === true);
    echo "application-utf16-nocase-like-rtrim-current-source-nexttwoOneFive self-test passed\n";
}

return $plan;
