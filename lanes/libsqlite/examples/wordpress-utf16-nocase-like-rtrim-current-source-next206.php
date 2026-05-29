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

$rows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache_new', 'UTF-16BE'),
    $row(3, 'theme_cache', 'UTF-8'),
];
$patternBytes = "\xfe\xff" . SQLiteEncodingCollationSourceCursor::encodeText('plugin!_cache%', 'UTF-16BE');

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedBomPatternPlan(
    $rows,
    $rows,
    SQLiteEncodingCollationSourceCursor::encodeText('plugin!_cache%', 'UTF-16LE'),
    'UTF-16LE',
    $patternBytes,
    'UTF-16BE',
    '!',
    'copied-wp-options',
    'copied-wp-options',
    206,
    206,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['nextPatternHadBom'] === true);
    assert($plan['nextPrefix'] === 'plugin_cache');
    assert($plan['nextMatchedRowids'] === [1, 2]);
    assert($plan['rawBomMatchedRowids'] === []);
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next206 self-test passed\n";
}

return $plan;
