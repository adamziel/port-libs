<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityCurrentSourceCursor;

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16LikeGlobAffinityCurrentSourceCursor.php';

$rows = [
    ['option_id' => 1, 'option_name' => 'blog_public', 'option_value' => 1, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'rewrite_rules', 'option_value' => '10-rules', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_locale', 'option_value' => 'plugin_éclair', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_emoji', 'option_value' => 'plugin_😀_cache', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_blob'), 'autoload' => 'no'],
];

$numericLike = SQLiteUtf16LikeGlobAffinityCurrentSourceCursor::optionRowValueScan(
    $rows,
    'option_value',
    '1%',
    'LIKE',
    'BINARY',
    null,
    true,
    'UTF-16LE',
);

$unicodeGlob = SQLiteUtf16LikeGlobAffinityCurrentSourceCursor::optionRowValueScan(
    $rows,
    'option_value',
    'plugin_[À-ÿ]*',
    'GLOB',
    'BINARY',
    null,
    false,
    'UTF-16BE',
);

$emojiLike = SQLiteUtf16LikeGlobAffinityCurrentSourceCursor::optionRowValueScan(
    $rows,
    'option_value',
    'plugin_😀%',
    'LIKE',
    'BINARY',
    null,
    true,
    'UTF-16LE',
);

$summary = [
    'scenario' => 'application-utf16-like-glob-affinity-current-source-next87',
    'numeric_like_rowids' => array_column($numericLike, 'rowid'),
    'numeric_like_storage' => array_column($numericLike, 'originalStorage'),
    'unicode_glob_rowids' => array_column($unicodeGlob, 'rowid'),
    'unicode_glob_encoding' => $unicodeGlob[0]['textEncoding'] ?? null,
    'emoji_like_rowids' => array_column($emojiLike, 'rowid'),
    'emoji_utf16le_bytes' => $emojiLike[0]['bytesHex'] ?? null,
    'applicationUse' => 'Preview copied wp_options values scanned through SQLite LIKE/GLOB after TEXT affinity coercion and UTF-16 encoding, so numeric settings and Unicode plugin payloads can be filtered without ext/sqlite.',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['numeric_like_rowids'] === [1, 2]);
    assert($summary['numeric_like_storage'] === ['integer', 'text']);
    assert($summary['unicode_glob_rowids'] === [3]);
    assert($summary['unicode_glob_encoding'] === 'UTF-16BE');
    assert($summary['emoji_like_rowids'] === [4]);
    assert($summary['emoji_utf16le_bytes'] === '70006c007500670069006e005f003dd800de5f0063006100630068006500');
    echo "application-utf16-like-glob-affinity-current-source-next87 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
