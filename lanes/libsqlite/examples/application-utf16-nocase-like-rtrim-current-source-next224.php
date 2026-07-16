<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$row = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$currentRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row(4, 'plugin_cache_beta', 'UTF-8'),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16LE'),
    $row(5, 'plugin_cache_aardvark', 'UTF-16LE'),
    $row(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(4, 'plugin_cache_beta', 'UTF-8'),
];
$escape = SQLiteEncodingCollationSourceCursor::encodeText('!', 'UTF-16LE');

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyKeysetResumePlan(
    $currentRows,
    $nextRows,
    'plugin!_cache%',
    $escape,
    'UTF-16LE',
    $escape,
    'UTF-16LE',
    2,
    2,
    'plugin_cache',
    'copied-wp-options@current',
    'copied-wp-options@next',
    224,
    225,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['currentResumePageRowids'] === [3, 4]);
    assert($plan['nextResumePageRowids'] === [5, 3]);
    assert(in_array('resume-page-rowset', $plan['invalidationReasons'], true));
    assert($plan['keysetResumeAppliedAfterResidual'] === true);
    echo "application-utf16-nocase-like-rtrim-current-source-next224 self-test passed\n";
}

return [
    'scenario' => 'application-utf16-nocase-like-rtrim-current-source-next224',
    'applicationUse' => 'Copied app_settings scans can fence a resumed UTF-16 NOCASE LIKE RTRIM cursor by the saved keyset tail so new key_name rows before the next page cannot be skipped after source refresh.',
    'currentResumePageRowids' => $plan['currentResumePageRowids'],
    'nextResumePageRowids' => $plan['nextResumePageRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
];
