<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNext160Plan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$code = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};
$row = static function (int $id, string $name, string $encoding) use ($enc, $code): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => $enc($name, $encoding),
        'text_encoding' => $code($encoding),
    ];
};

$rows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-8'),
    $row(4, 'plugin-cache', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNormalizedPatternPlan(
    $rows,
    $rows,
    $enc('plugin\\_cache%', 'UTF-16LE'),
    $code('UTF-16LE'),
    $enc('plugin\\_cache%', 'UTF-16BE'),
    $code('UTF-16BE'),
    $enc('\\', 'UTF-16LE'),
    $code('UTF-16LE'),
    $enc('\\', 'UTF-16BE'),
    $code('UTF-16BE'),
    'main.wp_options@copy-a',
    'main.wp_options@copy-a',
    162,
    162,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['byteOnlyReprepare'] === true);
    assert($plan['cursorReusable'] === true);
    assert($plan['semanticInvalidationReasons'] === []);
    assert($plan['currentMatchedRowids'] === [1, 2, 3]);
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next162 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-utf16-nocase-like-rtrim-current-source-next162',
    'wordpressUse' => 'Copied wp_options scans can reuse a NOCASE LIKE RTRIM cursor when a prepared UTF-16 pattern is rebound with equivalent decoded text but different byte order.',
    'matchedRowids' => $plan['currentMatchedRowids'],
    'byteReprepareReasons' => $plan['byteReprepareReasons'],
    'semanticInvalidationReasons' => $plan['semanticInvalidationReasons'],
    'cursorReusable' => $plan['cursorReusable'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
