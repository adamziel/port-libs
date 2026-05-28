<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext261Plan.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext261Plan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current = [
    ['option_id' => 1, 'option_name_bytes' => $enc('Plugin_cache', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => 'enabled:core'],
    ['option_id' => 2, 'option_name_bytes' => $enc('plugin_cache_timeout', 'UTF-16BE'), 'name_text_encoding' => 'UTF-16BE', 'option_value' => 'disabled:15'],
    ['option_id' => 3, 'option_name_bytes' => $enc('plugin_theme', 'UTF-8'), 'name_text_encoding' => 'UTF-8', 'option_value' => 'enabled:theme'],
    ['option_id' => 4, 'option_name_bytes' => $enc('plugin_blob', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => new SQLiteBlobValue('enabled:blob')],
];

$next = [
    ['option_id' => 1, 'option_name_bytes' => $enc('Plugin_cache', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => 'enabled:core'],
    ['option_id' => 2, 'option_name_bytes' => $enc('plugin_cache_timeout', 'UTF-16BE'), 'name_text_encoding' => 'UTF-16BE', 'option_value' => 'enabled:15'],
    ['option_id' => 3, 'option_name_bytes' => $enc('plugin_theme', 'UTF-8'), 'name_text_encoding' => 'UTF-8', 'option_value' => 'disabled:theme'],
    ['option_id' => 4, 'option_name_bytes' => $enc('plugin_blob', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => new SQLiteBlobValue('enabled:blob')],
    ['option_id' => 5, 'option_name_bytes' => $enc('plugin_new', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => true],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNext261Plan::wordpressUtf16NameAndValueLikePlan(
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
    echo "wordpress-encoding-affinity-like-current-source-next261 self-test passed\n";
}

return [
    'scenario' => 'wordpress-encoding-affinity-like-current-source-next261',
    'wordpressUse' => 'Copied wp_options imports can bind UTF-16 LIKE patterns for option_name while applying SQLite text affinity to option_value before deciding whether a current-source cursor is reusable.',
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];
