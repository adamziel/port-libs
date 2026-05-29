<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$current = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row(4, 'plugin_cache_beta', 'UTF-16BE'),
    $row(5, 'plugin_cache_delta', 'UTF-8'),
];
$next = [
    $row(1, 'Plugin_Cache ', 'UTF-16BE'),
    $row(2, 'plugin_cache   ', 'UTF-16LE'),
    $row(9, 'plugin_cache_aardvark', 'UTF-16LE'),
    $row(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(4, 'plugin_cache_beta  ', 'UTF-16LE'),
    $row(5, 'plugin_cache_delta', 'UTF-8'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameLimitOffsetPlan(
    $current,
    $next,
    'plugin!_cache%',
    '!',
    2,
    2,
    'copied-wp-options',
    'copied-wp-options',
    193,
    193,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next193');
    assert($plan['currentLimitWindowRowids'] === [3, 4]);
    assert($plan['nextLimitWindowRowids'] === [9, 3]);
    assert($plan['limitOffsetResumeSafe'] === false);
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next193 self-test passed\n";
}

return $plan;
