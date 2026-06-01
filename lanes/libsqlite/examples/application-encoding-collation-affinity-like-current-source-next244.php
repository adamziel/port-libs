<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$row = static fn (int $id, string $name, string $encoding): array => [
    'setting_id' => $id,
    'key_name' => $name,
    'text_encoding' => $encoding,
];

$current = [
    $row(1, 'plugin_café_main', 'UTF-16LE'),
    $row(2, 'PLUGIN_café_aux', 'UTF-16BE'),
    $row(3, 'PLUGIN_CAFÉ_MAIN', 'UTF-16LE'),
    $row(4, 'theme_café_main', 'UTF-8'),
];

$next = [
    $row(1, 'plugin_café_main', 'UTF-16BE'),
    $row(2, 'PLUGIN_café_aux', 'UTF-16LE'),
    $row(5, 'plugin_café_archive', 'UTF-8'),
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUtf16KeyNameLikePlan(
    $current,
    $next,
    'plugin!_%café%',
    '!',
);

$summary = [
    'scenario' => 'application-encoding-collation-affinity-like-current-source-next244',
    'applicationUse' => 'Copied app_settings migrations can resume mixed UTF-8/UTF-16 key_name LIKE scans while preserving SQLite ASCII-only NOCASE behavior for accented plugin slugs.',
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'changedEncodingRowids' => $plan['changedEncodingRowids'],
    'cursorInvalidated' => $plan['cursorInvalidated'],
    'invalidationReasons' => $plan['invalidationReasons'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($summary['currentMatchedRowids'] === [2, 1]);
    assert($summary['nextMatchedRowids'] === [5, 1, 2]);
    assert($summary['changedEncodingRowids'] === [1, 2]);
    assert($summary['cursorInvalidated'] === true);
    echo "application-encoding-collation-affinity-like-current-source-next244 self-test passed\n";
}

return $summary;
