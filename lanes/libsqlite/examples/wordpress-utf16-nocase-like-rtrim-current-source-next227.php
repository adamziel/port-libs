<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$nbsp = "\xc2\xa0";
$current = [
    $row(1, 'plugin_cache ', 'UTF-16LE'),
    $row(2, 'plugin_cache' . $nbsp, 'UTF-16BE'),
    $row(3, "plugin_cache\t", 'UTF-16LE'),
];
$next = [
    $row(1, 'PLUGIN_CACHE ', 'UTF-16BE'),
    $row(2, 'plugin_cache ', 'UTF-16LE'),
    $row(3, "plugin_cache\t", 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameAsciiSpaceBoundaryPlan($current, $next);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [1]);
    assert($plan['nextMatchedRowids'] === [1, 2]);
    assert($plan['currentNbspSuffixRowids'] === [2]);
    assert($plan['currentTabSuffixRowids'] === [3]);
    assert(in_array('non-ascii-space-rtrim-boundary', $plan['invalidationReasons'], true));
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next227 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
