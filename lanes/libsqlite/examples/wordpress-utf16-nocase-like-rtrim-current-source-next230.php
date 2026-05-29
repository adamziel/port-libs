<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

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
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, "Plugin_Cache\n", 'UTF-16BE'),
    $row(3, "plugin_cache\f", 'UTF-16LE'),
    $row(4, 'PLUGIN_CACHE ', 'UTF-8'),
];
$next = [
    $row(1, 'plugin_cache ', 'UTF-16BE'),
    $row(2, 'Plugin_Cache', 'UTF-16LE'),
    $row(3, "plugin_cache\f", 'UTF-16BE'),
    $row(4, 'PLUGIN_CACHE ', 'UTF-8'),
    $row(5, 'plugin_cache', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameLineBreakBoundaryPlan($current, $next);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next230');
    assert($plan['currentMatchedRowids'] === [1, 4]);
    assert($plan['nextMatchedRowids'] === [1, 2, 4, 5]);
    assert($plan['currentLineBreakSuffixRowids'] === [2]);
    assert($plan['currentFormFeedSuffixRowids'] === [3]);
    assert(in_array('non-space-rtrim-line-boundary', $plan['invalidationReasons'], true));
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next230 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-utf16-nocase-like-rtrim-current-source-next230',
    'status' => $plan['status'],
    'matchedEnteredRowids' => $plan['matchedEnteredRowids'],
    'currentLineBreakSuffixRowids' => $plan['currentLineBreakSuffixRowids'],
    'currentFormFeedSuffixRowids' => $plan['currentFormFeedSuffixRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
