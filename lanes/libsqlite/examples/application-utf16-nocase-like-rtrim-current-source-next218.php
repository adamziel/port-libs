<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encodingId($encoding),
];

$current = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, "plugin_cache\t", 'UTF-8'),
    $row(4, 'plugin_cache_alpha', 'UTF-16LE'),
];
$next = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16LE'),
    $row(3, "plugin_cache\t", 'UTF-8'),
    $row(4, 'plugin_cache_alpha', 'UTF-16LE'),
    $row(5, 'PLUGIN_CACHE_AARDVARK', 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyYieldPagePlan(
    $current,
    $next,
    'plugin!_cache%',
    $enc('!', 'UTF-16LE'),
    'UTF-16LE',
    $enc('!', 'UTF-16LE'),
    'UTF-16LE',
    2,
    1,
    'main.app_settings@copy-current',
    'main.app_settings@copy-next',
    217,
    218,
);

$summary = [
    'scenario' => 'application-utf16-nocase-like-rtrim-current-source-next218',
    'applicationUse' => 'Copied app_settings imports can resume a UTF-16 RTRIM/NOCASE LIKE page only when the ordered LIMIT window still matches the current-source token.',
    'status' => $plan['status'],
    'currentPageRowids' => $plan['currentPageRowids'],
    'nextPageRowids' => $plan['nextPageRowids'],
    'pageEnteredRowids' => $plan['pageEnteredRowids'],
    'rowsBeforeWindowChanged' => $plan['rowsBeforeWindowChanged'],
    'limitWindowChanged' => $plan['limitWindowChanged'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assert($summary['status'] === 'utf16-nocase-like-rtrim-current-source-next218');
    assert($summary['currentPageRowids'] === [2, 3]);
    assert($summary['nextPageRowids'] === [2, 3]);
    assert($summary['pageEnteredRowids'] === []);
    assert($summary['rowsBeforeWindowChanged'] === false);
    assert($summary['limitWindowChanged'] === false);
    assert(in_array('matched-rowset', $summary['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next218 self-test passed\n";
}

return $summary;
