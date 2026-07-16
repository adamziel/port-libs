<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16LikeGlobCurrentNextCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16GlobRangeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteUtf16GlobRangeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobCurrentNextCursor;

$enc = static fn (string $text): string => SQLiteUtf16LikeGlobCurrentNextCursor::encodeUtf16($text, 'UTF-16LE');
$row = static fn (int $id, string $name, string $load_policy = 'yes'): array => [
    'setting_id' => $id,
    'key_name' => $name,
    'key_name_utf16' => $enc($name),
    'load_policy' => $load_policy,
];

$current = [
    $row(1, 'plugin_cache'),
    $row(2, 'plugin_cache_old'),
    $row(3, 'plugin_😀_cache'),
    $row(4, 'theme_alpha'),
];

$next = [
    $row(1, 'plugin_cache'),
    $row(2, 'plugin_cache_new'),
    $row(3, 'plugin_😀_cache_v2'),
    $row(4, 'theme_alpha'),
    $row(5, 'plugin_enabled'),
];

$plan = SQLiteUtf16GlobRangeCurrentSourceNextPlan::keyValueRowKeyGlobRange(
    $current,
    $next,
    'plugin_*',
    'BINARY',
    'UTF-16LE',
    'UTF-16LE',
    'main.app_settings@cookie102',
    'main.app_settings@cookie103',
    102,
    103,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['cursorReusable'] === false);
    assert($plan['reprepareReasons'] === ['source-name', 'schema-cookie', 'matched-rowset', 'key-bytes']);
    assert($plan['current']['rangeBytesHex']['lowerInclusive'] === '70006c007500670069006e005f00');
    assert($plan['next']['rowids'] === [1, 2, 5, 3]);
    echo "application-utf16-glob-range-current-source-next102 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
