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

$current = [
    $row(1, 'PLUGIN_CAFÉ_MAIN ', 'UTF-16LE'),
    $row(2, 'plugin_café_main', 'UTF-16BE'),
    $row(3, 'plugin_cafÉ_aux', 'UTF-8'),
];
$next = [
    $row(1, 'plugin_café_main ', 'UTF-16BE'),
    $row(2, 'plugin_cafÉ_main', 'UTF-16LE'),
    $row(3, 'PLUGIN_CAFÉ_AUX ', 'UTF-8'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameAsciiOnlyNocasePlan($current, $next);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [3, 1]);
    assert($plan['nextMatchedRowids'] === [3, 2]);
    assert($plan['currentFalsePositiveRowids'] === [2]);
    assert($plan['nextFalsePositiveRowids'] === [1]);
    assert(in_array('ascii-only-nocase-boundary', $plan['invalidationReasons'], true));
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next231 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
