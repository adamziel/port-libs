<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$row = static fn (int $id, string $name, string $encoding = 'UTF-16LE', string $storage = 'text'): array => [
    'option_id' => $id,
    'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
    'text_encoding' => $encoding === 'UTF-16LE' ? 2 : 3,
    'storage_class' => $storage,
];

$current = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-16LE', 'blob'),
    $row(4, 'theme_cache', 'UTF-16LE'),
];
$next = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra_v2', 'UTF-16LE'),
    $row(5, 'PLUGIN_cache_new', 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan::wordpressOptionNameGlobPlan(
    $current,
    $next,
    $argv[1] ?? 'plugin_cache*',
    'main.wp_options@147',
    'main.wp_options@148',
    'UTF-16LE',
    'UTF-16BE',
);

echo json_encode([
    'wordpressUse' => 'Preview a UTF-16 wp_options NOCASE index cursor for GLOB while preserving SQLite case-sensitive GLOB residual matching after TEXT affinity.',
    'pattern' => $plan['pattern'],
    'collation' => $plan['collation'],
    'globResidualCaseSensitive' => $plan['globResidualCaseSensitive'],
    'currentCandidateRowids' => $plan['currentCandidateRowids'],
    'currentMatchedRowids' => $plan['currentRowids'],
    'currentResidualRejectedRowids' => $plan['currentResidualRejectedRowids'],
    'nextCandidateRowids' => $plan['nextCandidateRowids'],
    'nextMatchedRowids' => $plan['nextRowids'],
    'nextResidualRejectedRowids' => $plan['nextResidualRejectedRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
