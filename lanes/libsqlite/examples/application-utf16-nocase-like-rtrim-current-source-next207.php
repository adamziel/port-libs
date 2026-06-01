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
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-16LE'),
];
$next = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache  ', 'UTF-16LE'),
    $row(3, 'plugin_cache_extra', 'UTF-16BE'),
    $row(4, 'PLUGIN_CACHE_NEW  ', 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyRtrimCollationRebindPlan(
    $current,
    $next,
    'plugin!_cache%',
    '!',
    true,
    false,
    'copied-wp-options@before-rtrim-collation',
    'copied-wp-options@after-rtrim-collation',
    206,
    207,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-nexttwoZeroSeven');
    assert($plan['currentMatchedRowids'] === [1, 2, 3]);
    assert($plan['nextMatchedRowids'] === [1, 2, 3, 4]);
    assert(in_array('rtrim-collation-rebound', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-nexttwoZeroSeven self-test passed\n";
}

return $plan;
