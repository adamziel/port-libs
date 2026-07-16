<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current = [
    ['setting_id' => 1, 'key_name_bytes' => $enc('Plugin_cache', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'key_value' => 'enabled:core'],
    ['setting_id' => 2, 'key_name_bytes' => $enc('plugin_cache_timeout', 'UTF-16BE'), 'name_text_encoding' => 'UTF-16BE', 'key_value' => 'disabled:15'],
    ['setting_id' => 3, 'key_name_bytes' => $enc('plugin_theme', 'UTF-8'), 'name_text_encoding' => 'UTF-8', 'key_value' => 'enabled:theme'],
    ['setting_id' => 4, 'key_name_bytes' => $enc('plugin_blob', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'key_value' => new SQLiteBlobValue('enabled:blob')],
];

$next = [
    ['setting_id' => 1, 'key_name_bytes' => $enc('Plugin_cache', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'key_value' => 'enabled:core'],
    ['setting_id' => 2, 'key_name_bytes' => $enc('plugin_cache_timeout', 'UTF-16BE'), 'name_text_encoding' => 'UTF-16BE', 'key_value' => 'enabled:15'],
    ['setting_id' => 3, 'key_name_bytes' => $enc('plugin_theme', 'UTF-8'), 'name_text_encoding' => 'UTF-8', 'key_value' => 'disabled:theme'],
    ['setting_id' => 4, 'key_name_bytes' => $enc('plugin_blob', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'key_value' => new SQLiteBlobValue('enabled:blob')],
    ['setting_id' => 5, 'key_name_bytes' => $enc('plugin_new', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'key_value' => true],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUtf16NameAndValueLikePlan(
    $current,
    $next,
    $enc('plugin!_%', 'UTF-16LE'),
    'UTF-16LE',
    'enabled:%',
);

assert($plan['status'] === 'encoding-collation-affinity-like-current-source-next261');
assert($plan['currentMatchedRowids'] === [1, 3]);
assert($plan['nextMatchedRowids'] === [1, 2]);
assert(in_array('matched-rowset', $plan['invalidationReasons'], true));

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    echo "application-encoding-affinity-like-current-source-next261 self-test passed\n";
}

return [
    'scenario' => 'application-encoding-affinity-like-current-source-next261',
    'applicationUse' => 'Copied app_settings imports can bind UTF-16 LIKE patterns for key_name while applying SQLite text affinity to key_value before deciding whether a current-source cursor is reusable.',
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];
