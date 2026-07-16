<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameGenerationPlan(
    [
        $row(1, 'Plugin_Cache  ', 2),
        $row(2, 'plugin_cache_shadow', 3),
        $row(3, 'plugin_other', 2),
    ],
    [
        $row(1, 'plugin_cache', 2),
        $row(2, 'plugin_cache_shadow  ', 3),
        $row(4, 'plugin_cache_new', 2),
    ],
    'plugin!_cache%',
    '!',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 2, 4]);
    assert(in_array('collation-generation', $plan['invalidationReasons'], true));
    assert(in_array('like-generation', $plan['invalidationReasons'], true));
    assert($plan['reprepareRequired'] === true);
    echo "application-utf16-nocase-like-rtrim-current-source-next161 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'matchedBefore' => $plan['currentMatchedRowids'],
    'matchedAfter' => $plan['nextMatchedRowids'],
    'retainedChangedBytes' => $plan['retainedChangedBytesRowids'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'reasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
