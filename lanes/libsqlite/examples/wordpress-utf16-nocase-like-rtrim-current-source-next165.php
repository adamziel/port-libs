<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$code = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};
$row = static fn (int $id, string $name, string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $code($encoding),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameResumePlan(
    [
        $row(1, 'Plugin_Cache', 'UTF-16LE'),
        $row(2, 'plugin_cache_alpha  ', 'UTF-16BE'),
        $row(3, 'plugin_cache_beta', 'UTF-16LE'),
        $row(4, 'plugin_cache_tab' . "\t", 'UTF-16BE'),
    ],
    [
        $row(1, 'Plugin_Cache', 'UTF-16BE'),
        $row(2, 'plugin_cache_alpha', 'UTF-16BE'),
        $row(3, 'plugin_cache_beta  ', 'UTF-16LE'),
        $row(7, 'PLUGIN_CACHE_GAMMA', 'UTF-16LE'),
        $row(4, 'plugin_cache_tab' . "\t", 'UTF-16BE'),
    ],
    $enc('plugin\\_cache%', 'UTF-16LE'),
    $code('UTF-16LE'),
    $enc('plugin\\_cache%', 'UTF-16BE'),
    $code('UTF-16BE'),
    $enc('\\', 'UTF-16LE'),
    $code('UTF-16LE'),
    $enc('\\', 'UTF-16BE'),
    $code('UTF-16BE'),
    ['key' => 'plugin_cache_alpha', 'rowid' => 2],
    'stable',
    'stable',
    165,
    165,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['safeToResumeFromToken'] === true);
    assert($plan['resumePlanRowids'] === [3, 7, 4]);
    assert($plan['byteReprepareReasons'] === ['pattern-encoding', 'pattern-bytes', 'escape-bytes']);
    assert($plan['semanticInvalidationReasons'] === []);
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next165 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentMatched' => $plan['currentMatchedRowids'],
    'nextMatched' => $plan['nextMatchedRowids'],
    'lastYielded' => $plan['lastYielded'],
    'resumeMode' => $plan['resumePlanMode'],
    'resumeRowids' => $plan['resumePlanRowids'],
    'safeToResume' => $plan['safeToResumeFromToken'],
    'byteReprepareReasons' => $plan['byteReprepareReasons'],
    'semanticInvalidationReasons' => $plan['semanticInvalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
