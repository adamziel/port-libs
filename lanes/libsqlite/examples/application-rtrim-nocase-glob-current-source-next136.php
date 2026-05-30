<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteRtrimNocaseGlobCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$current = [
    $row(1, 'plugin_cache', 'UTF-8'),
    $row(2, 'Plugin_Cache   ', 'UTF-16LE'),
    $row(3, 'plugin_cache_extra', 'UTF-16BE'),
];

$next = [
    $row(1, 'plugin_cache  ', 'UTF-16BE'),
    $row(2, 'Plugin_Cache', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-16BE'),
    $row(4, 'plugin_cache_new', 'UTF-16LE'),
];

$plan = SQLiteRtrimNocaseGlobCurrentSourceNextPlan::optionRowNameExpressionPlan(
    $current,
    $next,
    'plugin_*',
    'main.wp_options@135',
    'main.wp_options@136',
    21,
    22,
    4,
    5,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentCandidateRowids'] === [1, 2, 3]);
    assert($plan['currentMatchedRowids'] === [1, 3]);
    assert($plan['currentFalsePositiveRowids'] === [2]);
    assert($plan['nextMatchedRowids'] === [1, 3, 4]);
    assert($plan['enteredMatchedRowids'] === [4]);
    assert($plan['retainedEncodingChangedRowids'] === [1, 2]);
    assert($plan['invalidationReasons'] === [
        'source-name',
        'schema-cookie',
        'collation-version',
        'candidate-rowset',
        'matched-rowset',
        'text-encoding',
        'key-bytes',
    ]);
    echo "application-rtrim-nocase-glob-current-source-next136 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-rtrim-nocase-glob-current-source-next136',
    'applicationUse' => 'Copied wp_options scans over rtrim(option_name) COLLATE NOCASE can keep a broad encoded index range while applying byte-sensitive GLOB residual checks and invalidating stale cursors when the next source changes bytes, encoding, schema, or matching rowsets.',
    'pattern' => $plan['pattern'],
    'range' => $plan['range'],
    'currentCandidateRowids' => $plan['currentCandidateRowids'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'currentFalsePositiveRowids' => $plan['currentFalsePositiveRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'enteredMatchedRowids' => $plan['enteredMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
