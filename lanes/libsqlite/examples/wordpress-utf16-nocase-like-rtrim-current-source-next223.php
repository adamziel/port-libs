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
    $row(1, 'plugin_cache_alpha', 'UTF-16LE'),
    $row(2, 'plugin_cache_beta', 'UTF-16BE'),
    $row(3, 'plugin_cache_gamma', 'UTF-8'),
    $row(4, 'plugin_cache_zeta', 'UTF-16LE'),
];
$next = [
    $row(1, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(2, 'plugin_cache_beta', 'UTF-16LE'),
    $row(3, 'plugin_cache_gamma', 'UTF-8'),
    $row(4, 'plugin_cache_zeta', 'UTF-16BE'),
    $row(5, 'PLUGIN_CACHE_ZULU', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameDescYieldPagePlan(
    $current,
    $next,
    limit: 2,
    offset: 1,
    currentSource: 'copied-wp-options@before-desc-page',
    nextSource: 'copied-wp-options@after-desc-page',
    currentSchemaCookie: 222,
    nextSchemaCookie: 223,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next223');
    assert($plan['currentPageRowids'] === [3, 2]);
    assert($plan['nextPageRowids'] === [4, 3]);
    assert(in_array('desc-limit-window-rowset', $plan['invalidationReasons'], true));
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next223 self-test passed\n";
}

return $plan;
