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

$currentRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16LE'),
    $row(3, 'plugin_cache_alpha', 'UTF-16LE'),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_alpha', 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameHeaderEncodingFencePlan(
    $currentRows,
    $nextRows,
    currentDatabaseEncoding: 'UTF-16LE',
    nextDatabaseEncoding: 'UTF-16BE',
    preparedEncoding: 'UTF-16LE',
    currentSource: 'copied-wp-options@utf16le',
    nextSource: 'copied-wp-options@utf16be',
    currentSchemaCookie: 228,
    nextSchemaCookie: 229,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['currentMatchedRowids'] === [1, 2, 3]);
    assert($plan['nextMatchedRowids'] === [1, 2, 3]);
    assert($plan['canRetainRowsetButNotPreparedCursor'] === true);
    assert(in_array('database-text-encoding', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next228 self-test passed\n";
}

return [
    'scenario' => 'application-utf16-nocase-like-rtrim-current-source-next228',
    'applicationUse' => 'Copied wp_options scans retain the logical NOCASE/RTRIM LIKE rowset across a UTF-16LE to UTF-16BE source refresh, but force prepared cursor reprepare because the SQLite database text-encoding header changed.',
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
];
